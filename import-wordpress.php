<?php
/**
 * WordPress Import Script
 * Imports published posts from WordPress XML export file
 * 
 * Usage: 
 * - Web: Access via browser (requires admin login) - /import-wordpress.php
 * - CLI: php import-wordpress.php
 */

require_once 'config.php';

$isWeb = php_sapi_name() !== 'cli';

// For security, require admin login when accessed via web
if ($isWeb) {
    requireLogin();
}

$xmlFile = __DIR__ . '/omklimat.WordPress.2025-12-21.xml';

if (!file_exists($xmlFile)) {
    if ($isWeb) {
        die("Error: WordPress XML file not found: $xmlFile");
    } else {
        die("Error: WordPress XML file not found: $xmlFile\n");
    }
}

if ($isWeb) {
    // Web interface
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Import WordPress Posts</title>
        <link rel="stylesheet" href="styles.css">
        <style>
            .import-container {
                max-width: 800px;
                margin: 2rem auto;
                padding: 2rem;
                background: white;
                border-radius: 8px;
            }
            .btn {
                padding: 0.75rem 2rem;
                background: #333;
                color: white;
                border: none;
                border-radius: 4px;
                cursor: pointer;
                font-size: 1rem;
                text-decoration: none;
                display: inline-block;
            }
            .btn:hover {
                background: #555;
            }
            .output {
                margin-top: 2rem;
                padding: 1rem;
                background: #f5f5f5;
                border-radius: 4px;
                font-family: monospace;
                white-space: pre-wrap;
                max-height: 500px;
                overflow-y: auto;
            }
            .success { color: #2e7d32; }
            .error { color: #d32f2f; }
            .info { color: #1976d2; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="import-container">
                <h1>Import WordPress Posts</h1>
                <p>This will import all published posts from the WordPress XML export file.</p>
                <a href="admin/dashboard.php" class="btn">← Back to Dashboard</a>
                <hr style="margin: 2rem 0;">
                <div class="output">
    <?php
    ob_start();
}

echo "Starting WordPress import...\n";
echo "Reading XML file...\n";

// Load XML file
libxml_use_internal_errors(true);
$xml = simplexml_load_file($xmlFile);

if ($xml === false) {
    $errors = libxml_get_errors();
    foreach ($errors as $error) {
        echo "XML Error: " . $error->message . "\n";
    }
    die("Failed to parse XML file.\n");
}

$db = getDB();
$imported = 0;
$skipped = 0;
$errors = 0;

// Track featured images (thumbnail_id => attachment_url)
$featuredImages = [];

// First pass: collect attachment URLs for featured images
echo "Collecting featured image data...\n";
foreach ($xml->channel->item as $item) {
    $postType = (string)$item->children('wp', true)->post_type;
    $status = (string)$item->children('wp', true)->status;
    
    if ($postType === 'attachment' && $status === 'inherit') {
        $postId = (int)$item->children('wp', true)->post_id;
        $attachmentUrl = (string)$item->children('wp', true)->attachment_url;
        
        if ($postId && $attachmentUrl) {
            $featuredImages[$postId] = $attachmentUrl;
        }
    }
}

// Second pass: import published posts
echo "Importing published posts...\n";
foreach ($xml->channel->item as $item) {
    $postType = (string)$item->children('wp', true)->post_type;
    $status = (string)$item->children('wp', true)->status;
    
    // Only import published posts (not pages, attachments, etc.)
    if ($postType !== 'post' || $status !== 'publish') {
        continue;
    }
    
    $title = (string)$item->title;
    $content = (string)$item->children('content', true)->encoded;
    $postDate = (string)$item->children('wp', true)->post_date;
    $postId = (int)$item->children('wp', true)->post_id;
    
    // Skip if no title
    if (empty($title)) {
        $skipped++;
        continue;
    }
    
    // Generate slug from title
    $slug = generateSlug($title);
    
    // Check if slug already exists
    $checkStmt = $db->prepare("SELECT id FROM posts WHERE slug = ?");
    $checkStmt->execute([$slug]);
    if ($checkStmt->fetch()) {
        // Append post ID to make it unique
        $slug = $slug . '-' . $postId;
    }
    
    // Get featured image if available
    $featuredImage = null;
    $thumbnailId = null;
    
    // Look for _thumbnail_id in postmeta
    foreach ($item->children('wp', true)->postmeta as $postmeta) {
        $metaKey = (string)$postmeta->children('wp', true)->meta_key;
        if ($metaKey === '_thumbnail_id') {
            $thumbnailId = (int)$postmeta->children('wp', true)->meta_value;
            break;
        }
    }
    
    // If we have a thumbnail ID, try to download and process the image
    if ($thumbnailId && isset($featuredImages[$thumbnailId])) {
        $imageUrl = $featuredImages[$thumbnailId];
        echo "  Found featured image for post '$title': $imageUrl\n";
        
        // Try to download and process the image
        $featuredImage = downloadAndProcessImage($imageUrl, $postId);
    }
    
    // Clean up WordPress-specific HTML
    $content = cleanWordPressContent($content);
    
    // Convert post date to proper format
    $createdAt = date('Y-m-d H:i:s', strtotime($postDate));
    
    try {
        // Insert post
        $stmt = $db->prepare("INSERT INTO posts (title, content, slug, featured_image, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$title, $content, $slug, $featuredImage, $createdAt, $createdAt]);
        
        $imported++;
        echo "  ✓ Imported: $title\n";
        
    } catch (PDOException $e) {
        $errors++;
        echo "  ✗ Error importing '$title': " . $e->getMessage() . "\n";
    }
}

echo "\n";
echo "Import complete!\n";
echo "  Imported: $imported posts\n";
echo "  Skipped: $skipped posts\n";
echo "  Errors: $errors posts\n";

if ($isWeb) {
    $output = ob_get_clean();
    // Convert newlines to HTML breaks and highlight
    $output = htmlspecialchars($output);
    $output = preg_replace('/✓ (.+)/', '<span class="success">✓ $1</span>', $output);
    $output = preg_replace('/✗ (.+)/', '<span class="error">✗ $1</span>', $output);
    $output = preg_replace('/Import complete!/', '<strong class="success">Import complete!</strong>', $output);
    $output = preg_replace('/\n/', '<br>', $output);
    echo $output;
    ?>
                </div>
                <div style="margin-top: 2rem;">
                    <a href="admin/dashboard.php" class="btn">View Dashboard</a>
                    <a href="index.php" class="btn" target="_blank">View Blog</a>
                </div>
            </div>
        </div>
    </body>
    </html>
    <?php
}

/**
 * Clean WordPress content
 * Removes WordPress-specific shortcodes and cleans up HTML
 */
function cleanWordPressContent($content) {
    // Remove WordPress shortcodes (basic cleanup)
    $content = preg_replace('/\[.*?\]/', '', $content);
    
    // Convert WordPress line breaks to HTML
    $content = nl2br($content);
    
    // Clean up extra whitespace
    $content = preg_replace('/\s+/', ' ', $content);
    
    return trim($content);
}

/**
 * Download and process featured image
 * Downloads image from URL and processes it using our upload function
 */
function downloadAndProcessImage($url, $postId) {
    // Skip if URL is not accessible or invalid
    if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
        return null;
    }
    
    // Create temporary file
    $tempFile = tempnam(sys_get_temp_dir(), 'wp_import_');
    
    // Download image
    $ch = curl_init($url);
    $fp = fopen($tempFile, 'wb');
    curl_setopt($ch, CURLOPT_FILE, $fp);
    curl_setopt($ch, CURLOPT_HEADER, 0);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    fclose($fp);
    
    if ($httpCode !== 200 || !file_exists($tempFile) || filesize($tempFile) === 0) {
        @unlink($tempFile);
        return null;
    }
    
    // Create fake $_FILES array for uploadFeaturedImage function
    $fileInfo = [
        'name' => basename(parse_url($url, PHP_URL_PATH)),
        'type' => mime_content_type($tempFile),
        'tmp_name' => $tempFile,
        'error' => UPLOAD_ERR_OK,
        'size' => filesize($tempFile)
    ];
    
    // Process image
    $filename = uploadFeaturedImage($fileInfo, $postId);
    
    // Clean up temp file
    @unlink($tempFile);
    
    return $filename;
}


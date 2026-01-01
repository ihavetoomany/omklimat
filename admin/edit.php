<?php
/**
 * Admin post editor - create and edit posts
 */
require_once '../config.php';
requireLogin(); // Ensure user is logged in

// Generate CSRF token
$csrfToken = generateCSRFToken();

$db = getDB();

$post = null;
$postId = null;
$isEdit = false;

// Check if editing existing post
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $postId = (int)$_GET['id'];
    $stmt = $db->prepare("SELECT * FROM posts WHERE id = ?");
    $stmt->execute([$postId]);
    $post = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($post) {
        $isEdit = true;
    } else {
        header('Location: dashboard.php');
        exit;
    }
}

$error = '';
$success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Ogiltig begäran. Försök igen.';
    } else {
        $title = trim($_POST['title'] ?? '');
        $content = trim($_POST['content'] ?? '');
        $deleteImage = isset($_POST['delete_image']);
        // Determine status based on which button was clicked
        $status = isset($_POST['publish']) ? 'published' : 'draft';
        
        // Validation
        if (empty($title)) {
            $error = 'Titel krävs.';
        } elseif (empty($content)) {
            $error = 'Innehåll krävs.';
        } else {
        // Handle featured image upload
        $featuredImage = null;
        if (isset($_FILES['featured_image']) && $_FILES['featured_image']['error'] === UPLOAD_ERR_OK) {
            // Upload new image
            $featuredImage = uploadFeaturedImage($_FILES['featured_image'], $postId);
            if ($featuredImage === false) {
                $error = 'Bilduppladdning misslyckades. Kontrollera att filen är en giltig bildfil (JPG, PNG, GIF eller WebP).';
            } else {
                // Delete old image if editing
                if ($isEdit && $post && $post['featured_image']) {
                    deleteFeaturedImage($post['featured_image']);
                }
            }
        } elseif ($isEdit && $post) {
            // Keep existing image unless deleted
            if ($deleteImage) {
                if ($post['featured_image']) {
                    deleteFeaturedImage($post['featured_image']);
                }
                $featuredImage = null;
            } else {
                $featuredImage = $post['featured_image'];
            }
        }
        
        if (!isset($error) || empty($error)) {
            // Generate slug from title
            $slug = generateSlug($title);
            
            // Check if slug already exists (for new posts or if slug changed)
            $checkStmt = $db->prepare("SELECT id FROM posts WHERE slug = ? AND id != ?");
            $checkStmt->execute([$slug, $postId ?: 0]);
            if ($checkStmt->fetch()) {
                // If slug exists, append a number
                $originalSlug = $slug;
                $counter = 1;
                do {
                    $slug = $originalSlug . '-' . $counter;
                    $checkStmt->execute([$slug, $postId ?: 0]);
                    $counter++;
                } while ($checkStmt->fetch());
            }
            
            try {
                if ($isEdit) {
                    // Update existing post
                    $stmt = $db->prepare("UPDATE posts SET title = ?, content = ?, slug = ?, featured_image = ?, status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                    $stmt->execute([$title, $content, $slug, $featuredImage, $status, $postId]);
                } else {
                    // Create new post
                    $stmt = $db->prepare("INSERT INTO posts (title, content, slug, featured_image, status) VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute([$title, $content, $slug, $featuredImage, $status]);
                }
                
                $message = $status === 'published' ? 'published=1' : 'saved=1';
                header('Location: dashboard.php?' . $message);
                exit;
            } catch (PDOException $e) {
                $error = 'Fel vid sparande av inlägg: ' . $e->getMessage();
            }
        }
    }
}
}

// Pre-fill form if editing
$titleValue = $post['title'] ?? ($_POST['title'] ?? '');
$contentValue = $post['content'] ?? ($_POST['content'] ?? '');
$currentImage = $post['featured_image'] ?? null;
$currentStatus = $post['status'] ?? 'draft';
?>
<!DOCTYPE html>
<html lang="sv">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $isEdit ? 'Redigera inlägg' : 'Nytt inlägg'; ?> - Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Barlow:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../styles.css">
    <!-- Quill Editor -->
    <link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
    <script src="https://cdn.quilljs.com/1.3.6/quill.js"></script>
    <style>
        .container {
            background: white;
            padding: 2rem;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        .admin-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #eee;
        }
        .btn {
            padding: 0.5rem 1rem;
            text-decoration: none;
            border-radius: 4px;
            display: inline-block;
            font-size: 0.9rem;
        }
        .btn-secondary {
            background: #666;
            color: white;
        }
        .btn-secondary:hover {
            background: #888;
        }
        .form-group {
            margin-bottom: 1.5rem;
        }
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            font-size: 1.1rem;
        }
        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 0.75rem;
            font-size: 1rem;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
            font-family: inherit;
        }
        .form-group input[type="text"] {
            font-size: 1.2rem;
        }
        .form-group input[type="file"] {
            padding: 0.5rem;
            font-size: 1rem;
            cursor: pointer;
        }
        .current-image {
            margin-bottom: 1rem;
        }
        .current-image img {
            display: block;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .form-group textarea {
            min-height: 400px;
            resize: vertical;
            line-height: 1.6;
        }
        /* Quill editor styling */
        #editor {
            min-height: 400px;
            background: white;
        }
        .ql-container {
            font-size: 16px;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
        }
        .ql-editor {
            min-height: 400px;
            line-height: 1.6;
        }
        .btn-primary {
            padding: 0.75rem 2rem;
            font-size: 1rem;
            background: #333;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        .btn-primary:hover {
            background: #555;
        }
        .form-actions {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
        }
        .btn-success {
            padding: 0.75rem 2rem;
            font-size: 1rem;
            background: #2e7d32;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        .btn-success:hover {
            background: #1b5e20;
        }
        .status-indicator {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.85rem;
            font-weight: 600;
            margin-left: 1rem;
        }
        .status-draft {
            background: #ff9800;
            color: white;
        }
        .status-published {
            background: #4caf50;
            color: white;
        }
        .error {
            color: #d32f2f;
            margin-bottom: 1rem;
            padding: 1rem;
            background: #ffebee;
            border-radius: 4px;
        }
        .help-text {
            color: #666;
            font-size: 0.9rem;
            margin-top: 0.5rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="admin-header">
            <h1>
                <?php echo $isEdit ? 'Redigera inlägg' : 'Nytt inlägg'; ?>
                <?php if ($isEdit && isset($currentStatus)): ?>
                    <span class="status-indicator status-<?php echo htmlspecialchars($currentStatus); ?>">
                        <?php echo $currentStatus === 'published' ? 'Publicerad' : 'Utkast'; ?>
                    </span>
                <?php endif; ?>
            </h1>
            <a href="dashboard.php" class="btn btn-secondary">← Tillbaka till adminpanelen</a>
        </div>
        
        <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <form method="POST" action="" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
            <div class="form-group">
                <label for="title">Titel</label>
                <input type="text" 
                       id="title" 
                       name="title" 
                       value="<?php echo htmlspecialchars($titleValue); ?>" 
                       required 
                       autofocus>
            </div>
            
            <div class="form-group">
                <label for="featured_image">Huvudbild</label>
                <?php if ($currentImage): ?>
                    <div class="current-image">
                        <img src="../<?php echo htmlspecialchars(getFeaturedImageUrl($currentImage)); ?>" alt="Nuvarande huvudbild" style="max-width: 100%; height: auto; border-radius: 4px; margin-bottom: 1rem; max-height: 300px;">
                        <label style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 1rem;">
                            <input type="checkbox" name="delete_image" value="1">
                            <span>Radera nuvarande bild</span>
                        </label>
                    </div>
                <?php endif; ?>
                <input type="file" 
                       id="featured_image" 
                       name="featured_image" 
                       accept="image/jpeg,image/jpg,image/png,image/gif,image/webp">
                <p class="help-text">Ladda upp en bild (JPG, PNG, GIF eller WebP). Den kommer automatiskt att ändras till <?php echo FEATURED_IMAGE_WIDTH; ?>x<?php echo FEATURED_IMAGE_HEIGHT; ?>px.</p>
            </div>
            
            <div class="form-group">
                <label for="content">Innehåll</label>
                <!-- Hidden textarea to store HTML content for form submission -->
                <textarea id="content" 
                          name="content" 
                          style="display: none;"><?php echo htmlspecialchars($contentValue); ?></textarea>
                <!-- Quill editor container -->
                <div id="editor"></div>
                <p class="help-text">Använd verktygsraden ovan för att formatera din text. Fet, kursiv, listor, länkar med mera.</p>
            </div>
            
            <script>
                // Initialize Quill editor
                var quill = new Quill('#editor', {
                    theme: 'snow',
                    modules: {
                        toolbar: [
                            [{ 'header': [1, 2, 3, false] }],
                            ['bold', 'italic', 'underline', 'strike'],
                            [{ 'list': 'ordered'}, { 'list': 'bullet' }],
                            [{ 'color': [] }, { 'background': [] }],
                            ['link'],
                            ['clean']
                        ]
                    }
                });
                
                // Set initial content if editing
                <?php if (!empty($contentValue)): ?>
                quill.root.innerHTML = <?php echo json_encode($contentValue); ?>;
                <?php endif; ?>
                
                // Update hidden textarea with HTML content before form submission
                var form = document.querySelector('form');
                form.addEventListener('submit', function(e) {
                    var content = document.querySelector('#content');
                    var htmlContent = quill.root.innerHTML;
                    
                    // Check if content is empty (just whitespace or empty HTML tags)
                    var textContent = quill.getText().trim();
                    if (!textContent || textContent.length === 0) {
                        e.preventDefault();
                        alert('Vänligen ange innehåll för ditt inlägg.');
                        return false;
                    }
                    
                    // Update hidden textarea with HTML content
                    content.value = htmlContent;
                });
            </script>
            
            <div class="form-actions">
                <button type="submit" name="save_draft" class="btn-primary">
                    <?php echo $isEdit ? 'Spara som utkast' : 'Spara som utkast'; ?>
                </button>
                <button type="submit" name="publish" class="btn-success">
                    <?php echo $isEdit ? 'Publicera' : 'Publicera'; ?>
                </button>
                <a href="dashboard.php" class="btn btn-secondary">Avbryt</a>
            </div>
        </form>
    </div>
</body>
</html>


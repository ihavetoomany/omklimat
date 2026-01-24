<?php
/**
 * Configuration file for the blog
 * Edit these settings as needed
 */

// Database configuration
define('DB_PATH', __DIR__ . '/data/blog.db');
define('DATA_DIR', __DIR__ . '/data');
define('UPLOADS_DIR', __DIR__ . '/uploads');

// Featured image settings - adjust size as needed
define('FEATURED_IMAGE_WIDTH', 1200);
define('FEATURED_IMAGE_HEIGHT', 630);
define('FEATURED_IMAGE_QUALITY', 85);

// Admin password - Password: Rosenberg9
// Hash generated with: password_hash('Rosenberg9', PASSWORD_DEFAULT)
define('ADMIN_PASSWORD_HASH', '$2y$10$7lFcbG0cC5z8PmymXJvTqOMRSzl19g6QJ4In6V1Y6Z9ThoqzAMHOS');

// Session configuration - MUST be set before session_start()
ini_set('session.cookie_httponly', 1);
// Only enable secure cookies if HTTPS is being used
if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
    ini_set('session.cookie_secure', 1);
}
ini_set('session.use_strict_mode', 1);
// Use Lax instead of Strict for better compatibility
if (version_compare(PHP_VERSION, '7.3.0', '>=')) {
    ini_set('session.cookie_samesite', 'Lax');
}

// Start session after configuring it
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Regenerate session ID periodically - only for logged-in admin users
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    if (!isset($_SESSION['created'])) {
        $_SESSION['created'] = time();
    } else if (time() - $_SESSION['created'] > 1800) { // 30 minutes
        @session_regenerate_id(true);
        $_SESSION['created'] = time();
    }
    
    // Set session timeout (30 minutes of inactivity)
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 1800)) {
        session_unset();
        session_destroy();
        $isAdminPage = strpos($_SERVER['REQUEST_URI'] ?? '', '/admin/') !== false;
        if ($isAdminPage && !headers_sent()) {
            header('Location: login.php?timeout=1');
            exit;
        }
    }
    $_SESSION['last_activity'] = time();
}

// Ensure data and uploads directories exist
if (!file_exists(DATA_DIR)) {
    mkdir(DATA_DIR, 0755, true);
}
if (!file_exists(UPLOADS_DIR)) {
    mkdir(UPLOADS_DIR, 0755, true);
}

/**
 * Get database connection
 * Creates the database and tables if they don't exist
 */
function getDB() {
    // Set SQLite to use WAL mode for better concurrency and to avoid locks
    $db = new PDO('sqlite:' . DB_PATH);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // Enable WAL mode to prevent database locks
    $db->exec('PRAGMA journal_mode=WAL;');
    
    // Create posts table if it doesn't exist
    $db->exec("CREATE TABLE IF NOT EXISTS posts (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        title TEXT NOT NULL,
        content TEXT NOT NULL,
        slug TEXT UNIQUE NOT NULL,
        featured_image TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    
    // Add featured_image column if it doesn't exist (for existing databases)
    try {
        $db->exec("ALTER TABLE posts ADD COLUMN featured_image TEXT");
    } catch (PDOException $e) {
        // Column already exists, ignore error
    }
    
    // Add status column if it doesn't exist (draft or published)
    $columnAdded = false;
    try {
        $db->exec("ALTER TABLE posts ADD COLUMN status TEXT DEFAULT 'draft'");
        $columnAdded = true;
    } catch (PDOException $e) {
        // Column already exists, ignore error
    }
    
    // Check if migration flag exists
    $migrationDone = false;
    try {
        $checkStmt = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='migrations'");
        if ($checkStmt->fetch()) {
            $migrationStmt = $db->query("SELECT COUNT(*) as count FROM migrations WHERE name = 'set_existing_posts_published'");
            $result = $migrationStmt->fetch(PDO::FETCH_ASSOC);
            $migrationDone = $result && $result['count'] > 0;
        }
    } catch (PDOException $e) {
        // Migrations table doesn't exist yet
    }
    
    // When column is first added, set all existing posts to 'published' (backward compatibility)
    if ($columnAdded) {
        try {
            $db->exec("UPDATE posts SET status = 'published'");
            // Create migrations table and mark this migration as done
            try {
                $db->exec("CREATE TABLE IF NOT EXISTS migrations (name TEXT PRIMARY KEY, executed_at DATETIME DEFAULT CURRENT_TIMESTAMP)");
                $db->exec("INSERT OR IGNORE INTO migrations (name) VALUES ('set_existing_posts_published')");
            } catch (PDOException $e) {
                // Ignore error
            }
        } catch (PDOException $e) {
            // Ignore error
        }
    } else if (!$migrationDone) {
        // Column already existed - update all draft posts to published (one-time migration)
        try {
            $db->exec("UPDATE posts SET status = 'published' WHERE status = 'draft' OR status IS NULL OR status = ''");
            // Create migrations table and mark this migration as done
            try {
                $db->exec("CREATE TABLE IF NOT EXISTS migrations (name TEXT PRIMARY KEY, executed_at DATETIME DEFAULT CURRENT_TIMESTAMP)");
                $db->exec("INSERT OR IGNORE INTO migrations (name) VALUES ('set_existing_posts_published')");
            } catch (PDOException $e) {
                // Ignore error
            }
        } catch (PDOException $e) {
            // Ignore error
        }
    }
    
    // Create index on slug for faster lookups
    $db->exec("CREATE INDEX IF NOT EXISTS idx_slug ON posts(slug)");
    
    // Create visits table for analytics
    $db->exec("CREATE TABLE IF NOT EXISTS visits (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        page TEXT NOT NULL,
        visited_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    
    // Create index on visited_at for faster queries
    $db->exec("CREATE INDEX IF NOT EXISTS idx_visited_at ON visits(visited_at)");
    
    return $db;
}

/**
 * Check if user is logged in
 */
function isLoggedIn() {
    return isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
}

/**
 * Require login - redirect to login page if not authenticated
 */
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit;
    }
}

/**
 * Generate URL-friendly slug from title
 */
function generateSlug($title) {
    // Convert to lowercase
    $slug = strtolower($title);
    // Replace spaces and special characters with hyphens
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
    // Remove leading/trailing hyphens
    $slug = trim($slug, '-');
    return $slug;
}

/**
 * Format date for display - Swedish format with short month names
 */
function formatDate($date) {
    $timestamp = strtotime($date);
    $day = date('j', $timestamp);
    $month = date('n', $timestamp);
    $year = date('Y', $timestamp);
    
    // Swedish short month names
    $months = [
        1 => 'jan', 2 => 'feb', 3 => 'mar', 4 => 'apr',
        5 => 'maj', 6 => 'jun', 7 => 'jul', 8 => 'aug',
        9 => 'sep', 10 => 'okt', 11 => 'nov', 12 => 'dec'
    ];
    
    return $day . ' ' . $months[$month] . ' ' . $year;
}

/**
 * Upload and resize featured image
 * Returns filename on success, false on failure
 */
function uploadFeaturedImage($file, $postId = null) {
    // Check if file was uploaded
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
        return false;
    }
    
    // Validate file type
    $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if (!in_array($mimeType, $allowedTypes)) {
        return false;
    }
    
    // Generate unique filename
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'featured_' . ($postId ? $postId . '_' : '') . time() . '_' . uniqid() . '.' . $extension;
    $filepath = UPLOADS_DIR . '/' . $filename;
    
    // Load image based on type
    switch ($mimeType) {
        case 'image/jpeg':
        case 'image/jpg':
            $source = imagecreatefromjpeg($file['tmp_name']);
            break;
        case 'image/png':
            $source = imagecreatefrompng($file['tmp_name']);
            break;
        case 'image/gif':
            $source = imagecreatefromgif($file['tmp_name']);
            break;
        case 'image/webp':
            $source = imagecreatefromwebp($file['tmp_name']);
            break;
        default:
            return false;
    }
    
    if (!$source) {
        return false;
    }
    
    // Get original dimensions
    $origWidth = imagesx($source);
    $origHeight = imagesy($source);
    
    // Calculate new dimensions (maintain aspect ratio, crop to fit)
    $ratio = max(FEATURED_IMAGE_WIDTH / $origWidth, FEATURED_IMAGE_HEIGHT / $origHeight);
    $newWidth = (int)($origWidth * $ratio);
    $newHeight = (int)($origHeight * $ratio);
    
    // Create new image
    $destination = imagecreatetruecolor(FEATURED_IMAGE_WIDTH, FEATURED_IMAGE_HEIGHT);
    
    // Preserve transparency for PNG/GIF
    if ($mimeType == 'image/png' || $mimeType == 'image/gif') {
        imagealphablending($destination, false);
        imagesavealpha($destination, true);
        $transparent = imagecolorallocatealpha($destination, 255, 255, 255, 127);
        imagefill($destination, 0, 0, $transparent);
    } else {
        // White background for JPEG
        $white = imagecolorallocate($destination, 255, 255, 255);
        imagefill($destination, 0, 0, $white);
    }
    
    // Calculate crop position (center the image)
    $x = (FEATURED_IMAGE_WIDTH - $newWidth) / 2;
    $y = (FEATURED_IMAGE_HEIGHT - $newHeight) / 2;
    
    // Resize and crop
    imagecopyresampled($destination, $source, $x, $y, 0, 0, $newWidth, $newHeight, $origWidth, $origHeight);
    
    // Save image
    $saved = false;
    switch ($mimeType) {
        case 'image/jpeg':
        case 'image/jpg':
            $saved = imagejpeg($destination, $filepath, FEATURED_IMAGE_QUALITY);
            break;
        case 'image/png':
            $saved = imagepng($destination, $filepath, 9);
            break;
        case 'image/gif':
            $saved = imagegif($destination, $filepath);
            break;
        case 'image/webp':
            $saved = imagewebp($destination, $filepath, FEATURED_IMAGE_QUALITY);
            break;
    }
    
    // Clean up
    imagedestroy($source);
    imagedestroy($destination);
    
    if ($saved) {
        return $filename;
    }
    
    return false;
}

/**
 * Upload featured image from base64 data
 */
function uploadFeaturedImageFromBase64($base64Data, $postId = null) {
    if (empty($base64Data)) {
        return false;
    }
    
    // Remove data URL prefix if present
    if (strpos($base64Data, 'data:image') === 0) {
        $base64Data = preg_replace('/^data:image\/\w+;base64,/', '', $base64Data);
    }
    
    // Decode base64 data
    $imageData = base64_decode($base64Data);
    if ($imageData === false) {
        return false;
    }
    
    // Create temporary file
    $tempFile = tempnam(sys_get_temp_dir(), 'upload_');
    if ($tempFile === false) {
        return false;
    }
    
    // Write decoded data to temp file
    file_put_contents($tempFile, $imageData);
    
    // Determine MIME type from image data
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $tempFile);
    finfo_close($finfo);
    
    // Validate MIME type
    $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
    if (!in_array($mimeType, $allowedTypes)) {
        unlink($tempFile);
        return false;
    }
    
    // Determine extension from MIME type
    $extension = 'jpg';
    switch ($mimeType) {
        case 'image/png':
            $extension = 'png';
            break;
        case 'image/gif':
            $extension = 'gif';
            break;
        case 'image/webp':
            $extension = 'webp';
            break;
    }
    
    // Create a file array similar to $_FILES
    $file = [
        'name' => 'cropped_image.' . $extension,
        'type' => $mimeType,
        'tmp_name' => $tempFile,
        'error' => UPLOAD_ERR_OK,
        'size' => filesize($tempFile)
    ];
    
    // Use existing upload function
    $result = uploadFeaturedImage($file, $postId);
    
    // Clean up temp file
    if (file_exists($tempFile)) {
        unlink($tempFile);
    }
    
    return $result;
}

/**
 * Delete featured image file
 */
function deleteFeaturedImage($filename) {
    if ($filename && file_exists(UPLOADS_DIR . '/' . $filename)) {
        unlink(UPLOADS_DIR . '/' . $filename);
    }
}

/**
 * Get URL for featured image
 */
function getFeaturedImageUrl($filename) {
    if (!$filename) {
        return null;
    }
    return 'uploads/' . $filename;
}

/**
 * Log a visit to the analytics system
 * Fails silently to prevent breaking the site if analytics fails
 */
function logVisit($page) {
    try {
        // Validate input
        if (empty($page) || !is_string($page)) {
            return false;
        }
        
        // Use a separate database connection for analytics to avoid locks
        $db = new PDO('sqlite:' . DB_PATH);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $db->exec('PRAGMA journal_mode=WAL;');
        $db->exec('PRAGMA busy_timeout=3000;'); // Wait up to 3 seconds if locked
        
        // Ensure visits table exists
        $db->exec("CREATE TABLE IF NOT EXISTS visits (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            page TEXT NOT NULL,
            visited_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");
        
        $stmt = $db->prepare("INSERT INTO visits (page) VALUES (?)");
        $stmt->execute([$page]);
        
        // Close connection immediately to prevent locks
        $stmt = null;
        $db = null;
        
        return true;
    } catch (Exception $e) {
        // Silently fail - don't break the site if analytics fails
        // Only log if error logging is enabled
        if (ini_get('log_errors')) {
            error_log("Analytics error: " . $e->getMessage());
        }
        return false;
    }
}

/**
 * Get visit statistics
 */
function getVisitStats() {
    $db = getDB();
    $stats = [];
    
    // Total visits
    $stmt = $db->query("SELECT COUNT(*) as total FROM visits");
    $stats['total'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Visits today
    $stmt = $db->query("SELECT COUNT(*) as today FROM visits WHERE DATE(visited_at) = DATE('now')");
    $stats['today'] = $stmt->fetch(PDO::FETCH_ASSOC)['today'];
    
    // Visits this week
    $stmt = $db->query("SELECT COUNT(*) as week FROM visits WHERE visited_at >= DATE('now', '-7 days')");
    $stats['week'] = $stmt->fetch(PDO::FETCH_ASSOC)['week'];
    
    // Visits this month
    $stmt = $db->query("SELECT COUNT(*) as month FROM visits WHERE visited_at >= DATE('now', 'start of month')");
    $stats['month'] = $stmt->fetch(PDO::FETCH_ASSOC)['month'];
    
    return $stats;
}

/**
 * Get top 10 most read articles
 */
function getTopArticles($limit = 10) {
    $db = getDB();
    
    // Extract slug from page field (format: 'post:slug')
    // Count visits per slug and join with posts table
    $stmt = $db->prepare("
        SELECT 
            p.id,
            p.title,
            p.slug,
            COUNT(v.id) as visit_count
        FROM visits v
        INNER JOIN posts p ON v.page = 'post:' || p.slug
        WHERE p.status = 'published'
        GROUP BY p.id, p.title, p.slug
        ORDER BY visit_count DESC
        LIMIT ?
    ");
    $stmt->execute([$limit]);
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Generate CSRF token
 */
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF token
 */
function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token ?? '');
}

/**
 * Error reporting - disable in production
 * Set ENVIRONMENT=development to see errors during development
 */
if (getenv('ENVIRONMENT') !== 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
    ini_set('error_log', __DIR__ . '/data/php_errors.log');
} else {
    // Development mode - show errors
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    ini_set('log_errors', 1);
}


<?php
/**
 * Single post view page
 */
require_once 'config.php';

// Get slug from URL
$slug = $_GET['slug'] ?? '';

if (empty($slug)) {
    header('Location: index.php');
    exit;
}

$db = getDB();

// Get published post by slug
$stmt = $db->prepare("SELECT id, title, content, slug, featured_image, created_at, updated_at FROM posts WHERE slug = ? AND status = 'published'");
$stmt->execute([$slug]);
$post = $stmt->fetch(PDO::FETCH_ASSOC);

// If post not found or not published, redirect to home
if (!$post) {
    header('Location: index.php');
    exit;
}

// Log visit to this post
logVisit('post:' . $slug);
?>
<!DOCTYPE html>
<html lang="sv">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($post['title']); ?> - Blogg</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Barlow:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="styles.css?v=3">
</head>
<body>
    <div class="container">
        <header>
            <div class="header-title-wrapper">
                <h1><?php echo htmlspecialchars($post['title']); ?></h1>
                <a href="admin/login.php" class="admin-sun-button" aria-label="Gå till admin-sidan">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="5"></circle>
                        <line x1="12" y1="1" x2="12" y2="3"></line>
                        <line x1="12" y1="21" x2="12" y2="23"></line>
                        <line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line>
                        <line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line>
                        <line x1="1" y1="12" x2="3" y2="12"></line>
                        <line x1="21" y1="12" x2="23" y2="12"></line>
                        <line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line>
                        <line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line>
                    </svg>
                </a>
            </div>
            <div class="header-back-link">
                <a href="index.php" class="back-link">← Tillbaka till alla inlägg</a>
            </div>
        </header>
        
        <main>
            <article class="single-post">
                <time class="post-date"><?php echo formatDate($post['created_at']); ?></time>
                
                <?php if ($post['featured_image']): ?>
                    <div class="featured-image-container">
                        <img src="<?php echo htmlspecialchars(getFeaturedImageUrl($post['featured_image'])); ?>" 
                             alt="<?php echo htmlspecialchars($post['title']); ?>" 
                             class="featured-image-single">
                    </div>
                <?php endif; ?>
                
                <div class="post-content">
                    <?php 
                    // Display HTML content (from rich text editor)
                    // Allow safe HTML tags, strip dangerous ones for security
                    $allowedTags = '<p><br><strong><b><em><i><u><h1><h2><h3><h4><h5><h6><ul><ol><li><a><blockquote><code><pre><img>';
                    $content = strip_tags($post['content'], $allowedTags);
                    echo $content;
                    ?>
                </div>
                
                <div class="post-footer">
                    <a href="index.php" class="back-link">← Tillbaka till alla inlägg</a>
                </div>
            </article>
        </main>
        
        <footer>
            <p>&copy; <?php echo date('Y'); ?> Blogg</p>
        </footer>
    </div>
</body>
</html>


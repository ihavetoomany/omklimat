<?php
/**
 * Public home page - lists all blog posts
 */
require_once 'config.php';

// Log visit
logVisit('index');

$db = getDB();

// Get all published posts, newest first (but numbered: oldest = 1, newest = highest)
$stmt = $db->query("SELECT id, title, content, slug, featured_image, created_at FROM posts WHERE status = 'published' ORDER BY created_at DESC");
$posts = $stmt->fetchAll(PDO::FETCH_ASSOC);
$totalPosts = count($posts);

// Function to get excerpt (first 200 characters)
function getExcerpt($content, $length = 200) {
    $text = strip_tags($content);
    if (strlen($text) <= $length) {
        return $text;
    }
    return substr($text, 0, $length) . '...';
}
?>
<!DOCTYPE html>
<html lang="sv">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Blogg</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Barlow:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="styles.css?v=3">
</head>
<body>
    <div class="container">
        <header>
            <div class="header-title-wrapper">
                <h1>Om klimat</h1>
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
            <?php if (file_exists('uploads/author.png')): ?>
                <img src="uploads/author.png" alt="Lars Werner" class="author-image" style="max-width: 100%; height: auto; margin-bottom: 1rem; border-radius: 8px;">
            <?php endif; ?>
            <div class="author-intro">
                <p>Bloggen skrivs av <strong>Lars Werner</strong>, meteorolog och klimatexpert med över 50 års erfarenhet. Bloggen har nu varit aktiv i snart 15 år och innehåller över 300 artiklar.<br><a href="om.php">Läs mer om Lars →</a></p>
            </div>
        </header>
        
        <?php if (!empty($posts)): ?>
            <h2 class="articles-subtitle">Alla artiklar:</h2>
        <?php endif; ?>
        
        <main>
            <?php if (empty($posts)): ?>
                <p class="no-posts">Inga inlägg än. Kom tillbaka snart!</p>
            <?php else: ?>
                <div class="posts-list">
                    <?php foreach ($posts as $index => $post): ?>
                        <article class="post-preview">
                            <?php if ($post['featured_image']): ?>
                                <a href="post.php?slug=<?php echo htmlspecialchars($post['slug']); ?>" class="featured-image-link">
                                    <img src="<?php echo htmlspecialchars(getFeaturedImageUrl($post['featured_image'])); ?>" 
                                         alt="<?php echo htmlspecialchars($post['title']); ?>" 
                                         class="featured-image">
                                </a>
                            <?php else: ?>
                                <a href="post.php?slug=<?php echo htmlspecialchars($post['slug']); ?>" class="featured-image-placeholder">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
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
                            <?php endif; ?>
                            <div class="post-preview-content">
                                <h2><a href="post.php?slug=<?php echo htmlspecialchars($post['slug']); ?>">
                                    <?php echo htmlspecialchars($post['title']); ?>
                                </a></h2>
                                <div class="post-date-wrapper">
                                    <span class="article-number"><?php echo $totalPosts - $index; ?></span>
                                    <time class="post-date"><?php echo formatDate($post['created_at']); ?></time>
                                </div>
                                <p class="post-excerpt"><?php echo htmlspecialchars(getExcerpt($post['content'])); ?></p>
                                <a href="post.php?slug=<?php echo htmlspecialchars($post['slug']); ?>" class="read-more">Läs mer →</a>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </main>
        
        <footer>
            <p>&copy; <?php echo date('Y'); ?> Blogg</p>
        </footer>
    </div>
</body>
</html>


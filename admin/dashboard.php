<?php
/**
 * Admin dashboard - list all posts with edit/delete options
 */
require_once '../config.php';
requireLogin(); // Ensure user is logged in

$db = getDB();

// Handle delete action
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    
    // Get post to delete featured image
    $stmt = $db->prepare("SELECT featured_image FROM posts WHERE id = ?");
    $stmt->execute([$id]);
    $post = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Delete the post
    $stmt = $db->prepare("DELETE FROM posts WHERE id = ?");
    $stmt->execute([$id]);
    
    // Delete featured image if it exists
    if ($post && $post['featured_image']) {
        deleteFeaturedImage($post['featured_image']);
    }
    
    header('Location: dashboard.php?deleted=1');
    exit;
}

// Handle publish action
if (isset($_GET['publish']) && is_numeric($_GET['publish'])) {
    $id = (int)$_GET['publish'];
    
    // Update post status to published
    $stmt = $db->prepare("UPDATE posts SET status = 'published', updated_at = CURRENT_TIMESTAMP WHERE id = ?");
    $stmt->execute([$id]);
    
    header('Location: dashboard.php?published=1');
    exit;
}

// Get all posts, newest first
$stmt = $db->query("SELECT id, title, slug, featured_image, status, created_at, updated_at FROM posts ORDER BY created_at DESC");
$posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get visit statistics
$visitStats = getVisitStats();

// Get top 10 most read articles
$topArticles = getTopArticles(10);

    $message = '';
    if (isset($_GET['deleted'])) {
        $message = 'Inlägg raderat.';
    }
    if (isset($_GET['saved'])) {
        $message = 'Inlägg sparat som utkast.';
    }
    if (isset($_GET['published'])) {
        $message = 'Inlägg publicerat.';
    }
?>
<!DOCTYPE html>
<html lang="sv">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Adminpanel - Blogg</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Barlow:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../styles.css">
    <style>
        .admin-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #eee;
        }
        .admin-actions {
            display: flex;
            gap: 1rem;
        }
        .btn {
            padding: 0.5rem 1rem;
            text-decoration: none;
            border-radius: 4px;
            display: inline-block;
            font-size: 0.9rem;
        }
        .btn-primary {
            background: #333;
            color: white;
        }
        .btn-primary:hover {
            background: #555;
        }
        .btn-secondary {
            background: #666;
            color: white;
        }
        .btn-secondary:hover {
            background: #888;
        }
        .btn-danger {
            background: #d32f2f;
            color: white;
        }
        .btn-danger:hover {
            background: #c62828;
        }
        .posts-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }
        .posts-table th,
        .posts-table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        .posts-table th {
            background: #f5f5f5;
            font-weight: 600;
        }
        .posts-table tr:hover {
            background: #f9f9f9;
        }
        .action-buttons {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        .status-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.85rem;
            font-weight: 600;
            margin-left: 0.5rem;
        }
        .status-draft {
            background: #ff9800;
            color: white;
        }
        .status-published {
            background: #4caf50;
            color: white;
        }
        .btn-success {
            background: #2e7d32;
            color: white;
        }
        .btn-success:hover {
            background: #1b5e20;
        }
        .message {
            padding: 1rem;
            background: #e8f5e9;
            color: #2e7d32;
            border-radius: 4px;
            margin-bottom: 1rem;
        }
        .no-posts {
            text-align: center;
            padding: 3rem;
            color: #666;
        }
        .analytics-block {
            background: #f5f5f5;
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1.5rem;
        }
        .analytics-stat {
            text-align: center;
        }
        .analytics-stat-label {
            font-size: 0.85rem;
            color: #666;
            margin-bottom: 0.5rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .analytics-stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: #333;
        }
        .top-articles-block {
            background: white;
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            border: 1px solid #eee;
        }
        .top-articles-block h2 {
            font-size: 1.5rem;
            margin-bottom: 1rem;
            color: #222;
            font-weight: 600;
        }
        .top-articles-list {
            list-style: decimal;
            padding-left: 1.5rem;
            margin: 0;
        }
        .top-articles-list li {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem 0;
            border-bottom: 1px solid #f0f0f0;
        }
        .top-articles-list li:last-child {
            border-bottom: none;
        }
        .top-articles-list li a {
            color: #333;
            text-decoration: none;
            flex: 1;
        }
        .top-articles-list li a:hover {
            color: #555;
            text-decoration: underline;
        }
        .top-articles-count {
            font-weight: 600;
            color: #666;
            font-size: 0.9rem;
            margin-left: 1rem;
        }
        .no-top-articles {
            color: #666;
            font-style: italic;
            padding: 1rem 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="admin-header">
            <h1>Adminpanel</h1>
            <div class="admin-actions">
                <a href="edit.php" class="btn btn-primary">Nytt inlägg</a>
                <a href="../index.php" class="btn btn-secondary" target="_blank">Visa blogg</a>
                <a href="?logout=1" class="btn btn-secondary">Logga ut</a>
            </div>
        </div>
        
        <?php
        // Handle logout
        if (isset($_GET['logout'])) {
            session_destroy();
            header('Location: login.php');
            exit;
        }
        ?>
        
        <?php if ($message): ?>
            <div class="message"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        
        <div class="analytics-block">
            <div class="analytics-stat">
                <div class="analytics-stat-label">Idag</div>
                <div class="analytics-stat-value"><?php echo number_format($visitStats['today']); ?></div>
            </div>
            <div class="analytics-stat">
                <div class="analytics-stat-label">Denna vecka</div>
                <div class="analytics-stat-value"><?php echo number_format($visitStats['week']); ?></div>
            </div>
            <div class="analytics-stat">
                <div class="analytics-stat-label">Denna månad</div>
                <div class="analytics-stat-value"><?php echo number_format($visitStats['month']); ?></div>
            </div>
            <div class="analytics-stat">
                <div class="analytics-stat-label">Totalt</div>
                <div class="analytics-stat-value"><?php echo number_format($visitStats['total']); ?></div>
            </div>
        </div>
        
        <div class="top-articles-block">
            <h2>10 mest lästa artiklar</h2>
            <?php if (empty($topArticles)): ?>
                <div class="no-top-articles">Inga artiklar har besökts än.</div>
            <?php else: ?>
                <ol class="top-articles-list">
                    <?php foreach ($topArticles as $article): ?>
                        <li>
                            <a href="../post.php?slug=<?php echo htmlspecialchars($article['slug']); ?>" target="_blank">
                                <?php echo htmlspecialchars($article['title']); ?>
                            </a>
                            <span class="top-articles-count"><?php echo number_format($article['visit_count']); ?> besök</span>
                        </li>
                    <?php endforeach; ?>
                </ol>
            <?php endif; ?>
        </div>
        
        <?php if (empty($posts)): ?>
            <div class="no-posts">
                <p>Inga inlägg än. <a href="edit.php">Skapa ditt första inlägg</a>!</p>
            </div>
        <?php else: ?>
            <table class="posts-table">
                <thead>
                    <tr>
                        <th>Titel</th>
                        <th>Status</th>
                        <th>Datum</th>
                        <th>Åtgärder</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($posts as $post): ?>
                        <tr>
                            <td>
                                <strong><?php echo htmlspecialchars($post['title']); ?></strong>
                            </td>
                            <td>
                                <span class="status-badge status-<?php echo htmlspecialchars($post['status'] ?? 'draft'); ?>">
                                    <?php echo ($post['status'] ?? 'draft') === 'published' ? 'Publicerad' : 'Utkast'; ?>
                                </span>
                            </td>
                            <td><?php echo formatDate($post['created_at']); ?></td>
                            <td>
                                <div class="action-buttons">
                                    <a href="edit.php?id=<?php echo $post['id']; ?>" class="btn btn-secondary">Redigera</a>
                                    <?php if (($post['status'] ?? 'draft') === 'draft'): ?>
                                        <a href="?publish=<?php echo $post['id']; ?>" 
                                           class="btn btn-success" 
                                           onclick="return confirm('Vill du publicera detta inlägg?');">Publicera</a>
                                    <?php endif; ?>
                                    <a href="?delete=<?php echo $post['id']; ?>" 
                                       class="btn btn-danger" 
                                       onclick="return confirm('Är du säker på att du vill radera detta inlägg?');">Radera</a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</body>
</html>


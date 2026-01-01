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


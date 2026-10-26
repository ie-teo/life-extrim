<?php
session_start();
require_once '../db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

function fetch_all_pages($result) {
    $rows = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $rows[] = $row;
    }
    return $rows;
}

if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $stmt = $db->prepare("DELETE FROM pages WHERE id = :id");
    $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
    $stmt->execute();
    header('Location: pages.php');
    exit;
}

$pages = fetch_all_pages($db->query("SELECT * FROM pages ORDER BY id ASC"));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Pages - Admin Panel</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; }
        body { margin: 0; font-family: 'Roboto', sans-serif; background-color: #f3f4f6; color: #1f2937; font-size: 14px; }
        .admin-header { background-color: #1f2937; color: #ffffff; padding: 15px 30px; display: flex; justify-content: space-between; align-items: center; border-bottom: 3px solid #10b981; }
        .admin-header a { color: #9ca3af; text-decoration: none; font-weight: 600; }
        .admin-header a:hover { color: #ffffff; }
        .admin-container { max-width: 1140px; margin: 30px auto; padding: 0 15px; }
        .admin-card { background: #ffffff; border-radius: 8px; border: 1px solid #e5e7eb; box-shadow: 0 1px 3px rgba(0,0,0,0.05); padding: 25px; }
        .admin-nav { display: flex; gap: 15px; margin-bottom: 25px; border-bottom: 1px solid #e5e7eb; padding-bottom: 15px; }
        .admin-nav a { padding: 8px 14px; background: #f3f4f6; color: #374151; border-radius: 4px; text-decoration: none; font-weight: 600; }
        .admin-nav a.active, .admin-nav a:hover { background: #10b981; color: #ffffff; }
        .btn { padding: 8px 16px; background: #10b981; color: #ffffff; border-radius: 4px; text-decoration: none; font-weight: 600; display: inline-flex; align-items: center; gap: 6px; border: none; cursor: pointer; margin-bottom: 20px; }
        .btn:hover { background: #059669; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #e5e7eb; }
        th { background: #f9fafb; font-weight: 700; color: #374151; }
        .actions a { margin-right: 10px; color: #4b5563; text-decoration: none; font-size: 15px; }
        .actions a.edit:hover { color: #2563eb; }
        .actions a.delete:hover { color: #dc2626; }
    </style>
</head>
<body>

    <header class="admin-header">
        <div style="font-weight: 900; text-transform: uppercase;"><a href="/admin/index.php">life-extrim admin</a></div>
        <div>
            <a href="/" target="_blank" style="margin-right: 15px;"><i class="fa-solid fa-globe"></i> View Site</a>
            <a href="logout.php"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
        </div>
    </header>

    <div class="admin-container">
        <div class="admin-card">
            
            <div class="admin-nav">
                <a href="posts.php"><i class="fa-solid fa-file-lines"></i> Posts</a>
                <a href="categories.php"><i class="fa-solid fa-folder"></i> Categories</a>
                <a href="pages.php" class="active"><i class="fa-solid fa-file"></i> Pages</a>
            </div>

            <a href="page_edit.php" class="btn"><i class="fa-solid fa-plus"></i> Add New Page</a>

            <?php if (empty($pages)): ?>
                <p style="color: #6b7280; padding: 20px 0;">No pages found.</p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Slug</th>
                            <th style="width: 100px; text-align: right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pages as $page): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($page['title']) ?></strong></td>
                                <td style="color: #6b7280;">/<?= htmlspecialchars($page['slug']) ?></td>
                                <td class="actions" style="text-align: right;">
                                    <a href="page_edit.php?id=<?= $page['id'] ?>" class="edit" title="Edit"><i class="fa-solid fa-pen-to-square"></i></a>
                                    <a href="pages.php?delete=<?= $page['id'] ?>" class="delete" title="Delete" onclick="return confirm('Are you sure you want to delete this page?');"><i class="fa-solid fa-trash"></i></a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>

        </div>
    </div>

</body>
</html>
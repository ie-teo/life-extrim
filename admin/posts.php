<?php
session_start();
require_once '../db.php';

// Проверка авторизации администратора
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Вспомогательная функция для получения всех строк
function fetch_all($result) {
    $rows = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $rows[] = $row;
    }
    return $rows;
}

// Обработка удаления поста
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    
    // Получаем путь к картинке, чтобы удалить её с сервера при необходимости
    $stmtImg = $db->prepare("SELECT image FROM posts WHERE id = :id");
    $stmtImg->bindValue(':id', $id, SQLITE3_INTEGER);
    $postImg = $stmtImg->execute()->fetchArray(SQLITE3_ASSOC);
    
    if ($postImg && !empty($postImg['image']) && file_exists('../' . $postImg['image'])) {
        @unlink('../' . $postImg['image']);
    }

    $stmtDel = $db->prepare("DELETE FROM posts WHERE id = :id");
    $stmtDel->bindValue(':id', $id, SQLITE3_INTEGER);
    $stmtDel->execute();
    
    header('Location: posts.php');
    exit;
}

// Настройка пагинации
$per_page = 10;
$current_page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($current_page - 1) * $per_page;

$search = trim($_GET['q'] ?? '');

if (!empty($search)) {
    // 1. Считаем количество найденных постов
    $stmtCount = $db->prepare("SELECT COUNT(*) FROM posts WHERE title LIKE :term1 OR content LIKE :term2");
    $searchTerm = '%' . $search . '%';
    $stmtCount->bindValue(':term1', $searchTerm, SQLITE3_TEXT);
    $stmtCount->bindValue(':term2', $searchTerm, SQLITE3_TEXT);
    $countRes = $stmtCount->execute()->fetchArray(SQLITE3_NUM);
    $total_posts = $countRes[0] ?? 0;

    $total_pages = ceil($total_posts / $per_page);

    // 2. Получаем посты с учетом поиска и пагинации
    $stmt = $db->prepare("SELECT p.*, c.title as category_title FROM posts p LEFT JOIN categories c ON p.category_id = c.id WHERE p.title LIKE :term1 OR p.content LIKE :term2 ORDER BY p.id DESC LIMIT :limit OFFSET :offset");
    $stmt->bindValue(':term1', $searchTerm, SQLITE3_TEXT);
    $stmt->bindValue(':term2', $searchTerm, SQLITE3_TEXT);
    $stmt->bindValue(':limit', $per_page, SQLITE3_INTEGER);
    $stmt->bindValue(':offset', $offset, SQLITE3_INTEGER);
    $posts = fetch_all($stmt->execute());
} else {
    // Все посты с пагинацией
    $countRes = $db->query("SELECT COUNT(*) FROM posts")->fetchArray(SQLITE3_NUM);
    $total_posts = $countRes[0] ?? 0;
    $total_pages = ceil($total_posts / $per_page);

    $stmt = $db->prepare("SELECT p.*, c.title as category_title FROM posts p LEFT JOIN categories c ON p.category_id = c.id ORDER BY p.id DESC LIMIT :limit OFFSET :offset");
    $stmt->bindValue(':limit', $per_page, SQLITE3_INTEGER);
    $stmt->bindValue(':offset', $offset, SQLITE3_INTEGER);
    $posts = fetch_all($stmt->execute());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Posts - Admin Panel</title>
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
        .top-actions { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 15px; }
        .btn { padding: 8px 16px; background: #10b981; color: #ffffff; border-radius: 4px; text-decoration: none; font-weight: 600; display: inline-flex; align-items: center; gap: 6px; border: none; cursor: pointer; }
        .btn:hover { background: #059669; }
        .search-form { display: flex; gap: 8px; }
        .search-form input { padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 4px; font-size: 14px; width: 240px; }
        .search-form button { padding: 8px 14px; background: #374151; color: #fff; border: none; border-radius: 4px; cursor: pointer; }
        .search-form a { padding: 8px 12px; background: #9ca3af; color: #fff; border-radius: 4px; text-decoration: none; display: flex; align-items: center; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #e5e7eb; }
        th { background: #f9fafb; font-weight: 700; color: #374151; }
        td img { width: 50px; height: 40px; object-fit: cover; border-radius: 4px; border: 1px solid #e5e7eb; }
        .badge { padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: 700; text-transform: uppercase; }
        .badge-published { background: #ecfdf5; color: #059669; }
        .badge-draft { background: #fef3c7; color: #d97706; }
        .actions a { margin-right: 10px; color: #4b5563; text-decoration: none; font-size: 15px; }
        .actions a.edit:hover { color: #2563eb; }
        .actions a.delete:hover { color: #dc2626; }
        .pagination { display: flex; gap: 6px; list-style: none; padding: 0; margin-top: 25px; }
        .pagination li a, .pagination li span { display: block; padding: 8px 14px; background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 4px; color: #1f2937; text-decoration: none; font-weight: 700; font-size: 13px; }
        .pagination li.active span { background: #10b981; color: #fff; border-color: #10b981; }
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
                <a href="posts.php" class="active"><i class="fa-solid fa-file-lines"></i> Posts</a>
                <a href="categories.php"><i class="fa-solid fa-folder"></i> Categories</a>
                <a href="pages.php"><i class="fa-solid fa-file"></i> Pages</a>
            </div>

            <div class="top-actions">
                <a href="post_edit.php" class="btn"><i class="fa-solid fa-plus"></i> Add New Post</a>

                <form action="" method="GET" class="search-form">
                    <input type="text" name="q" placeholder="Search posts..." value="<?= htmlspecialchars($search) ?>" required>
                    <button type="submit"><i class="fa-solid fa-magnifying-glass"></i></button>
                    <?php if (!empty($search)): ?>
                        <a href="posts.php" title="Reset search"><i class="fa-solid fa-rotate-left"></i></a>
                    <?php endif; ?>
                </form>
            </div>

            <?php if (empty($posts)): ?>
                <p style="color: #6b7280; padding: 20px 0;">No posts found.</p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th style="width: 70px;">Image</th>
                            <th>Title</th>
                            <th>Category</th>
                            <th>Status</th>
                            <th style="width: 100px; text-align: right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($posts as $post): ?>
                            <tr>
                                <td>
                                    <?php if (!empty($post['image'])): ?>
                                        <img src="/<?= htmlspecialchars($post['image']) ?>" alt="">
                                    <?php else: ?>
                                        <div style="width: 50px; height: 40px; background: #e5e7eb; border-radius: 4px; display: flex; align-items: center; justify-content: center; color: #9ca3af; font-size: 12px;">No</div>
                                    <?php endif; ?>
                                </td>
                                <td><strong><?= htmlspecialchars($post['title']) ?></strong></td>
                                <td><?= htmlspecialchars($post['category_title'] ?? 'Uncategorized') ?></td>
                                <td>
                                    <?php if ($post['is_published']): ?>
                                        <span class="badge badge-published">Published</span>
                                    <?php else: ?>
                                        <span class="badge badge-draft">Draft</span>
                                    <?php endif; ?>
                                </td>
                                <td class="actions" style="text-align: right;">
                                    <a href="post_edit.php?id=<?= $post['id'] ?>" class="edit" title="Edit"><i class="fa-solid fa-pen-to-square"></i></a>
                                    <a href="posts.php?delete=<?= $post['id'] ?>" class="delete" title="Delete" onclick="return confirm('Are you sure you want to delete this post?');"><i class="fa-solid fa-trash"></i></a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <?php if ($total_pages > 1): ?>
                    <ul class="pagination">
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <li class="<?= ($current_page == $i) ? 'active' : '' ?>">
                                <?php if ($current_page == $i): ?>
                                    <span><?= $i ?></span>
                                <?php else: ?>
                                    <a href="?<?= !empty($search) ? 'q=' . urlencode($search) . '&' : '' ?>page=<?= $i ?>"><?= $i ?></a>
                                <?php endif; ?>
                            </li>
                        <?php endfor; ?>
                    </ul>
                <?php endif; ?>

            <?php endif; ?>

        </div>
    </div>

</body>
</html>
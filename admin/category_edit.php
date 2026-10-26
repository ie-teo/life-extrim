<?php
session_start();
require_once '../db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$category = ['title' => '', 'slug' => '', 'sort_order' => 0];
$error = '';

if ($id > 0) {
    $stmt = $db->prepare("SELECT * FROM categories WHERE id = :id");
    $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
    $category = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
    if (!$category) {
        header('Location: categories.php');
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title']);
    $slug = trim($_POST['slug']);
    $sort_order = (int)$_POST['sort_order'];

    if (empty($slug)) {
        // Simple slugify fallback if empty
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $title), '-'));
    }

    if (empty($title)) {
        $error = 'Title is required.';
    } else {
        if ($id > 0) {
            $stmt = $db->prepare("UPDATE categories SET title = :title, slug = :slug, sort_order = :sort WHERE id = :id");
            $stmt->bindValue(':title', $title, SQLITE3_TEXT);
            $stmt->bindValue(':slug', $slug, SQLITE3_TEXT);
            $stmt->bindValue(':sort', $sort_order, SQLITE3_INTEGER);
            $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
            $stmt->execute();
        } else {
            $stmt = $db->prepare("INSERT INTO categories (title, slug, sort_order) VALUES (:title, :slug, :sort)");
            $stmt->bindValue(':title', $title, SQLITE3_TEXT);
            $stmt->bindValue(':slug', $slug, SQLITE3_TEXT);
            $stmt->bindValue(':sort', $sort_order, SQLITE3_INTEGER);
            $stmt->execute();
        }
        header('Location: categories.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $id > 0 ? 'Edit Category' : 'Add Category' ?> - Admin Panel</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: 'Roboto', sans-serif;
            background-color: #f3f4f6;
            color: #1f2937;
            font-size: 14px;
        }
        .admin-header {
            background-color: #1f2937;
            color: #ffffff;
            padding: 15px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 3px solid #10b981;
        }
        .admin-header a { color: #9ca3af; text-decoration: none; font-weight: 600; }
        .admin-header a:hover { color: #ffffff; }
        .admin-container { max-width: 700px; margin: 30px auto; padding: 0 15px; }
        .admin-card { background: #ffffff; border-radius: 8px; border: 1px solid #e5e7eb; box-shadow: 0 1px 3px rgba(0,0,0,0.05); padding: 25px; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; font-weight: 700; margin-bottom: 8px; color: #374151; }
        .form-group input { width: 100%; padding: 10px 12px; border: 1px solid #d1d5db; border-radius: 4px; font-size: 14px; }
        .btn { padding: 10px 20px; background: #10b981; color: #ffffff; border-radius: 4px; text-decoration: none; font-weight: 600; border: none; cursor: pointer; }
        .btn:hover { background: #059669; }
        .btn-secondary { background: #6b7280; margin-left: 10px; }
        .btn-secondary:hover { background: #4b5563; }
        .error { background: #fee2e2; color: #dc2626; padding: 10px; border-radius: 4px; margin-bottom: 20px; }
    </style>
</head>
<body>

    <header class="admin-header">
        <div style="font-weight: 900; text-transform: uppercase;"><a href="/admin/index.php">life-extrim admin</a></div>
        <div>
            <a href="categories.php"><i class="fa-solid fa-arrow-left"></i> Back to Categories</a>
        </div>
    </header>

    <div class="admin-container">
        <div class="admin-card">
            <h2 style="margin-top: 0; margin-bottom: 20px; font-size: 20px;"><?= $id > 0 ? 'Edit Category' : 'Add New Category' ?></h2>

            <?php if (!empty($error)): ?>
                <div class="error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form action="" method="POST">
                <div class="form-group">
                    <label>Title</label>
                    <input type="text" name="title" value="<?= htmlspecialchars($category['title']) ?>" required>
                </div>
                <div class="form-group">
                    <label>Slug (URL)</label>
                    <input type="text" name="slug" value="<?= htmlspecialchars($category['slug']) ?>" placeholder="e.g. calisthenics">
                </div>
                <div class="form-group">
                    <label>Sort Order</label>
                    <input type="number" name="sort_order" value="<?= (int)$category['sort_order'] ?>">
                </div>
                <button type="submit" class="btn"><?= $id > 0 ? 'Save Changes' : 'Create Category' ?></button>
                <a href="categories.php" class="btn btn-secondary" style="display: inline-block;">Cancel</a>
            </form>
        </div>
    </div>

</body>
</html>
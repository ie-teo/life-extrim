<?php
session_start();
require_once '../db.php';

// Авторизация администратора (по аналогии с posts.php)
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $stmt = $db->prepare("SELECT * FROM users WHERE username = :username");
    $stmt->bindValue(':username', trim($_POST['username']), SQLITE3_TEXT);
    $user = $stmt->execute()->fetchArray(SQLITE3_ASSOC);

    if ($user && password_verify(trim($_POST['password']), $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['admin'] = $user['username'];
        header('Location: index.php');
        exit;
    } else {
        $error = 'Неверный логин или пароль';
    }
}

if (!isset($_SESSION['user_id']) && !isset($_SESSION['admin'])) {
    // Форма входа в том же стиле
    ?>
    <!DOCTYPE html>
    <html lang="ru">
    <head>
        <meta charset="UTF-8">
        <title>Вход в админку</title>
        <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
        <style>
            body { font-family: 'Roboto', sans-serif; background-color: #f3f4f6; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; color: #1f2937; }
            .login-box { background: #fff; padding: 30px; border-radius: 8px; border: 1px solid #e5e7eb; box-shadow: 0 1px 3px rgba(0,0,0,0.05); width: 320px; }
            .login-box h2 { margin-top: 0; font-size: 20px; margin-bottom: 20px; }
            input { width: 100%; padding: 10px; margin-bottom: 15px; box-sizing: border-box; border: 1px solid #d1d5db; border-radius: 4px; font-size: 14px; }
            button { width: 100%; padding: 10px; background: #10b981; color: #fff; border: none; border-radius: 4px; cursor: pointer; font-weight: bold; font-size: 14px; }
            button:hover { background: #059669; }
            .error { color: #dc2626; font-size: 13px; margin-bottom: 15px; background: #fee2e2; padding: 8px; border-radius: 4px; }
        </style>
    </head>
    <body>
        <div class="login-box">
            <h2>🔒 Admin Login</h2>
            <?php if($error): ?><div class="error"><?= $error ?></div><?php endif; ?>
            <form method="POST">
                <input type="text" name="username" placeholder="Username" required>
                <input type="password" name="password" placeholder="Password" required>
                <button type="submit" name="login">Войти</button>
            </form>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// Вспомогательная функция для получения всех строк


function fetch_all_posts($result) {
    $rows = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $rows[] = $row;
    }
    return $rows;
}

// Удаление поста
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    
    // Удаляем картинку, если она есть
    $stmtImg = $db->prepare("SELECT image FROM posts WHERE id = :id");
    $stmtImg->bindValue(':id', $id, SQLITE3_INTEGER);
    $postImg = $stmtImg->execute()->fetchArray(SQLITE3_ASSOC);
    
    if ($postImg && !empty($postImg['image']) && file_exists('../' . $postImg['image'])) {
        @unlink('../' . $postImg['image']);
    }

    $stmtDel = $db->prepare("DELETE FROM posts WHERE id = :id");
    $stmtDel->bindValue(':id', $id, SQLITE3_INTEGER);
    $stmtDel->execute();
    
    header('Location: index.php');
    exit;
}

$posts = fetch_all_posts($db->query("SELECT p.*, c.title as category_title FROM posts p LEFT JOIN categories c ON p.category_id = c.id ORDER BY p.id DESC"));
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
            
            <!-- Навигация по админке -->
            <div class="admin-nav">
                <a href="posts.php"><i class="fa-solid fa-file-lines"></i> Posts</a>
                <a href="categories.php"><i class="fa-solid fa-folder"></i> Categories</a>
                <a href="pages.php"><i class="fa-solid fa-file"></i> Pages</a>
            </div>

            

            

        </div>
    </div>

</body>
</html>
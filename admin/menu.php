<?php
session_start();
require_once '../db.php';
if (!isset($_SESSION['admin'])) { header('Location: index.php'); exit; }

// Добавление пункта меню
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_menu'])) {
    $title = trim($_POST['title']);
    $url = trim($_POST['url']);
    $sort = (int)$_POST['sort_order'];

    $stmt = $pdo->prepare("INSERT INTO menu (title, url, sort_order) VALUES (?, ?, ?)");
    $stmt->execute([$title, $url, $sort]);
    header('Location: menu.php');
    exit;
}

// Удаление пункта меню
if (isset($_GET['delete'])) {
    $stmt = $pdo->prepare("DELETE FROM menu WHERE id = ?");
    $stmt->execute([$_GET['delete']]);
    header('Location: menu.php');
    exit;
}

$menu_items = $pdo->query("SELECT * FROM menu ORDER BY sort_order ASC")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Управление меню</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body class="admin-body">
    <div class="admin-wrapper">
        <aside class="admin-sidebar">
            <h3>Админка</h3>
            <ul>
                <li><a href="index.php">Посты</a></li>
                <li><a href="menu.php">Управление меню</a></li>
                <li><a href="logout.php">Выйти</a></li>
            </ul>
        </aside>
        <main class="admin-content">
            <h2>Управление меню</h2>
            
            <form method="POST" style="margin-bottom: 30px; background: #fff; padding: 15px; border-radius: 5px;">
                <h4>Добавить пункт меню</h4>
                <input type="text" name="title" placeholder="Название (например: Блог)" required style="padding:6px;">
                <input type="text" name="url" placeholder="Ссылка (например: index.php)" required style="padding:6px;">
                <input type="number" name="sort_order" value="0" placeholder="Порядок" style="width:70px; padding:6px;">
                <button type="submit" name="add_menu" class="btn">Добавить</button>
            </form>

            <table>
                <tr>
                    <th>Порядок</th>
                    <th>Название</th>
                    <th>Ссылка</th>
                    <th>Действия</th>
                </tr>
                <?php foreach ($menu_items as $item): ?>
                <tr>
                    <td><?= $item['sort_order'] ?></td>
                    <td><?= htmlspecialchars($item['title']) ?></td>
                    <td><?= htmlspecialchars($item['url']) ?></td>
                    <td><a href="menu.php?delete=<?= $item['id'] ?>" onclick="return confirm('Удалить?')">Удалить</a></td>
                </tr>
                <?php endforeach; ?>
            </table>
        </main>
    </div>
</body>
</html>
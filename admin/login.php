<?php
session_start();
require_once '../db.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    $stmt = $db->prepare("SELECT * FROM users WHERE username = :username");
    $stmt->bindValue(':username', $username, SQLITE3_TEXT);
    $user = $stmt->execute()->fetchArray(SQLITE3_ASSOC);

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['admin'] = $user['username'];
        header('Location: posts.php');
        exit;
    } else {
        $error = 'Incorrect username or password';
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Login to the Admin Panel</title>
    <style>
        body { font-family: sans-serif; background: #f1f5f9; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .login-box { background: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); width: 320px; }
        input { width: 100%; padding: 10px; margin-bottom: 15px; box-sizing: border-box; border: 1px solid #cbd5e1; border-radius: 4px; }
        button { width: 100%; padding: 10px; background: #0284c7; color: #fff; border: none; border-radius: 4px; cursor: pointer; font-weight: bold; }
        .error { color: #ef4444; font-size: 13px; margin-bottom: 15px; }
    </style>
</head>
<body>
    <div class="login-box">
        <h2 style="margin-top: 0;">Login to the Admin Panel</h2>
        <?php if ($error): ?><div class="error"><?= $error ?></div><?php endif; ?>
        <form method="POST">
            <input type="text" name="username" required>
            <input type="password" name="password" required>
            <button type="submit">Log In</button>
        </form>
    </div>
</body>
</html>
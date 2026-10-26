<?php
$db_file = __DIR__ . '/database.sqlite';
$db_exists = file_exists($db_file);

try {
    $pdo = new PDO('sqlite:' . $db_file);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    if (!$db_exists) {
        // Таблица постов (для блога)
        $pdo->exec("CREATE TABLE posts (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            title TEXT NOT NULL,
            slug TEXT NOT NULL UNIQUE,
            content TEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");

        // Таблица страниц (для меню и статических разделов)
        $pdo->exec("CREATE TABLE pages (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            title TEXT NOT NULL,
            slug TEXT NOT NULL UNIQUE,
            content TEXT NOT NULL,
            show_in_menu INTEGER DEFAULT 1,
            sort_order INTEGER DEFAULT 0
        )");

        // Таблица пользователей
        $pdo->exec("CREATE TABLE users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT NOT NULL UNIQUE,
            password TEXT NOT NULL
        )");

        // Дефолтный админ: admin / admin123
        $password_hash = password_hash('admin123', PASSWORD_DEFAULT);
        $pdo->exec("INSERT INTO users (username, password) VALUES ('admin', '$password_hash')");

        // Создаем стартовые страницы, которые сразу появятся в меню
        $pdo->exec("INSERT INTO pages (title, slug, content, show_in_menu, sort_order) VALUES ('Главная', 'home', 'Добро пожаловать на наш сайт!', 1, 1)");
        $pdo->exec("INSERT INTO pages (title, slug, content, show_in_menu, sort_order) VALUES ('О нас', 'about', 'Здесь находится информация о нашем проекте.', 1, 2)");
        $pdo->exec("INSERT INTO pages (title, slug, content, show_in_menu, sort_order) VALUES ('Контакты', 'contacts', 'Свяжитесь с нами: email@example.com', 1, 3)");
    }
} catch (PDOException $e) {
    die("Ошибка базы данных: " . $e->getMessage());
}
?>
<?php
$db_file = __DIR__ . '/database.sqlite';
$db_exists = file_exists($db_file);

try {
    // Используем встроенный класс SQLite3
    $db = new SQLite3($db_file);
    $db->enableExceptions(true);

    if (!$db_exists) {
        // 1. Таблица статических страниц
        $db->exec("CREATE TABLE IF NOT EXISTS pages (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            title TEXT NOT NULL,
            slug TEXT NOT NULL UNIQUE,
            content TEXT NOT NULL
        )");

        // 2. Таблица рубрик
        $db->exec("CREATE TABLE IF NOT EXISTS categories (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            title TEXT NOT NULL,
            slug TEXT NOT NULL UNIQUE,
            sort_order INTEGER DEFAULT 0
        )");

        // 3. Таблица постов
        $db->exec("CREATE TABLE IF NOT EXISTS posts (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            category_id INTEGER,
            title TEXT NOT NULL,
            slug TEXT NOT NULL UNIQUE,
            excerpt TEXT,
            content TEXT NOT NULL,
            image TEXT DEFAULT '',
            is_published INTEGER DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
        )");

        // 4. Таблица пользователей
        $db->exec("CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT NOT NULL UNIQUE,
            password TEXT NOT NULL
        )");

        // Создаем админа по умолчанию (admin / admin123)
        $password_hash = password_hash('admin123', PASSWORD_DEFAULT);
        $stmt = $db->prepare("INSERT INTO users (username, password) VALUES ('admin', :pass)");
        $stmt->bindValue(':pass', $password_hash, SQLITE3_TEXT);
        $stmt->execute();

        // Дефолтные страницы
        $db->exec("INSERT INTO pages (title, slug, content) VALUES ('Home', 'home', '<h1>Welcome to life-extrim</h1><p>Your ultimate strength and fitness blueprint. Select a category from the sidebar to explore training programs and articles.</p>')");
        $db->exec("INSERT INTO pages (title, slug, content) VALUES ('About Us', 'about', '<h1>About life-extrim</h1><p>We are dedicated to raw power, science-backed hypertrophy, and functional fitness.</p>')");
        $db->exec("INSERT INTO pages (title, slug, content) VALUES ('Contacts', 'contacts', '<h1>Contacts</h1><p>Email: support@life-extrim.test<br>Phone: 8 (800) 850-50-50</p>')");

        // Дефолтные рубрики
        $db->exec("INSERT INTO categories (title, slug, sort_order) VALUES ('Bodyweight', 'bodyweight', 1)");
        $db->exec("INSERT INTO categories (title, slug, sort_order) VALUES ('Calisthenics', 'calisthenics', 2)");
        $db->exec("INSERT INTO categories (title, slug, sort_order) VALUES ('Gym Workouts', 'gym-workouts', 3)");
        $db->exec("INSERT INTO categories (title, slug, sort_order) VALUES ('Health & Mobility', 'health-mobility', 4)");
        $db->exec("INSERT INTO categories (title, slug, sort_order) VALUES ('Kettlebell Training', 'kettlebell', 5)");
    }
} catch (Exception $e) {
    die("Ошибка БД: " . $e->getMessage());
}
?>
<?php
session_start();
require_once '../db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$page = ['title' => '', 'slug' => '', 'content' => ''];
$error = '';

if ($id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM pages WHERE id = ?");
    $stmt->execute([$id]);
    $page = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$page) {
        header('Location: pages.php');
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title']);
    $slug = trim($_POST['slug']);
    $content = $_POST['content'] ?? '';

    if (empty($slug)) {
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $title), '-'));
    }

    if (empty($title)) {
        $error = 'Title is required.';
    } else {
        if ($id > 0) {
            $stmt = $pdo->prepare("UPDATE pages SET title = ?, slug = ?, content = ? WHERE id = ?");
            $stmt->execute([$title, $slug, $content, $id]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO pages (title, slug, content) VALUES (?, ?, ?)");
            $stmt->execute([$title, $slug, $content]);
        }
        header('Location: pages.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $id > 0 ? 'Edit Page' : 'Add Page' ?> - Admin Panel</title>
    
    <!-- Иконки и шрифты -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    
    <!-- 1. СТРОГО СНАЧАЛА jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    <!-- 2. Bootstrap (требуется для корректной отрисовки кнопок Summernote) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@3.4.1/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@3.4.1/dist/js/bootstrap.min.js"></script>

    <!-- 3. Summernote CSS & JS -->
    <link href="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote.min.js"></script>

    <style>
        /* Изоляция стилей Bootstrap, чтобы он не ломал дизайн админки */
        .admin-container { max-width: 900px; margin: 30px auto; padding: 0 15px; box-sizing: border-box; }
        .admin-card { background: #ffffff; border-radius: 8px; border: 1px solid #e5e7eb; box-shadow: 0 1px 3px rgba(0,0,0,0.05); padding: 25px; }
        
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
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; font-weight: 700; margin-bottom: 8px; color: #374151; }
        .form-group input { width: 100%; padding: 10px 12px; border: 1px solid #d1d5db; border-radius: 4px; font-size: 14px; }
        .btn-custom { padding: 10px 20px; background: #10b981; color: #ffffff; border-radius: 4px; text-decoration: none; font-weight: 600; border: none; cursor: pointer; display: inline-block; }
        .btn-custom:hover { background: #059669; color: #fff; }
        .btn-secondary-custom { background: #6b7280; margin-left: 10px; color: #fff; }
        .btn-secondary-custom:hover { background: #4b5563; color: #fff; }
        .error { background: #fee2e2; color: #dc2626; padding: 10px; border-radius: 4px; margin-bottom: 20px; }
        
        /* Исправление отображения панели Summernote с Bootstrap */
        .note-editor.note-frame { border: 1px solid #d1d5db; border-radius: 4px; box-shadow: none; }
        .note-toolbar { background: #f9fafb !important; border-bottom: 1px solid #e5e7eb !important; }
    </style>
</head>
<body>

    <header class="admin-header">
        <div style="font-weight: 900; text-transform: uppercase;">life-extrim admin</div>
        <div>
            <a href="pages.php"><i class="fa-solid fa-arrow-left"></i> Back to Pages</a>
        </div>
    </header>

    <div class="admin-container">
        <div class="admin-card">
            <h2 style="margin-top: 0; margin-bottom: 20px; font-size: 20px;"><?= $id > 0 ? 'Edit Page' : 'Add New Page' ?></h2>

            <?php if (!empty($error)): ?>
                <div class="error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form action="" method="POST">
                <div class="form-group">
                    <label>Title</label>
                    <input type="text" name="title" value="<?= htmlspecialchars($page['title']) ?>" required>
                </div>
                <div class="form-group">
                    <label>Slug (URL)</label>
                    <input type="text" name="slug" value="<?= htmlspecialchars($page['slug']) ?>" placeholder="e.g. about-us">
                </div>
                <div class="form-group">
                    <label>Content</label>
                    <textarea id="summernote" name="content"><?= htmlspecialchars($page['content']) ?></textarea>
                </div>
                <button type="submit" class="btn-custom"><?= $id > 0 ? 'Save Changes' : 'Create Page' ?></button>
                <a href="pages.php" class="btn-custom btn-secondary-custom">Cancel</a>
            </form>
        </div>
    </div>

    <!-- Гарантированная инициализация редактора -->
    <script>
        $(document).ready(function() {
            $('#summernote').summernote({
                height: 400,
                placeholder: 'Write page content here...',
                toolbar: [
                    ['style', ['style']],
                    ['font', ['bold', 'underline', 'clear']],
                    ['color', ['color']],
                    ['para', ['ul', 'ol', 'paragraph']],
                    ['table', ['table']],
                    ['insert', ['link', 'picture']],
                    ['view', ['codeview', 'help']]
                ]
            });
        });
    </script>
</body>
</html>
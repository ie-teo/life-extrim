<?php
session_start();
require_once '../db.php';
if (!isset($_SESSION['admin'])) { header('Location: index.php'); exit; }

$id = $_GET['id'] ?? null;
$title = '';
$slug = '';
$content = '';
$image = '';
$is_published = 1;
$sort_order = 0;

if ($id) {
    $stmt = $db->prepare("SELECT * FROM posts WHERE id = :id");
    $stmt->bindValue(':id', (int)$id, SQLITE3_INTEGER);
    $art = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
    if ($art) {
        $title = $art['title'];
        $slug = $art['slug'];
        $content = $art['content'];
        $image = $art['image'];
        $is_published = $art['is_published'] ?? 1;
        $sort_order = $art['sort_order'] ?? 0;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title']);
    $slug = trim($_POST['slug']);
    if (empty($slug)) {
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $title), '-'));
    }
    $content = $_POST['content']; 
    $is_published = isset($_POST['is_published']) ? 1 : 0;
    $sort_order = (int)$_POST['sort_order'];

    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = __DIR__ . '/../uploads/';
        if (!is_dir($upload_dir)) { mkdir($upload_dir, 0777, true); }
        $file_ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
        $file_name = 'img_' . time() . '.' . $file_ext;
        move_uploaded_file($_FILES['image']['tmp_name'], $upload_dir . $file_name);
        $image = 'uploads/' . $file_name;
    }

    if ($id) {
        $stmt = $db->prepare("UPDATE posts SET title = :title, slug = :slug, content = :content, image = :image, is_published = :pub, sort_order = :sort WHERE id = :id");
        $stmt->bindValue(':title', $title, SQLITE3_TEXT);
        $stmt->bindValue(':slug', $slug, SQLITE3_TEXT);
        $stmt->bindValue(':content', $content, SQLITE3_TEXT);
        $stmt->bindValue(':image', $image, SQLITE3_TEXT);
        $stmt->bindValue(':pub', $is_published, SQLITE3_INTEGER);
        $stmt->bindValue(':sort', $sort_order, SQLITE3_INTEGER);
        $stmt->bindValue(':id', (int)$id, SQLITE3_INTEGER);
        $stmt->execute();
    } else {
        $stmt = $db->prepare("INSERT INTO posts (title, slug, content, image, is_published, sort_order) VALUES (:title, :slug, :content, :image, :pub, :sort)");
        $stmt->bindValue(':title', $title, SQLITE3_TEXT);
        $stmt->bindValue(':slug', $slug, SQLITE3_TEXT);
        $stmt->bindValue(':content', $content, SQLITE3_TEXT);
        $stmt->bindValue(':image', $image, SQLITE3_TEXT);
        $stmt->bindValue(':pub', $is_published, SQLITE3_INTEGER);
        $stmt->bindValue(':sort', $sort_order, SQLITE3_INTEGER);
        $stmt->execute();
    }
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Редактирование статьи</title>
    <link rel="stylesheet" href="../css/style.css">
    <link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
    <script src="https://cdn.quilljs.com/1.3.6/quill.min.js"></script>
    <style>
        .ql-editor { min-height: 250px; font-size: 15px; }
        #html-textarea {
            width: 100%; min-height: 250px; font-family: monospace; font-size: 14px;
            padding: 12px; border: 1px solid #ccc; border-radius: 4px; display: none;
            box-sizing: border-box; background: #1e293b; color: #f8fafc;
        }
        .code-toggle-btn {
            background: #e2e8f0; border: none; padding: 6px 12px; border-radius: 4px;
            cursor: pointer; font-weight: bold; font-size: 12px; margin-bottom: 10px;
        }
        .code-toggle-btn:hover { background: #cbd5e1; }
    </style>
</head>
<body style="background:#f8fafc; padding: 30px;">
    <div class="wrapper" style="max-width: 850px; background:#fff; padding:30px; border-radius:8px; box-shadow:0 1px 3px rgba(0,0,0,0.1);">
        <h2><?= $id ? 'Редактировать статью' : 'Новая статья' ?></h2>
        
        <form method="POST" enctype="multipart/form-data" id="articleForm">
            <label><strong>Заголовок:</strong></label><br>
            <input type="text" name="title" value="<?= htmlspecialchars($title) ?>" required style="width:100%; padding:10px; margin-top:5px; margin-bottom:15px; border:1px solid #cbd5e1; border-radius:4px;"><br>

            <label><strong>Slug (URL-адрес):</strong></label><br>
            <input type="text" name="slug" value="<?= htmlspecialchars($slug) ?>" style="width:100%; padding:10px; margin-top:5px; margin-bottom:15px; border:1px solid #cbd5e1; border-radius:4px;"><br>

            <label><strong>Порядок сортировки:</strong></label><br>
            <input type="number" name="sort_order" value="<?= $sort_order ?>" style="width:100px; padding:8px; margin-top:5px; margin-bottom:15px; border:1px solid #cbd5e1; border-radius:4px;"><br>

            <div style="margin-bottom: 15px;">
                <label><input type="checkbox" name="is_published" value="1" <?= $is_published ? 'checked' : '' ?>> Опубликовать пост</label>
            </div>

            <label><strong>Главное изображение:</strong></label><br>
            <?php if($image): ?>
                <div style="margin: 10px 0;"><img src="../<?= $image ?>" width="150" style="border-radius:4px;"></div>
            <?php endif; ?>
            <input type="file" name="image" accept="image/*" style="margin-top:5px; margin-bottom:20px;"><br>

            <div style="display: flex; justify-content: space-between; align-items: center;">
                <label><strong>Содержимое:</strong></label>
                <button type="button" id="toggleCodeBtn" class="code-toggle-btn">&lt;/&gt; Исходный код (HTML)</button>
            </div>

            <div id="editor-container" style="background:#fff; margin-top:5px; margin-bottom:20px; border-radius:4px;"><?= $content ?></div>
            <textarea id="html-textarea" name="content"><?= htmlspecialchars($content) ?></textarea>

            <button type="submit" style="background:#0284c7; color:#fff; border:none; padding:12px 25px; border-radius:4px; font-weight:bold; cursor:pointer;">Сохранить</button>
            <a href="index.php" style="margin-left: 15px; color: #64748b; text-decoration: none;">Отмена</a>
        </form>
    </div>

    <script>
        var quill = new Quill('#editor-container', {
            theme: 'snow',
            modules: { toolbar: [[{ 'header': [1, 2, 3, false] }], ['bold', 'italic', 'underline'], [{ 'list': 'ordered'}, { 'list': 'bullet' }], ['link', 'image'], ['clean']] }
        });

        const toggleBtn = document.getElementById('toggleCodeBtn');
        const editorContainer = document.getElementById('editor-container');
        const htmlTextarea = document.getElementById('html-textarea');
        let isCodeView = false;

        toggleBtn.addEventListener('click', function() {
            if (!isCodeView) {
                htmlTextarea.value = quill.root.innerHTML;
                editorContainer.style.display = 'none';
                htmlTextarea.style.display = 'block';
                toggleBtn.innerText = 'Visual Editor (Визуально)';
                isCodeView = true;
            } else {
                quill.root.innerHTML = htmlTextarea.value;
                htmlTextarea.style.display = 'none';
                editorContainer.style.display = 'block';
                toggleBtn.innerText = '</> Исходный код (HTML)';
                isCodeView = false;
            }
        });

        document.getElementById('articleForm').onsubmit = function() {
            if (!isCodeView) {
                htmlTextarea.value = quill.root.innerHTML;
            }
        };
    </script>
</body>
</html>
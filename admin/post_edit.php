<?php
session_start();
require_once '../db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

function fetch_all_categories($result) {
    $rows = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $rows[] = $row;
    }
    return $rows;
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$post = [
    'title' => '',
    'slug' => '',
    'category_id' => 0,
    'content' => '',
    'image' => '',
    'is_published' => 1
];
$error = '';

// Fetch categories for dropdown
$categories = fetch_all_categories($db->query("SELECT * FROM categories ORDER BY sort_order ASC"));

if ($id > 0) {
    $stmt = $db->prepare("SELECT * FROM posts WHERE id = :id");
    $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
    $post = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
    if (!$post) {
        header('Location: posts.php');
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title']);
    $slug = trim($_POST['slug']);
    $category_id = (int)$_POST['category_id'];
    $content = $_POST['content'] ?? '';
    $is_published = isset($_POST['is_published']) ? 1 : 0;
    
    // Image handling
    $image_path = $post['image'];
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath = $_FILES['image']['tmp_name'];
        $fileName = $_FILES['image']['name'];
        $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
        if (in_array($fileExtension, $allowedExtensions)) {
            $newFileName = md5(time() . $fileName) . '.' . $fileExtension;
            $uploadFileDir = '../uploads/';
            
            if (!is_dir($uploadFileDir)) {
                mkdir($uploadFileDir, 0755, true);
            }
            
            $dest_path = $uploadFileDir . $newFileName;
            if (move_uploaded_file($fileTmpPath, $dest_path)) {
                if (!empty($image_path) && file_exists('../' . $image_path)) {
                    @unlink('../' . $image_path);
                }
                $image_path = 'uploads/' . $newFileName;
            }
        }
    }

    if (empty($slug)) {
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $title), '-'));
    }

    if (empty($title)) {
        $error = 'Title is required.';
    } else {
        if ($id > 0) {
            $stmt = $db->prepare("UPDATE posts SET title = :title, slug = :slug, category_id = :cat_id, content = :content, image = :img, is_published = :pub WHERE id = :id");
            $stmt->bindValue(':title', $title, SQLITE3_TEXT);
            $stmt->bindValue(':slug', $slug, SQLITE3_TEXT);
            $stmt->bindValue(':cat_id', $category_id, SQLITE3_INTEGER);
            $stmt->bindValue(':content', $content, SQLITE3_TEXT);
            $stmt->bindValue(':img', $image_path, SQLITE3_TEXT);
            $stmt->bindValue(':pub', $is_published, SQLITE3_INTEGER);
            $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
            $stmt->execute();
        } else {
            $stmt = $db->prepare("INSERT INTO posts (title, slug, category_id, content, image, is_published) VALUES (:title, :slug, :cat_id, :content, :img, :pub)");
            $stmt->bindValue(':title', $title, SQLITE3_TEXT);
            $stmt->bindValue(':slug', $slug, SQLITE3_TEXT);
            $stmt->bindValue(':cat_id', $category_id, SQLITE3_INTEGER);
            $stmt->bindValue(':content', $content, SQLITE3_TEXT);
            $stmt->bindValue(':img', $image_path, SQLITE3_TEXT);
            $stmt->bindValue(':pub', $is_published, SQLITE3_INTEGER);
            $stmt->execute();
        }
        header('Location: posts.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $id > 0 ? 'Edit Post' : 'Add Post' ?> - Admin Panel</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@3.4.1/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@3.4.1/dist/js/bootstrap.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote.min.js"></script>
    <style>
        .admin-container { max-width: 900px; margin: 30px auto; padding: 0 15px; box-sizing: border-box; }
        .admin-card { background: #ffffff; border-radius: 8px; border: 1px solid #e5e7eb; box-shadow: 0 1px 3px rgba(0,0,0,0.05); padding: 25px; }
        *, *::before, *::after { box-sizing: border-box; }
        body { margin: 0; font-family: 'Roboto', sans-serif; background-color: #f3f4f6; color: #1f2937; font-size: 14px; }
        .admin-header { background-color: #1f2937; color: #ffffff; padding: 15px 30px; display: flex; justify-content: space-between; align-items: center; border-bottom: 3px solid #10b981; }
        .admin-header a { color: #9ca3af; text-decoration: none; font-weight: 600; }
        .admin-header a:hover { color: #ffffff; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; font-weight: 700; margin-bottom: 8px; color: #374151; }
        .form-group input[type="text"], .form-group select { width: 100%; padding: 10px 12px; border: 1px solid #d1d5db; border-radius: 4px; font-size: 14px; }
        .btn-custom { padding: 10px 20px; background: #10b981; color: #ffffff; border-radius: 4px; text-decoration: none; font-weight: 600; border: none; cursor: pointer; display: inline-block; }
        .btn-custom:hover { background: #059669; color: #fff; }
        .btn-secondary-custom { background: #6b7280; margin-left: 10px; color: #fff; }
        .btn-secondary-custom:hover { background: #4b5563; color: #fff; }
        .error { background: #fee2e2; color: #dc2626; padding: 10px; border-radius: 4px; margin-bottom: 20px; }
        .note-editor.note-frame { border: 1px solid #d1d5db; border-radius: 4px; box-shadow: none; }
        .note-toolbar { background: #f9fafb !important; border-bottom: 1px solid #e5e7eb !important; }
    </style>
</head>
<body>

    <header class="admin-header">
        <div style="font-weight: 900; text-transform: uppercase;"><a href="/admin/index.php">life-extrim admin</a></div>
        <div>
            <a href="posts.php"><i class="fa-solid fa-arrow-left"></i> Back to Posts</a>
        </div>
    </header>

    <div class="admin-container">
        <div class="admin-card">
            <h2 style="margin-top: 0; margin-bottom: 20px; font-size: 20px;"><?= $id > 0 ? 'Edit Post' : 'Add New Post' ?></h2>

            <?php if (!empty($error)): ?>
                <div class="error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form action="" method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <label>Title</label>
                    <input type="text" name="title" value="<?= htmlspecialchars($post['title']) ?>" required>
                </div>
                <div class="form-group">
                    <label>Slug (URL)</label>
                    <input type="text" name="slug" value="<?= htmlspecialchars($post['slug']) ?>" placeholder="e.g. pull-up-masterclass">
                </div>
                <div class="form-group">
                    <label>Category</label>
                    <select name="category_id">
                        <option value="0">Uncategorized</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>" <?= ($post['category_id'] == $cat['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cat['title']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Main Image</label>
                    <?php if (!empty($post['image'])): ?>
                        <div style="margin-bottom: 10px;">
                            <img src="/<?= htmlspecialchars($post['image']) ?>" alt="" style="max-width: 150px; height: 90px; object-fit: cover; border-radius: 4px; border: 1px solid #d1d5db;">
                        </div>
                    <?php endif; ?>
                    <input type="file" name="image" accept="image/*">
                </div>
                <div class="form-group">
                    <label>Content</label>
                    <textarea id="summernote" name="content"><?= htmlspecialchars($post['content']) ?></textarea>
                </div>
                <div class="form-group" style="display: flex; align-items: center; gap: 10px;">
                    <input type="checkbox" id="is_published" name="is_published" value="1" <?= $post['is_published'] ? 'checked' : '' ?> style="width: 18px; height: 18px; cursor: pointer;">
                    <label for="is_published" style="margin: 0; cursor: pointer;">Publish post</label>
                </div>

                <button type="submit" class="btn-custom"><?= $id > 0 ? 'Save Changes' : 'Create Post' ?></button>
                <a href="posts.php" class="btn-custom btn-secondary-custom">Cancel</a>
            </form>
        </div>
    </div>

    <script>
        $(document).ready(function() {
            $('#summernote').summernote({
                height: 400,
                placeholder: 'Write full article content here...',
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
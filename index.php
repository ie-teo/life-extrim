<?php
require_once 'db.php';

// Pagination settings (number of posts per page)
$posts_per_page = 10; 

// Helper function to fetch all rows as associative array for SQLite3
function fetch_all($result) {
    $rows = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $rows[] = $row;
    }
    return $rows;
}

$top_menu = fetch_all($db->query("SELECT * FROM pages"));
$sidebar_categories = fetch_all($db->query("SELECT * FROM categories ORDER BY sort_order ASC"));

// Get 2 latest published posts for sidebar
$recent_posts = fetch_all($db->query("SELECT * FROM posts WHERE is_published = 1 ORDER BY id DESC LIMIT 2"));

// Get random published posts for footer
$random_posts = fetch_all($db->query("SELECT * FROM posts WHERE is_published = 1 ORDER BY RANDOM() LIMIT 4"));

$current_page_num = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($current_page_num - 1) * $posts_per_page;

$route = $_GET['route'] ?? 'home';
$route = trim($route, '/');
if (empty($route)) {
    $route = 'home';
}
$parts = explode('/', $route);

$view_type = ''; 
$current_page = null;
$current_category = null;
$current_post = null;
$posts_list = [];
$total_pages = 1;
$search_query = trim($_GET['q'] ?? '');

// Helper function to extract first 2 sentences from HTML content
function get_two_sentences($html_content) {
    $text = strip_tags($html_content);
    preg_match('/.*?[.!?](?:\s+|$)..*?[.!?](?:\s+|$)/u', $text, $matches);
    if (!empty($matches[0])) {
        return trim($matches[0]);
    }
    return mb_substr($text, 0, 150) . '...';
}

if (!empty($search_query)) {
    $view_type = 'search';

    $stmtCount = $db->prepare("SELECT COUNT(*) FROM posts WHERE (title LIKE :term1 OR content LIKE :term2) AND is_published = 1");
    $searchTerm = '%' . $search_query . '%';
    $stmtCount->bindValue(':term1', $searchTerm, SQLITE3_TEXT);
    $stmtCount->bindValue(':term2', $searchTerm, SQLITE3_TEXT);
    $countRes = $stmtCount->execute()->fetchArray(SQLITE3_NUM);
    $total_posts = $countRes[0] ?? 0;

    $total_pages = ceil($total_posts / $posts_per_page);

    $stmtPosts = $db->prepare("SELECT * FROM posts WHERE (title LIKE :term1 OR content LIKE :term2) AND is_published = 1 ORDER BY id DESC LIMIT :limit OFFSET :offset");
    $stmtPosts->bindValue(':term1', $searchTerm, SQLITE3_TEXT);
    $stmtPosts->bindValue(':term2', $searchTerm, SQLITE3_TEXT);
    $stmtPosts->bindValue(':limit', $posts_per_page, SQLITE3_INTEGER);
    $stmtPosts->bindValue(':offset', $offset, SQLITE3_INTEGER);
    $posts_list = fetch_all($stmtPosts->execute());

} elseif (count($parts) === 1) {
    $slug = $parts[0];

    $stmt = $db->prepare("SELECT * FROM pages WHERE slug = :slug");
    $stmt->bindValue(':slug', $slug, SQLITE3_TEXT);
    $current_page = $stmt->execute()->fetchArray(SQLITE3_ASSOC);

    if ($current_page) {
        $view_type = 'page';
    } else {
        $stmt = $db->prepare("SELECT * FROM categories WHERE slug = :slug");
        $stmt->bindValue(':slug', $slug, SQLITE3_TEXT);
        $current_category = $stmt->execute()->fetchArray(SQLITE3_ASSOC);

        if ($current_category) {
            $view_type = 'category';

            $stmtCount = $db->prepare("SELECT COUNT(*) FROM posts WHERE category_id = :cat_id AND is_published = 1");
            $stmtCount->bindValue(':cat_id', $current_category['id'], SQLITE3_INTEGER);
            $countRes = $stmtCount->execute()->fetchArray(SQLITE3_NUM);
            $total_posts = $countRes[0] ?? 0;

            $total_pages = ceil($total_posts / $posts_per_page);

            $stmtPosts = $db->prepare("SELECT * FROM posts WHERE category_id = :cat_id AND is_published = 1 ORDER BY id DESC LIMIT :limit OFFSET :offset");
            $stmtPosts->bindValue(':cat_id', $current_category['id'], SQLITE3_INTEGER);
            $stmtPosts->bindValue(':limit', $posts_per_page, SQLITE3_INTEGER);
            $stmtPosts->bindValue(':offset', $offset, SQLITE3_INTEGER);
            $posts_list = fetch_all($stmtPosts->execute());
        } else {
            $view_type = '404';
        }
    }
} elseif (count($parts) === 2 && $parts[0] === 'post') {
    $post_slug = $parts[1];
    $stmt = $db->prepare("SELECT p.*, c.title as category_title, c.slug as category_slug FROM posts p LEFT JOIN categories c ON p.category_id = c.id WHERE p.slug = :slug AND p.is_published = 1");
    $stmt->bindValue(':slug', $post_slug, SQLITE3_TEXT);
    $current_post = $stmt->execute()->fetchArray(SQLITE3_ASSOC);

    if ($current_post) {
        $view_type = 'post';

        // Fetch related posts from the same category (excluding current post, max 3)
        $stmtRelated = $db->prepare("SELECT * FROM posts WHERE category_id = :cat_id AND id != :post_id AND is_published = 1 ORDER BY RANDOM() LIMIT 3");
        $stmtRelated->bindValue(':cat_id', $current_post['category_id'], SQLITE3_INTEGER);
        $stmtRelated->bindValue(':post_id', $current_post['id'], SQLITE3_INTEGER);
        $related_posts = fetch_all($stmtRelated->execute());
    } else {
        $view_type = '404';
    }
} else {
    $view_type = '404';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>
        <?= $view_type === 'page' ? htmlspecialchars($current_page['title']) : '' ?>
        <?= $view_type === 'category' ? htmlspecialchars($current_category['title']) : '' ?>
        <?= $view_type === 'post' ? htmlspecialchars($current_post['title']) : '' ?>
        <?= $view_type === 'search' ? 'Search: ' . htmlspecialchars($search_query) : '' ?>
        <?= $view_type === '404' ? '404 Not Found' : '' ?>
        - Life-extrim.ru
    </title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/css/style.css">
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <!-- Yandex.Metrika counter -->
<script type="text/javascript">
    (function(m,e,t,r,i,k,a){
        m[i]=m[i]||function(){(m[i].a=m[i].a||[]).push(arguments)};
        m[i].l=1*new Date();
        for (var j = 0; j < document.scripts.length; j++) {if (document.scripts[j].src === r) { return; }}
        k=e.createElement(t),a=e.getElementsByTagName(t)[0],k.async=1,k.src=r,a.parentNode.insertBefore(k,a)
    })(window, document,'script','https://mc.yandex.ru/metrika/tag.js?id=113813591', 'ym');

    ym(113813591, 'init', {ssr:true, webvisor:true, clickmap:true, ecommerce:"dataLayer", referrer: document.referrer, url: location.href, accurateTrackBounce:true, trackLinks:true});
</script>
<noscript><div><img src="https://mc.yandex.ru/watch/113813591" style="position:absolute; left:-9999px;" alt="" /></div></noscript>
<!-- /Yandex.Metrika counter -->
</head>
<body>

    <header class="site-header">
        <div class="wrapper top-nav">
            <a href="/home" class="logo">
                <i class="fa-solid fa-dumbbell"></i>
                <span>life-extrim</span>
            </a>
            <ul class="top-nav_menu">
                <?php foreach ($top_menu as $item): ?>
                    <li>
                        <a href="/<?= htmlspecialchars($item['slug']) ?>" class="<?= ($route === $item['slug']) ? 'active' : '' ?>">
                            <?= htmlspecialchars($item['title']) ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </header>

    <main class="wrapper">
        <div class="main-layout">
            <aside class="left-sidebar">
                
                <!-- 1. Блок рубрик -->
                <div class="sidebar-box">
                    <div class="sidebar-title"><i class="fa-solid fa-bolt"></i> Categories</div>
                    <ul class="left-sidebar_menu">
                        <?php foreach ($sidebar_categories as $cat): ?>
                            <li>
                                <a href="/<?= htmlspecialchars($cat['slug']) ?>" class="<?= ($current_category && $current_category['slug'] === $cat['slug']) ? 'active' : '' ?>">
                                    <?= htmlspecialchars($cat['title']) ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>

                <!-- 2. Блок поиска -->
                <div class="sidebar-box">
                    <div class="sidebar-title"><i class="fa-solid fa-search"></i> Search</div>
                    <div class="side-bar">
                        <form action="/" method="GET" class="side_get">
                            <input type="text" name="q" placeholder="Search articles..." value="<?= htmlspecialchars($search_query) ?>" required class="side-input">
                            <button type="submit" class="side-submit"><i class="fa-solid fa-magnifying-glass"></i></button>
                        </form>
                    </div>
                </div>

                <!-- 3. Блок последних 2 постов -->
                <div class="sidebar-box">
                    <div class="sidebar-title"><i class="fa-solid fa-clock"></i> Recent Posts</div>
                    <div>
                        <?php if (!empty($recent_posts)): ?>
                            <?php foreach ($recent_posts as $rp): ?>
                                <div class="recent-post-item">
                                    <?php if (!empty($rp['image'])): ?>
                                        <a href="/post/<?= htmlspecialchars($rp['slug']) ?>">
                                            <img src="/<?= htmlspecialchars($rp['image']) ?>" alt="">
                                        </a>
                                    <?php endif; ?>
                                    <div>
                                        <h4><a href="/post/<?= htmlspecialchars($rp['slug']) ?>"><?= htmlspecialchars($rp['title']) ?></a></h4>
                                        <p><?= htmlspecialchars(mb_substr(strip_tags($rp['content']), 0, 50)) ?>...</p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="yet">No posts yet</div>
                        <?php endif; ?>
                    </div>
                </div>

            </aside>

            <section class="content-area">
                <?php if ($view_type === 'page'): ?>
                    <h1><?= htmlspecialchars($current_page['title']) ?></h1>
                    <div class="article-body">
                        <?= $current_page['content'] ?>
                    </div>

                <?php elseif ($view_type === 'search'): ?>
                    <h1>Search Results: "<?= htmlspecialchars($search_query) ?>"</h1>
                    
                    <?php if (empty($posts_list)): ?>
                        <p style="color: #6b7280;">No articles found matching your query.</p>
                    <?php else: ?>
                        <div class="posts-list">
                            <?php foreach ($posts_list as $p): ?>
                                <article class="post-card">
                                    <?php if (!empty($p['image'])): ?>
                                        <a href="/post/<?= htmlspecialchars($p['slug']) ?>" style="flex-shrink: 0;">
                                            <img src="/<?= htmlspecialchars($p['image']) ?>" alt="">
                                        </a>
                                    <?php endif; ?>
                                    <div>
                                        <h2>
                                            <a href="/post/<?= htmlspecialchars($p['slug']) ?>"><?= htmlspecialchars($p['title']) ?></a>
                                        </h2>
                                        <p><?= htmlspecialchars(get_two_sentences($p['content'])) ?></p>
                                        <a href="/post/<?= htmlspecialchars($p['slug']) ?>" class="read-more">Read more &rarr;</a>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>

                        <?php if ($total_pages > 1): ?>
                            <ul class="pagination">
                                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                    <li class="<?= ($current_page_num == $i) ? 'active' : '' ?>">
                                        <?php if ($current_page_num == $i): ?>
                                            <span><?= $i ?></span>
                                        <?php else: ?>
                                            <a href="/?q=<?= urlencode($search_query) ?>&page=<?= $i ?>"><?= $i ?></a>
                                        <?php endif; ?>
                                    </li>
                                <?php endfor; ?>
                            </ul>
                        <?php endif; ?>

                    <?php endif; ?>

                <?php elseif ($view_type === 'category'): ?>
                    <h1><?= htmlspecialchars($current_category['title']) ?></h1>
                    
                    <?php if (empty($posts_list)): ?>
                        <p style="color: #6b7280;">No published articles in this category yet.</p>
                    <?php else: ?>
                        <div class="posts-list">
                            <?php foreach ($posts_list as $p): ?>
                                <article class="post-card">
                                    <?php if (!empty($p['image'])): ?>
                                        <a href="/post/<?= htmlspecialchars($p['slug']) ?>" style="flex-shrink: 0;">
                                            <img src="/<?= htmlspecialchars($p['image']) ?>" alt="">
                                        </a>
                                    <?php endif; ?>
                                    <div>
                                        <h2>
                                            <a href="/post/<?= htmlspecialchars($p['slug']) ?>"><?= htmlspecialchars($p['title']) ?></a>
                                        </h2>
                                        <p><?= htmlspecialchars(get_two_sentences($p['content'])) ?></p>
                                        <a href="/post/<?= htmlspecialchars($p['slug']) ?>" class="read-more">Read more &rarr;</a>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>

                        <?php if ($total_pages > 1): ?>
                            <ul class="pagination">
                                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                    <li class="<?= ($current_page_num == $i) ? 'active' : '' ?>">
                                        <?php if ($current_page_num == $i): ?>
                                            <span><?= $i ?></span>
                                        <?php else: ?>
                                            <a href="/<?= htmlspecialchars($current_category['slug']) ?>?page=<?= $i ?>"><?= $i ?></a>
                                        <?php endif; ?>
                                    </li>
                                <?php endfor; ?>
                            </ul>
                        <?php endif; ?>

                    <?php endif; ?>

                <?php elseif ($view_type === 'post'): ?>
                    <?php if (!empty($current_post['image'])): ?>
                        <img src="/<?= htmlspecialchars($current_post['image']) ?>" alt="" clss="img_style">
                    <?php endif; ?>
                    <h1><?= htmlspecialchars($current_post['title']) ?></h1>
                    <div class="article-body">
                        <?= $current_post['content'] ?>
                    </div>

                    <?php if (!empty($related_posts)): ?>
                        <div class="post-t">
                            <h3 class="im_article">Related Articles</h3>
                            <div class="related-posts-grid">
                                <?php foreach ($related_posts as $rp): ?>
                                    <div class="related-card">
                                        <?php if (!empty($rp['image'])): ?>
                                            <a href="/post/<?= htmlspecialchars($rp['slug']) ?>">
                                                <img src="/<?= htmlspecialchars($rp['image']) ?>" alt="">
                                            </a>
                                        <?php endif; ?>
                                        <div class="related-card-content">
                                            <h4>
                                                <a href="/post/<?= htmlspecialchars($rp['slug']) ?>"><?= htmlspecialchars($rp['title']) ?></a>
                                            </h4>
                                            <p><?= htmlspecialchars(get_two_sentences($rp['content'])) ?></p>
                                            <a href="/post/<?= htmlspecialchars($rp['slug']) ?>" class="read-more">Read more &rarr;</a>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                <?php else: ?>
                    <h1>404 — Page Not Found</h1>
                    <p style="color: #6b7280;">The requested article or page could not be found.</p>
                <?php endif; ?>
            </section>
        </div>
    </main>

    <footer class="site-footer">
        <div class="wrapper">
            <div class="footer-grid">
                
                <div class="footer-col">
                    <h3><i class="fa-solid fa-dumbbell"></i> life-extrim.ru</h3>
                    <p style="footer-text">
                        Your ultimate blueprint for bodyweight workouts, calisthenics, and functional strength training.
                    </p>
                    <ul class="footer-socials">
                        <li><a href="https://www.facebook.com/iron.lib/" target="_blank" title="Facebook"><i class="fa-brands fa-facebook-f"></i></a></li>
                        <li><a href="https://www.instagram.com/iron.lib" target="_blank" title="Instagram"><i class="fa-brands fa-instagram"></i></a></li>
                        <li><a href="https://www.youtube.com/@iron-lib" target="_blank" title="YouTube"><i class="fa-brands fa-youtube"></i></a></li>
                        <li><a href="tiktok.com/@gym.lib" target="_blank" title="TikTok"><i class="fa-brands fa-tiktok"></i></a></li>
                        <li>
                            <a href="https://x.com/ironlibs" target="_blank" title="X (Twitter)">
                                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"></path></svg>
                            </a>
                        </li>
                        <li><a href="https://ie.pinterest.com/ironlibs/" target="_blank" title="Pinterest"><i class="fa-brands fa-pinterest"></i></a></li>

                        

                    </ul>
                </div>

                <div class="footer-col">
                    <h3>Navigation</h3>
                    <ul class="footer-menu">
                        <?php foreach ($top_menu as $item): ?>
                            <li><a href="/<?= htmlspecialchars($item['slug']) ?>"><?= htmlspecialchars($item['title']) ?></a></li>
                        <?php endforeach; ?>
                    </ul>
                </div>

                <div class="footer-col">
                    <h3>Random Articles</h3>
                    <ul class="footer-menu">
                        <?php if (!empty($random_posts)): ?>
                            <?php foreach ($random_posts as $rp): ?>
                                <li><a href="/post/<?= htmlspecialchars($rp['slug']) ?>"><?= htmlspecialchars($rp['title']) ?></a></li>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <li><span style="color: #6b7280; font-size: 13px;">No articles yet</span></li>
                        <?php endif; ?>
                    </ul>
                </div>

            </div>
            
            <div class="footer-copy">
                Copyright <a href="/admin/index.php" target="_blank">&copy;</a> <?= date('Y') ?> life-extrim.ru all rights reserved.
            </div>
        </div>
    </footer>
</body>
</html>
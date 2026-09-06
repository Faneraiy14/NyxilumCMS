<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/render.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/categories.php';

// php -S: якщо це існуючий статичний файл - віддаємо як є.
if (PHP_SAPI === 'cli-server') {
    $file = __DIR__ . parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    if ($file !== __DIR__ . '/' && is_file($file)) {
        return false;
    }
}

if (!is_installed()) {
    header('Location: /install.php');
    exit;
}

$db = get_db();
$siteName = get_setting($db, 'site_name', 'Nyxilum CMS');

$path = trim(rawurldecode((string) parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH)), '/');

if ($path === '') {
    $items = $db->query('SELECT * FROM content WHERE ' . published_condition() . ' ORDER BY updated_at DESC LIMIT 20')->fetchAll();
    render('home', ['db' => $db, 'siteName' => $siteName, 'items' => $items]);
    exit;
}

if ($path === 'sitemap.xml') {
    $items = $db->query('SELECT slug, updated_at FROM content WHERE ' . published_condition() . ' ORDER BY updated_at DESC')->fetchAll();
    // Абсолютні URL будуються з реального хоста запиту, а не з окремого
    // "site_url" в налаштуваннях - так sitemap завжди правильний, хоч
    // локально, хоч на реальному домені, без ручного налаштування.
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $base = $scheme . '://' . $_SERVER['HTTP_HOST'];

    header('Content-Type: application/xml; charset=utf-8');
    echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
    echo "  <url><loc>{$base}/</loc></url>\n";
    foreach ($items as $item) {
        $loc = htmlspecialchars($base . '/' . rawurlencode($item['slug']));
        $lastmod = htmlspecialchars(date('c', strtotime($item['updated_at'])));
        echo "  <url><loc>{$loc}</loc><lastmod>{$lastmod}</lastmod></url>\n";
    }
    echo '</urlset>';
    exit;
}

if ($path === 'feed.xml') {
    $items = $db->query(
        "SELECT * FROM content WHERE type = 'post' AND " . published_condition() . ' ORDER BY updated_at DESC LIMIT 20'
    )->fetchAll();
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $base = $scheme . '://' . $_SERVER['HTTP_HOST'];

    header('Content-Type: application/rss+xml; charset=utf-8');
    echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    echo '<rss version="2.0"><channel>' . "\n";
    echo '  <title>' . htmlspecialchars($siteName) . "</title>\n";
    echo "  <link>{$base}/</link>\n";
    echo "  <description>RSS-стрічка публікацій</description>\n";
    foreach ($items as $item) {
        $link = htmlspecialchars($base . '/' . rawurlencode($item['slug']));
        // strip_tags - RSS-рідери здебільшого очікують текстовий опис у
        // <description>, не форматований HTML з Quill (деякі рідери його
        // й так екранували б, але чистий текст надійніший і передбачуваніший).
        $desc = htmlspecialchars($item['meta_description'] ?: mb_substr(strip_tags($item['body']), 0, 300));
        $pubDate = htmlspecialchars(date(DATE_RSS, strtotime($item['created_at'])));
        echo "  <item>\n";
        echo '    <title>' . htmlspecialchars($item['title']) . "</title>\n";
        echo "    <link>{$link}</link>\n";
        echo "    <guid>{$link}</guid>\n";
        echo "    <pubDate>{$pubDate}</pubDate>\n";
        echo "    <description>{$desc}</description>\n";
        echo "  </item>\n";
    }
    echo '</channel></rss>';
    exit;
}

if ($path === 'search') {
    $query = trim($_GET['q'] ?? '');
    $items = [];
    if ($query !== '') {
        $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $query) . '%';
        $stmt = $db->prepare(
            'SELECT * FROM content WHERE ' . published_condition() . ' AND (title LIKE ? OR body LIKE ?) ORDER BY updated_at DESC LIMIT 30'
        );
        $stmt->execute([$like, $like]);
        $items = $stmt->fetchAll();
    }
    render('search', [
        'db' => $db, 'siteName' => $siteName,
        'pageTitle' => 'Пошук' . ($query !== '' ? ": {$query}" : '') . " — {$siteName}",
        'query' => $query, 'items' => $items,
    ]);
    exit;
}

if (str_starts_with($path, 'category/')) {
    $slug = substr($path, strlen('category/'));
    $stmt = $db->prepare('SELECT * FROM categories WHERE slug = ?');
    $stmt->execute([$slug]);
    $category = $stmt->fetch();

    if (!$category) {
        http_response_code(404);
        render('404', ['db' => $db, 'siteName' => $siteName]);
        exit;
    }

    $itemsStmt = $db->prepare(
        'SELECT content.* FROM content
         JOIN content_categories cc ON cc.content_id = content.id
         WHERE cc.category_id = ? AND ' . published_condition('content.') . '
         ORDER BY content.updated_at DESC'
    );
    $itemsStmt->execute([$category['id']]);
    render('category', [
        'db' => $db, 'siteName' => $siteName,
        'pageTitle' => $category['name'] . ' — ' . $siteName,
        'category' => $category, 'items' => $itemsStmt->fetchAll(),
    ]);
    exit;
}

// Спільне для звичайного перегляду й прев'ю - обирає тип-специфічний
// шаблон (напр. "post" -> templates/type-post.php), якщо такий існує,
// інакше типовий page.php. preg_match тут не про "недовіру адміну" (тип
// вводить тільки він сам), а щоб не отримати шлях за межі templates/,
// навіть від довіреного джерела - дешева перестраховка.
function render_content_item(PDO $db, string $siteName, array $item, bool $isPreview = false): void
{
    $template = 'page';
    if (preg_match('/^[a-z0-9_-]+$/', $item['type']) && is_file(__DIR__ . "/templates/type-{$item['type']}.php")) {
        $template = "type-{$item['type']}";
    }
    render($template, [
        'db' => $db, 'siteName' => $siteName,
        'pageTitle' => ($isPreview ? '[Чернетка] ' : '') . ($item['meta_title'] ?: $item['title']) . ' — ' . $siteName,
        'metaDescription' => $item['meta_description'] ?? '',
        'item' => $item,
        'itemCategories' => get_categories_for_content($db, (int) $item['id']),
    ]);
}

// Прев'ю чернетки - лише для залогіненого адміна, без окремої системи
// підписаних токенів (простіше: якщо ти вже в адмінці, ти й так довірений).
if (str_starts_with($path, 'preview/')) {
    require_login();
    $slug = substr($path, strlen('preview/'));
    $stmt = $db->prepare('SELECT * FROM content WHERE slug = ?');
    $stmt->execute([$slug]);
    $item = $stmt->fetch();
    if (!$item) {
        http_response_code(404);
        render('404', ['db' => $db, 'siteName' => $siteName]);
        exit;
    }
    render_content_item($db, $siteName, $item, true);
    exit;
}

$stmt = $db->prepare('SELECT * FROM content WHERE slug = ? AND ' . published_condition());
$stmt->execute([$path]);
$item = $stmt->fetch();

if ($item) {
    render_content_item($db, $siteName, $item);
    exit;
}

http_response_code(404);
render('404', ['db' => $db, 'siteName' => $siteName]);

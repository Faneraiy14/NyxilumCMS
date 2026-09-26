<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/render.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/categories.php';
require_once __DIR__ . '/includes/site_auth.php';
require_once __DIR__ . '/includes/comments.php';
require_once __DIR__ . '/includes/csrf.php';

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

// Справжній перемикач мови (26.09.2026, перенесено з my-hub) - кука
// LANG_COOKIE, не сесія (переживає закриття браузера, той самий вибір
// без повторного логіну). Індивідуальні сторінки контенту (нижче,
// render_content_item) переозначають $currentLang власним item['lang'] -
// заголовок сторінки МАЄ відповідати мові самого контенту, а не тому,
// що показувалось на попередній сторінці.
const LANG_COOKIE = 'nyxilum_lang';
$currentLang = $_COOKIE[LANG_COOKIE] ?? get_setting($db, 'default_lang', 'uk');

$path = trim(rawurldecode((string) parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH)), '/');

if ($path === 'set-lang') {
    $to = (string) ($_GET['to'] ?? '');
    $to = preg_match('/^[a-z]{2}$/', $to) ? $to : 'uk';
    setcookie(LANG_COOKIE, $to, [
        'expires' => time() + 60 * 60 * 24 * 365,
        'path' => '/',
        'samesite' => 'Lax',
    ]);

    // Локальний шлях, ніколи чужий хост - "//evil.com" браузер читає
    // як protocol-relative URL, тож окремо блокуємо саме цей випадок,
    // не лише перший символ (той самий захист, що вже був у my-hub).
    $return = (string) ($_GET['return'] ?? '/');
    if ($return === '' || $return[0] !== '/' || str_starts_with($return, '//') || str_contains($return, '\\')) {
        $return = '/';
    }
    header('Location: ' . $return, true, 302);
    exit;
}

if ($path === '') {
    $stmt = $db->prepare('SELECT * FROM content WHERE lang = ? AND ' . published_condition() . ' ORDER BY updated_at DESC LIMIT 20');
    $stmt->execute([$currentLang]);
    render('home', ['db' => $db, 'siteName' => $siteName, 'currentLang' => $currentLang, 'currentPath' => '', 'items' => $stmt->fetchAll()]);
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
            'SELECT * FROM content WHERE lang = ? AND ' . published_condition() . ' AND (title LIKE ? OR body LIKE ?) ORDER BY updated_at DESC LIMIT 30'
        );
        $stmt->execute([$currentLang, $like, $like]);
        $items = $stmt->fetchAll();
    }
    render('search', [
        'db' => $db, 'siteName' => $siteName, 'currentLang' => $currentLang, 'currentPath' => 'search',
        'pageTitle' => 'Пошук' . ($query !== '' ? ": {$query}" : '') . " — {$siteName}",
        'query' => $query, 'items' => $items,
    ]);
    exit;
}

// Акаунти відвідувачів (реєстрація/вхід/вихід/коментарі) - геть окрема
// система від admin_users (includes/site_auth.php), той самий PHP-
// сеанс, свій ключ site_user_id.
if ($path === 'register') {
    $error = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        csrf_verify();
        $error = site_register((string) ($_POST['username'] ?? ''), (string) ($_POST['email'] ?? ''), (string) ($_POST['password'] ?? ''));
        if ($error === '') {
            header('Location: /');
            exit;
        }
    }
    render('register', [
        'db' => $db, 'siteName' => $siteName, 'currentLang' => $currentLang, 'currentPath' => 'register',
        'pageTitle' => "Реєстрація — {$siteName}", 'error' => $error,
    ]);
    exit;
}

if ($path === 'login') {
    $error = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        csrf_verify();
        if (site_attempt_login((string) ($_POST['username'] ?? ''), (string) ($_POST['password'] ?? ''))) {
            header('Location: /');
            exit;
        }
        $error = 'Невірний логін/email або пароль.';
    }
    render('account-login', [
        'db' => $db, 'siteName' => $siteName, 'currentLang' => $currentLang, 'currentPath' => 'login',
        'pageTitle' => "Вхід — {$siteName}", 'error' => $error,
    ]);
    exit;
}

if ($path === 'logout') {
    site_logout();
    header('Location: /');
    exit;
}

if ($path === 'comment' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $siteUser = current_site_user();
    $contentId = (int) ($_POST['content_id'] ?? 0);
    $stmt = $db->prepare('SELECT slug FROM content WHERE id = ?');
    $stmt->execute([$contentId]);
    $slug = $stmt->fetchColumn();

    if ($siteUser === null) {
        header('Location: /login');
        exit;
    }
    if ($slug !== false) {
        add_comment($db, $contentId, $siteUser['id'], (string) ($_POST['body'] ?? ''));
    }
    header('Location: /' . ($slug !== false ? rawurlencode($slug) : ''));
    exit;
}

if (str_starts_with($path, 'category/')) {
    $slug = substr($path, strlen('category/'));
    $stmt = $db->prepare('SELECT * FROM categories WHERE slug = ?');
    $stmt->execute([$slug]);
    $category = $stmt->fetch();

    if (!$category) {
        http_response_code(404);
        render('404', ['db' => $db, 'siteName' => $siteName, 'currentLang' => $currentLang, 'currentPath' => $path]);
        exit;
    }

    $itemsStmt = $db->prepare(
        'SELECT content.* FROM content
         JOIN content_categories cc ON cc.content_id = content.id
         WHERE cc.category_id = ? AND content.lang = ? AND ' . published_condition('content.') . '
         ORDER BY content.updated_at DESC'
    );
    $itemsStmt->execute([$category['id'], $currentLang]);
    render('category', [
        'db' => $db, 'siteName' => $siteName, 'currentLang' => $currentLang, 'currentPath' => $path,
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
/** @param array<string, mixed> $item */
function render_content_item(PDO $db, string $siteName, array $item, bool $isPreview = false): void
{
    $template = 'page';
    if (preg_match('/^[a-z0-9_-]+$/', $item['type']) && is_file(__DIR__ . "/templates/type-{$item['type']}.php")) {
        $template = "type-{$item['type']}";
    }
    render($template, [
        'db' => $db, 'siteName' => $siteName,
        // Мова сторінки - це мова САМОГО запису (item['lang']), не кука
        // відвідувача - слаг про-мене/about-me вже мовно-специфічний,
        // <html lang="..."> має відповідати РЕАЛЬНОМУ вмісту. Перемикач
        // мови (header.php) на такій сторінці веде на головну цільової
        // мови - v1 свідомо без пошуку "сусіднього" перекладу за
        // конкретним slug'ом (немає зв'язку між рядками content у схемі).
        'currentLang' => $item['lang'], 'currentPath' => $item['slug'],
        'pageTitle' => ($isPreview ? '[Чернетка] ' : '') . ($item['meta_title'] ?: $item['title']) . ' — ' . $siteName,
        'metaDescription' => $item['meta_description'] ?? '',
        'item' => $item,
        'itemCategories' => get_categories_for_content($db, (int) $item['id']),
        'itemComments' => get_comments_for_content($db, (int) $item['id']),
        'siteUser' => current_site_user(),
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
        render('404', ['db' => $db, 'siteName' => $siteName, 'currentLang' => $currentLang, 'currentPath' => $path]);
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
render('404', ['db' => $db, 'siteName' => $siteName, 'currentLang' => $currentLang, 'currentPath' => $path]);

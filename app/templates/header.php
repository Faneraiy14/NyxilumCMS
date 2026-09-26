<?php
/** @var string $siteName
 *  @var PDO $db
 */
// $currentLang/$currentPath - НЕ в @var вище навмисно: обидва
// ОПЦІЙНІ (не кожен виклик render() їх передає), а @var declares
// "завжди існує" - суперечило б власному fallback нижче й PHPStan
// (nullCoalesce.variable) слушно на це вказав би.
$currentLang = $currentLang ?? 'uk';
$currentPath = $currentPath ?? '';

// NULL = показувати на будь-якій мові (зовнішні посилання) - лише
// конкретне значення обмежує показ саме цією мовою (26.09.2026,
// справжній перемикач мови, перенесено з my-hub).
$menuStmt = $db->prepare('SELECT * FROM menu_items WHERE lang IS NULL OR lang = ? ORDER BY sort_order ASC, id ASC');
$menuStmt->execute([$currentLang]);
$menuItems = $menuStmt->fetchAll();

// Бінарний перемикач uk<->en - той самий підхід, що вже був у my-hub
// (двомовний сайт за задумом, не система довільної кількості мов).
// На сторінках КОНКРЕТНОГО контенту (index.php передає item['slug'] як
// currentPath) немає зв'язку "цей самий запис іншою мовою" у схемі -
// перемикач чесно веде на головну цільової мови, а не вгадує slug.
$targetLang = $currentLang === 'uk' ? 'en' : 'uk';
$langSwitchHref = '/set-lang?to=' . $targetLang . '&return=' . rawurlencode('/');
?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($currentLang); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo htmlspecialchars($pageTitle ?? $siteName); ?></title>
    <?php if (!empty($metaDescription)): ?>
    <meta name="description" content="<?php echo htmlspecialchars($metaDescription); ?>">
    <?php endif; ?>
    <?php
        // Open Graph/Twitter - розширення вже наявних meta_title/
        // meta_description, а не окрема нова система полів. Абсолютний
        // URL рахується тут, ЦЕНТРАЛЬНО (з реального хоста запиту, той
        // самий підхід, що й у sitemap.xml/feed.xml), а не проштовхується
        // окремим параметром через КОЖЕН виклик render() в index.php -
        // $_SERVER доступний скрізь однаково, незалежно від маршруту.
        // $item - у скоупі лише для однієї сторінки контенту
        // (render_content_item() в index.php) - "article" саме для type
        // "post", "website" для всього іншого (головна/архіви/сторінки).
        $ogScheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $ogUrl = $ogScheme . '://' . ($_SERVER['HTTP_HOST'] ?? '') . ($_SERVER['REQUEST_URI'] ?? '/');
        $ogType = (isset($item) && ($item['type'] ?? '') === 'post') ? 'article' : 'website';
        $ogTitle = $pageTitle ?? $siteName;
    ?>
    <meta property="og:type" content="<?php echo htmlspecialchars($ogType); ?>">
    <meta property="og:title" content="<?php echo htmlspecialchars($ogTitle); ?>">
    <meta property="og:url" content="<?php echo htmlspecialchars($ogUrl); ?>">
    <meta property="og:site_name" content="<?php echo htmlspecialchars($siteName); ?>">
    <?php if (!empty($metaDescription)): ?>
    <meta property="og:description" content="<?php echo htmlspecialchars($metaDescription); ?>">
    <?php endif; ?>
    <meta name="twitter:card" content="summary">
    <meta name="twitter:title" content="<?php echo htmlspecialchars($ogTitle); ?>">
    <?php if (!empty($metaDescription)): ?>
    <meta name="twitter:description" content="<?php echo htmlspecialchars($metaDescription); ?>">
    <?php endif; ?>
    <link rel="stylesheet" href="/public.css">
</head>
<body>
    <header class="site-header">
        <button class="menu-toggle" aria-label="Меню" aria-expanded="false">
            <span></span><span></span><span></span>
        </button>
        <a class="site-title" href="/"><?php echo htmlspecialchars($siteName); ?></a>
        <form method="get" action="/search" class="header-search">
            <input type="text" name="q" placeholder="Пошук…" value="<?php echo htmlspecialchars($_GET['q'] ?? ''); ?>">
        </form>
        <?php
            // Читається напряму з сесії (не через параметр render()) - на
            // відміну від itemComments/siteUser, які render_content_item()
            // передає лише сторінкам ОДНОГО запису, шапка рендериться на
            // КОЖНІЙ сторінці (home/search/category/404 теж), тож не може
            // покладатись на те, що виклик render() цього разу його передав.
            $headerSiteUser = current_site_user();
        ?>
        <div class="site-account">
            <?php if ($headerSiteUser) : ?>
                <span><?php echo htmlspecialchars($headerSiteUser['username']); ?></span> · <a href="/logout">Вийти</a>
            <?php else : ?>
                <a href="/login">Увійти</a> · <a href="/register">Реєстрація</a>
            <?php endif; ?>
        </div>
    </header>
    <div class="nav-overlay"></div>
    <?php if ($menuItems): ?>
    <nav class="site-nav">
        <?php foreach ($menuItems as $menuItem): ?>
            <?php $isExternal = preg_match('#^https?://#i', $menuItem['url']) === 1; ?>
            <a href="<?php echo htmlspecialchars($menuItem['url']); ?>"<?php echo $isExternal ? ' target="_blank" rel="noopener noreferrer"' : ''; ?>><?php echo htmlspecialchars($menuItem['label']); ?></a>
        <?php endforeach; ?>
        <div class="lang-switch">
            <a href="<?php echo htmlspecialchars($langSwitchHref); ?>"><?php echo $currentLang === 'uk' ? 'EN' : 'UK'; ?></a>
        </div>
    </nav>
    <?php endif; ?>
    <script src="/public.js"></script>
    <main class="site-main">

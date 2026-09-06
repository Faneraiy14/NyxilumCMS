<?php
/** @var string $siteName
 *  @var PDO $db
 */
$menuItems = $db->query('SELECT * FROM menu_items ORDER BY sort_order ASC, id ASC')->fetchAll();
?>
<!DOCTYPE html>
<html lang="uk">
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
        <a class="site-title" href="/"><?php echo htmlspecialchars($siteName); ?></a>
        <?php if ($menuItems): ?>
        <nav class="site-nav">
            <?php foreach ($menuItems as $menuItem): ?>
                <a href="<?php echo htmlspecialchars($menuItem['url']); ?>"><?php echo htmlspecialchars($menuItem['label']); ?></a>
            <?php endforeach; ?>
        </nav>
        <?php endif; ?>
        <form method="get" action="/search" class="header-search">
            <input type="text" name="q" placeholder="Пошук…" value="<?php echo htmlspecialchars($_GET['q'] ?? ''); ?>">
        </form>
    </header>
    <main class="site-main">

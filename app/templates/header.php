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

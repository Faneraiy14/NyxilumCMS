<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();

$db = get_db();
$total = (int) $db->query('SELECT COUNT(*) FROM content')->fetchColumn();
$published = (int) $db->query("SELECT COUNT(*) FROM content WHERE status = 'published'")->fetchColumn();
$draft = (int) $db->query("SELECT COUNT(*) FROM content WHERE status = 'draft'")->fetchColumn();

// Довільна кількість типів (не фіксований список як notes/pages/links у
// my-hub) - рахуємо, які типи реально є в базі, а не вгадуємо наперед.
$byType = $db->query('SELECT type, COUNT(*) as cnt FROM content GROUP BY type ORDER BY cnt DESC')->fetchAll();

$lastItem = $db->query('SELECT title, type, updated_at FROM content ORDER BY updated_at DESC LIMIT 1')->fetch();
?>
<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Дашборд — Nyxilum CMS</title>
    <link rel="stylesheet" href="admin.css">
</head>
<body>
    <?php include __DIR__ . '/nav.php'; ?>

    <main class="admin-main">
        <h1>Дашборд</h1>

        <div class="stats-grid">
            <a class="stat-card" href="content.php">
                <span class="stat-number"><?php echo $total; ?></span>
                <span class="stat-label">усього контенту</span>
            </a>
            <a class="stat-card" href="content.php?status=published">
                <span class="stat-number"><?php echo $published; ?></span>
                <span class="stat-label">опубліковано</span>
            </a>
            <a class="stat-card" href="content.php?status=draft">
                <span class="stat-number"><?php echo $draft; ?></span>
                <span class="stat-label">чернеток</span>
            </a>
        </div>

        <?php if ($byType) : ?>
            <h2>За типами</h2>
            <ul class="admin-list">
                <?php foreach ($byType as $row) : ?>
                    <li>
                        <a href="content.php?type=<?php echo urlencode($row['type']); ?>">
                            <?php echo htmlspecialchars($row['type']); ?> — <?php echo (int) $row['cnt']; ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <?php if ($lastItem) : ?>
            <p class="last-updated">
                Останнє оновлення: "<?php echo htmlspecialchars($lastItem['title']); ?>"
                (<?php echo htmlspecialchars($lastItem['type']); ?>, <?php echo htmlspecialchars($lastItem['updated_at']); ?>)
            </p>
        <?php endif; ?>
    </main>
</body>
</html>

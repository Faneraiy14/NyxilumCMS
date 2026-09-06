<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/activity.php';
require_login();

$db = get_db();
$total = (int) $db->query('SELECT COUNT(*) FROM content')->fetchColumn();
$published = (int) $db->query("SELECT COUNT(*) FROM content WHERE status = 'published'")->fetchColumn();
$draft = (int) $db->query("SELECT COUNT(*) FROM content WHERE status = 'draft'")->fetchColumn();

// Довільна кількість типів (не фіксований список як notes/pages/links у
// my-hub) - рахуємо, які типи реально є в базі, а не вгадуємо наперед.
$byType = $db->query('SELECT type, COUNT(*) as cnt FROM content GROUP BY type ORDER BY cnt DESC')->fetchAll();

$lastItem = $db->query('SELECT title, type, updated_at FROM content ORDER BY updated_at DESC LIMIT 1')->fetch();

// Останні дії - той самий словник підписів, що й повний журнал
// (admin/activity.php, тепер спільний у includes/activity.php), лише
// коротший зріз (8, не 50) - дашборду не потрібна пагінація. Сам
// журнал доступний лише ролі admin (require_role('admin') в
// activity.php - там і невдалі спроби входу, і зміни ролей
// користувачів) - той самий кордон доступу тут, а не показувати той
// самий зріз даних editor'у, якому повний журнал недоступний напряму.
$recentActivity = current_role() === 'admin'
    ? $db->query('SELECT * FROM activity_log ORDER BY created_at DESC LIMIT 8')->fetchAll()
    : null;
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

        <h2>Швидкі дії</h2>
        <div class="quick-actions">
            <a class="quick-action" href="content.php">+ Новий запис</a>
            <a class="quick-action" href="categories.php">+ Нова категорія</a>
            <a class="quick-action" href="menu.php">+ Пункт меню</a>
            <a class="quick-action" href="media.php">Завантажити медіа</a>
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

        <?php if (current_role() === 'admin') : ?>
            <h2>Останні дії</h2>
            <?php if ($recentActivity) : ?>
                <ul class="admin-list">
                    <?php foreach ($recentActivity as $row) : ?>
                        <li>
                            <div class="admin-list-item-header">
                                <strong>
                                    <?php echo htmlspecialchars($row['admin_username']); ?>
                                    —
                                    <?php echo htmlspecialchars(ACTION_LABELS[$row['action']] ?? $row['action']); ?>
                                    <?php echo htmlspecialchars(ENTITY_LABELS[$row['entity_type']] ?? $row['entity_type']); ?>
                                    <?php echo $row['entity_id'] !== null ? '#' . (int) $row['entity_id'] : ''; ?>
                                </strong>
                                <span class="admin-list-date"><?php echo htmlspecialchars($row['created_at']); ?></span>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <p><a href="activity.php">Весь журнал →</a></p>
            <?php else : ?>
                <p class="admin-list-empty">Дій поки немає - почни з "Швидкі дії" вище.</p>
            <?php endif; ?>
        <?php endif; ?>
    </main>
</body>
</html>

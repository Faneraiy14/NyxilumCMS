<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();
require_role('admin');

$db = get_db();

$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 50;
$offset = ($page - 1) * $perPage;

$total = (int) $db->query('SELECT COUNT(*) FROM activity_log')->fetchColumn();
$totalPages = max(1, (int) ceil($total / $perPage));

$stmt = $db->prepare('SELECT * FROM activity_log ORDER BY created_at DESC LIMIT ? OFFSET ?');
$stmt->bindValue(1, $perPage, PDO::PARAM_INT);
$stmt->bindValue(2, $offset, PDO::PARAM_INT);
$stmt->execute();
$rows = $stmt->fetchAll();

const ACTION_LABELS = [
    'create' => 'Створення',
    'update' => 'Оновлення',
    'update_role' => 'Зміна ролі',
    'delete' => 'Видалення',
    'upload' => 'Завантаження',
    'reset_password' => 'Скидання пароля',
    'login' => 'Вхід',
    'login_failed' => 'Невдалий вхід',
    'login_2fa_failed' => 'Невірний код 2FA',
    'enable_2fa' => 'Увімкнення 2FA',
    'disable_2fa' => 'Вимкнення 2FA',
];

const ENTITY_LABELS = [
    'content' => 'контент',
    'menu_item' => 'пункт меню',
    'media' => 'медіа',
    'settings' => 'налаштування',
    'user' => 'користувач',
    'auth' => 'авторизація',
    'category' => 'категорія',
];
?>
<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Журнал дій — Nyxilum CMS</title>
    <link rel="stylesheet" href="admin.css">
</head>
<body>
    <?php include __DIR__ . '/nav.php'; ?>

    <main class="admin-main">
        <h1>Журнал дій</h1>

        <ul class="admin-list">
            <?php foreach ($rows as $row) : ?>
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
                    <?php if ($row['details']) : ?>
                        <p><?php echo htmlspecialchars($row['details']); ?></p>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
            <?php if (!$rows) : ?>
                <li class="admin-list-empty">Записів поки немає.</li>
            <?php endif; ?>
        </ul>

        <?php if ($totalPages > 1) : ?>
            <div class="admin-list-actions">
                <?php if ($page > 1) : ?>
                    <a href="activity.php?page=<?php echo $page - 1; ?>">← Новіші</a>
                <?php endif; ?>
                <span>Сторінка <?php echo $page; ?> з <?php echo $totalPages; ?></span>
                <?php if ($page < $totalPages) : ?>
                    <a href="activity.php?page=<?php echo $page + 1; ?>">Старіші →</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </main>
</body>
</html>

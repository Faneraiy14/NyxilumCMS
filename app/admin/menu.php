<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/activity.php';
require_login();

$db = get_db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    if ($action === 'create' || $action === 'update') {
        $label = trim($_POST['label'] ?? '');
        $url = trim($_POST['url'] ?? '');
        $sortOrder = (int) ($_POST['sort_order'] ?? 0);

        if ($label !== '' && $url !== '') {
            if ($action === 'create') {
                $stmt = $db->prepare('INSERT INTO menu_items (label, url, sort_order) VALUES (?, ?, ?)');
                $stmt->execute([$label, $url, $sortOrder]);
                log_activity('create', 'menu_item', (int) $db->lastInsertId(), $label);
            } else {
                $id = (int) ($_POST['id'] ?? 0);
                $stmt = $db->prepare('UPDATE menu_items SET label = ?, url = ?, sort_order = ? WHERE id = ?');
                $stmt->execute([$label, $url, $sortOrder, $id]);
                log_activity('update', 'menu_item', $id, $label);
            }
        }
    } elseif ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        $stmt = $db->prepare('DELETE FROM menu_items WHERE id = ?');
        $stmt->execute([$id]);
        log_activity('delete', 'menu_item', $id);
    }

    header('Location: menu.php');
    exit;
}

$editId = isset($_GET['edit']) ? (int) $_GET['edit'] : null;
$editItem = null;
if ($editId !== null) {
    $stmt = $db->prepare('SELECT * FROM menu_items WHERE id = ?');
    $stmt->execute([$editId]);
    $editItem = $stmt->fetch();
}

$items = $db->query('SELECT * FROM menu_items ORDER BY sort_order ASC, id ASC')->fetchAll();
?>
<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Меню — Nyxilum CMS</title>
    <link rel="stylesheet" href="admin.css">
</head>
<body>
    <?php include __DIR__ . '/nav.php'; ?>

    <main class="admin-main">
        <h1>Меню сайту</h1>

        <form class="admin-form" method="post" action="menu.php">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="<?php echo $editItem ? 'update' : 'create'; ?>">
            <?php if ($editItem) : ?>
                <input type="hidden" name="id" value="<?php echo (int) $editItem['id']; ?>">
            <?php endif; ?>

            <label>
                Назва пункту
                <input type="text" name="label" required value="<?php echo htmlspecialchars($editItem['label'] ?? ''); ?>">
            </label>

            <label>
                URL (напр. /про-нас або https://зовнішнє.посилання)
                <input type="text" name="url" required value="<?php echo htmlspecialchars($editItem['url'] ?? ''); ?>">
            </label>

            <label>
                Порядок (менше число — лівіше)
                <input type="number" name="sort_order" value="<?php echo (int) ($editItem['sort_order'] ?? 0); ?>">
            </label>

            <button type="submit"><?php echo $editItem ? 'Зберегти зміни' : 'Додати пункт'; ?></button>
            <?php if ($editItem) : ?>
                <a href="menu.php" class="cancel-link">Скасувати</a>
            <?php endif; ?>
        </form>

        <ul class="admin-list">
            <?php foreach ($items as $item) : ?>
                <li>
                    <div class="admin-list-item-header">
                        <strong><?php echo htmlspecialchars($item['label']); ?></strong>
                        <span class="admin-list-date"><?php echo htmlspecialchars($item['url']); ?> · #<?php echo (int) $item['sort_order']; ?></span>
                    </div>
                    <div class="admin-list-actions">
                        <a href="menu.php?edit=<?php echo (int) $item['id']; ?>">Редагувати</a>
                        <form method="post" action="menu.php" onsubmit="return confirm('Видалити пункт меню?');">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?php echo (int) $item['id']; ?>">
                            <button type="submit" class="delete-btn">Видалити</button>
                        </form>
                    </div>
                </li>
            <?php endforeach; ?>
            <?php if (!$items) : ?>
                <li class="admin-list-empty">Меню поки порожнє.</li>
            <?php endif; ?>
        </ul>
    </main>
</body>
</html>

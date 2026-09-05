<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/activity.php';
require_login();

$db = get_db();

function slugify_category(string $text): string
{
    $text = mb_strtolower(trim($text));
    $text = preg_replace('/[^\p{L}\p{N}]+/u', '-', $text);
    return trim($text, '-');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    if ($action === 'create' || $action === 'update') {
        $name = trim($_POST['name'] ?? '');
        $slug = trim($_POST['slug'] ?? '') ?: slugify_category($name);
        $description = trim($_POST['description'] ?? '') ?: null;

        if ($name === '' || $slug === '') {
            $error = 'Назва обов\'язкова.';
        } elseif ($action === 'create') {
            $stmt = $db->prepare('INSERT INTO categories (name, slug, description) VALUES (?, ?, ?)');
            $stmt->execute([$name, $slug, $description]);
            log_activity('create', 'category', (int) $db->lastInsertId(), $name);
        } else {
            $id = (int) ($_POST['id'] ?? 0);
            $stmt = $db->prepare('UPDATE categories SET name = ?, slug = ?, description = ? WHERE id = ?');
            $stmt->execute([$name, $slug, $description, $id]);
            log_activity('update', 'category', $id, $name);
        }
    } elseif ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        $nameStmt = $db->prepare('SELECT name FROM categories WHERE id = ?');
        $nameStmt->execute([$id]);
        $deletedName = $nameStmt->fetchColumn() ?: '';
        $db->prepare('DELETE FROM categories WHERE id = ?')->execute([$id]);
        log_activity('delete', 'category', $id, $deletedName);
    }

    if ($error === '') {
        header('Location: categories.php');
        exit;
    }
}

$editId = isset($_GET['edit']) ? (int) $_GET['edit'] : null;
$editItem = null;
if ($editId !== null) {
    $stmt = $db->prepare('SELECT * FROM categories WHERE id = ?');
    $stmt->execute([$editId]);
    $editItem = $stmt->fetch();
}

$categories = $db->query(
    'SELECT c.*, COUNT(cc.content_id) AS content_count
     FROM categories c LEFT JOIN content_categories cc ON cc.category_id = c.id
     GROUP BY c.id ORDER BY c.name'
)->fetchAll();
?>
<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Категорії — Nyxilum CMS</title>
    <link rel="stylesheet" href="admin.css">
</head>
<body>
    <?php include __DIR__ . '/nav.php'; ?>

    <main class="admin-main">
        <h1>Категорії</h1>

        <?php if ($error !== '') : ?>
            <p class="error"><?php echo htmlspecialchars($error); ?></p>
        <?php endif; ?>

        <form class="admin-form" method="post" action="categories.php">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="<?php echo $editItem ? 'update' : 'create'; ?>">
            <?php if ($editItem) : ?>
                <input type="hidden" name="id" value="<?php echo (int) $editItem['id']; ?>">
            <?php endif; ?>

            <label>
                Назва
                <input type="text" name="name" required value="<?php echo htmlspecialchars($editItem['name'] ?? ''); ?>">
            </label>

            <label>
                Slug (порожньо = згенерувати з назви)
                <input type="text" name="slug" value="<?php echo htmlspecialchars($editItem['slug'] ?? ''); ?>">
            </label>

            <label>
                Опис (необов'язково)
                <textarea name="description" rows="2"><?php echo htmlspecialchars($editItem['description'] ?? ''); ?></textarea>
            </label>

            <button type="submit"><?php echo $editItem ? 'Зберегти зміни' : 'Створити'; ?></button>
            <?php if ($editItem) : ?>
                <a href="categories.php" class="cancel-link">Скасувати</a>
            <?php endif; ?>
        </form>

        <ul class="admin-list">
            <?php foreach ($categories as $cat) : ?>
                <li>
                    <div class="admin-list-item-header">
                        <strong><?php echo htmlspecialchars($cat['name']); ?></strong>
                        <span class="admin-list-date">/<?php echo htmlspecialchars($cat['slug']); ?> · <?php echo (int) $cat['content_count']; ?> записів</span>
                    </div>
                    <?php if ($cat['description']) : ?>
                        <p><?php echo htmlspecialchars($cat['description']); ?></p>
                    <?php endif; ?>
                    <div class="admin-list-actions">
                        <a href="categories.php?edit=<?php echo (int) $cat['id']; ?>">Редагувати</a>
                        <a href="/category/<?php echo rawurlencode($cat['slug']); ?>" target="_blank">Переглянути</a>
                        <form method="post" action="categories.php" onsubmit="return confirm('Видалити категорію? Прив\'язка до контенту теж зникне.');">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?php echo (int) $cat['id']; ?>">
                            <button type="submit" class="delete-btn">Видалити</button>
                        </form>
                    </div>
                </li>
            <?php endforeach; ?>
            <?php if (!$categories) : ?>
                <li class="admin-list-empty">Категорій поки немає.</li>
            <?php endif; ?>
        </ul>
    </main>
</body>
</html>

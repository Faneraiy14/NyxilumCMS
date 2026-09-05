<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/activity.php';
require_login();

$db = get_db();

function slugify(string $text): string
{
    $text = mb_strtolower(trim($text));
    $text = preg_replace('/[^\p{L}\p{N}]+/u', '-', $text);
    return trim($text, '-');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    if ($action === 'create' || $action === 'update') {
        $type = trim($_POST['type'] ?? '') ?: 'page';
        $title = trim($_POST['title'] ?? '');
        $slug = trim($_POST['slug'] ?? '') ?: slugify($title);
        $lang = trim($_POST['lang'] ?? '') ?: 'uk';
        $metaTitle = trim($_POST['meta_title'] ?? '') ?: null;
        $metaDescription = trim($_POST['meta_description'] ?? '') ?: null;
        $body = $_POST['body'] ?? '';
        $status = ($_POST['status'] ?? 'draft') === 'published' ? 'published' : 'draft';

        if ($title !== '' && $slug !== '') {
            if ($action === 'create') {
                $stmt = $db->prepare('INSERT INTO content (type, slug, lang, title, meta_title, meta_description, body, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
                $stmt->execute([$type, $slug, $lang, $title, $metaTitle, $metaDescription, $body, $status]);
                log_activity('create', 'content', (int) $db->lastInsertId(), $title);
            } else {
                $id = (int) ($_POST['id'] ?? 0);
                $stmt = $db->prepare('UPDATE content SET type = ?, slug = ?, lang = ?, title = ?, meta_title = ?, meta_description = ?, body = ?, status = ? WHERE id = ?');
                $stmt->execute([$type, $slug, $lang, $title, $metaTitle, $metaDescription, $body, $status, $id]);
                log_activity('update', 'content', $id, $title);
            }
        }
    } elseif ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        $titleStmt = $db->prepare('SELECT title FROM content WHERE id = ?');
        $titleStmt->execute([$id]);
        $deletedTitle = $titleStmt->fetchColumn() ?: '';
        $stmt = $db->prepare('DELETE FROM content WHERE id = ?');
        $stmt->execute([$id]);
        log_activity('delete', 'content', $id, $deletedTitle);
    }

    header('Location: content.php');
    exit;
}

$editId = isset($_GET['edit']) ? (int) $_GET['edit'] : null;
$editItem = null;
if ($editId !== null) {
    $stmt = $db->prepare('SELECT * FROM content WHERE id = ?');
    $stmt->execute([$editId]);
    $editItem = $stmt->fetch();
}

// Фільтри за типом/статусом (з дашборду чи вручну в URL) - обидва
// необов'язкові, порожній фільтр = показати все.
$filterType = trim($_GET['type'] ?? '');
$filterStatus = trim($_GET['status'] ?? '');

$where = [];
$params = [];
if ($filterType !== '') {
    $where[] = 'type = ?';
    $params[] = $filterType;
}
if ($filterStatus !== '') {
    $where[] = 'status = ?';
    $params[] = $filterStatus;
}
$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$stmt = $db->prepare("SELECT * FROM content $whereSql ORDER BY updated_at DESC");
$stmt->execute($params);
$items = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Контент — Nyxilum CMS</title>
    <link rel="stylesheet" href="admin.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/quill/1.3.6/quill.snow.min.css" rel="stylesheet">
    <style>
        #editor-content { background: #fff; border-radius: 0 0 8px 8px; min-height: 200px; }
        #editor-toolbar { border-radius: 8px 8px 0 0; }
    </style>
</head>
<body>
    <?php include __DIR__ . '/nav.php'; ?>

    <main class="admin-main">
        <h1>Контент<?php echo $filterType !== '' ? ': ' . htmlspecialchars($filterType) : ''; ?></h1>

        <form class="admin-form" method="post" action="content.php">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="<?php echo $editItem ? 'update' : 'create'; ?>">
            <?php if ($editItem) : ?>
                <input type="hidden" name="id" value="<?php echo (int) $editItem['id']; ?>">
            <?php endif; ?>

            <label>
                Тип (довільний - page, post, і т.д.)
                <input type="text" name="type" required value="<?php echo htmlspecialchars($editItem['type'] ?? 'page'); ?>">
            </label>

            <label>
                Заголовок
                <input type="text" name="title" required value="<?php echo htmlspecialchars($editItem['title'] ?? ''); ?>">
            </label>

            <label>
                Slug (порожньо = згенерувати з заголовка)
                <input type="text" name="slug" value="<?php echo htmlspecialchars($editItem['slug'] ?? ''); ?>">
            </label>

            <label>
                Мова
                <input type="text" name="lang" value="<?php echo htmlspecialchars($editItem['lang'] ?? 'uk'); ?>">
            </label>

            <label>
                SEO-заголовок (порожньо = звичайний заголовок)
                <input type="text" name="meta_title" value="<?php echo htmlspecialchars($editItem['meta_title'] ?? ''); ?>">
            </label>

            <label>
                SEO-опис (для пошуковиків, ~150-160 символів)
                <textarea name="meta_description" rows="2"><?php echo htmlspecialchars($editItem['meta_description'] ?? ''); ?></textarea>
            </label>

            <label>
                Вміст
                <div id="editor-toolbar"></div>
                <div id="editor-content"></div>
                <textarea name="body" id="body-field" style="display:none;"><?php echo htmlspecialchars($editItem['body'] ?? ''); ?></textarea>
            </label>

            <label>
                Статус
                <select name="status">
                    <?php $curStatus = $editItem['status'] ?? 'draft'; ?>
                    <option value="draft" <?php echo $curStatus === 'draft' ? 'selected' : ''; ?>>Чернетка</option>
                    <option value="published" <?php echo $curStatus === 'published' ? 'selected' : ''; ?>>Опубліковано</option>
                </select>
            </label>

            <button type="submit"><?php echo $editItem ? 'Зберегти зміни' : 'Створити'; ?></button>
            <?php if ($editItem) : ?>
                <a href="content.php" class="cancel-link">Скасувати</a>
            <?php endif; ?>
        </form>

        <ul class="admin-list">
            <?php foreach ($items as $item) : ?>
                <li>
                    <div class="admin-list-item-header">
                        <strong><?php echo htmlspecialchars($item['title']); ?></strong>
                        <span class="admin-list-date">
                            <?php echo htmlspecialchars($item['type']); ?> · <?php echo htmlspecialchars($item['status']); ?> · <?php echo htmlspecialchars($item['updated_at']); ?>
                        </span>
                    </div>
                    <p>/<?php echo htmlspecialchars($item['slug']); ?></p>
                    <div class="admin-list-actions">
                        <a href="content.php?edit=<?php echo (int) $item['id']; ?>">Редагувати</a>
                        <a href="/preview/<?php echo rawurlencode($item['slug']); ?>" target="_blank">Переглянути</a>
                        <form method="post" action="content.php" onsubmit="return confirm('Видалити?');">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?php echo (int) $item['id']; ?>">
                            <button type="submit" class="delete-btn">Видалити</button>
                        </form>
                    </div>
                </li>
            <?php endforeach; ?>
            <?php if (!$items) : ?>
                <li class="admin-list-empty">Нічого немає.</li>
            <?php endif; ?>
        </ul>
    </main>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/quill/1.3.6/quill.min.js"></script>
    <script>
        // Quill сам створює й вставляє тулбар перед #editor-content, коли
        // toolbar - масив груп кнопок (а не готовий DOM-контейнер) - тому
        // окремий порожній #editor-toolbar не потрібен, Quill малює туди.
        const quill = new Quill('#editor-content', {
            theme: 'snow',
            modules: {
                toolbar: [
                    ['bold', 'italic', 'underline'],
                    ['link', 'image'],
                    [{ list: 'ordered' }, { list: 'bullet' }],
                    [{ header: [1, 2, 3, false] }],
                ],
            },
        });
        const bodyField = document.getElementById('body-field');
        if (bodyField.value) {
            quill.root.innerHTML = bodyField.value;
        }
        document.querySelector('.admin-form').addEventListener('submit', () => {
            bodyField.value = quill.root.innerHTML;
        });
    </script>
</body>
</html>

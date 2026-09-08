<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/activity.php';
require_login();

// Доступно editor'у теж, не лише admin - той самий рівень, що й
// content.php/media.php (модерація коментарів - рутинна редакторська
// дія, не адмін-only, як settings.php/users.php).
$db = get_db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $id = (int) ($_POST['id'] ?? 0);
    if ($id > 0) {
        $db->prepare('DELETE FROM comments WHERE id = ?')->execute([$id]);
        log_activity('delete', 'comment', $id);
    }
    header('Location: comments.php');
    exit;
}

$items = $db->query(
    'SELECT c.id, c.body, c.created_at, u.username, content.title AS content_title, content.slug AS content_slug
     FROM comments c
     JOIN site_users u ON u.id = c.site_user_id
     JOIN content ON content.id = c.content_id
     ORDER BY c.created_at DESC'
)->fetchAll();
?>
<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Коментарі — Nyxilum CMS</title>
    <link rel="stylesheet" href="admin.css">
</head>
<body>
    <?php include __DIR__ . '/nav.php'; ?>

    <main class="admin-main">
        <h1>Коментарі</h1>

        <ul class="admin-list">
            <?php foreach ($items as $item) : ?>
                <li>
                    <div class="admin-list-item-header">
                        <strong><?php echo htmlspecialchars($item['username']); ?></strong>
                        <span class="admin-list-date">
                            на <?php echo htmlspecialchars($item['content_title']); ?>
                            · <?php echo htmlspecialchars(date('d.m.Y H:i', strtotime($item['created_at']))); ?>
                        </span>
                    </div>
                    <p><?php echo nl2br(htmlspecialchars($item['body'])); ?></p>
                    <div class="admin-list-actions">
                        <a href="/<?php echo rawurlencode($item['content_slug']); ?>" target="_blank">Переглянути</a>
                        <form method="post" action="comments.php" onsubmit="return confirm('Видалити коментар?');">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="id" value="<?php echo (int) $item['id']; ?>">
                            <button type="submit" class="delete-btn">Видалити</button>
                        </form>
                    </div>
                </li>
            <?php endforeach; ?>
            <?php if (!$items) : ?>
                <li class="admin-list-empty">Коментарів поки немає.</li>
            <?php endif; ?>
        </ul>
    </main>
</body>
</html>

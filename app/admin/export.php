<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_login();
require_role('admin');

$db = get_db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $data = [
        'exported_at' => date('c'),
        'content' => $db->query('SELECT * FROM content')->fetchAll(),
        'menu_items' => $db->query('SELECT * FROM menu_items')->fetchAll(),
        'media' => $db->query('SELECT * FROM media')->fetchAll(),
        'settings' => $db->query('SELECT * FROM settings')->fetchAll(),
        // Паролі йдуть як bcrypt-хеші (не відновлювані), не сирий текст -
        // але файл однаково варто тримати приватним, це реальний бекап
        // акаунтів, а не публічний дамп.
        'admin_users' => $db->query('SELECT * FROM admin_users')->fetchAll(),
    ];

    $tmpTar = tempnam(sys_get_temp_dir(), 'nyxilum-export-') . '.tar';
    $archive = new PharData($tmpTar);
    $archive->addFromString('data.json', json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    $uploadsDir = __DIR__ . '/../uploads';
    if (is_dir($uploadsDir)) {
        foreach (scandir($uploadsDir) as $file) {
            if ($file === '.' || $file === '..' || $file === '.gitkeep') {
                continue;
            }
            $archive->addFile($uploadsDir . '/' . $file, 'uploads/' . $file);
        }
    }

    $filename = 'nyxilum-cms-export-' . date('Y-m-d') . '.tar';
    header('Content-Type: application/x-tar');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . filesize($tmpTar));
    readfile($tmpTar);
    unlink($tmpTar);
    exit;
}
?>
<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Експорт — Nyxilum CMS</title>
    <link rel="stylesheet" href="admin.css">
</head>
<body>
    <?php include __DIR__ . '/nav.php'; ?>

    <main class="admin-main">
        <h1>Експорт сайту</h1>
        <p style="color: var(--muted);">
            Один .tar-файл з усім контентом, меню, медіафайлами, налаштуваннями й акаунтами адмінів.
            Тримай приватно - там реальні (хоч і хешовані) дані акаунтів.
        </p>
        <form class="admin-form" method="post" action="export.php">
            <?php echo csrf_field(); ?>
            <button type="submit">Завантажити повний бекап (.tar)</button>
        </form>
    </main>
</body>
</html>

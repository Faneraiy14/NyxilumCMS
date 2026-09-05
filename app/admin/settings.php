<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/activity.php';
require_login();
require_role('admin');

$db = get_db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $siteName = trim($_POST['site_name'] ?? '');
    $defaultLang = trim($_POST['default_lang'] ?? 'uk');

    if ($siteName !== '') {
        $stmt = $db->prepare('UPDATE settings SET setting_value = ? WHERE setting_key = ?');
        $stmt->execute([$siteName, 'site_name']);
        $stmt->execute([$defaultLang, 'default_lang']);
        log_activity('update', 'settings', null, "site_name={$siteName}, default_lang={$defaultLang}");
    }

    header('Location: settings.php');
    exit;
}

$rows = $db->query('SELECT setting_key, setting_value FROM settings')->fetchAll();
$settings = [];
foreach ($rows as $row) {
    $settings[$row['setting_key']] = $row['setting_value'];
}
?>
<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Налаштування — Nyxilum CMS</title>
    <link rel="stylesheet" href="admin.css">
</head>
<body>
    <?php include __DIR__ . '/nav.php'; ?>

    <main class="admin-main">
        <h1>Налаштування сайту</h1>

        <form class="admin-form" method="post" action="settings.php">
            <?php echo csrf_field(); ?>

            <label>
                Назва сайту
                <input type="text" name="site_name" required value="<?php echo htmlspecialchars($settings['site_name'] ?? ''); ?>">
            </label>

            <label>
                Мова за замовчуванням
                <input type="text" name="default_lang" value="<?php echo htmlspecialchars($settings['default_lang'] ?? 'uk'); ?>">
            </label>

            <button type="submit">Зберегти</button>
        </form>
    </main>
</body>
</html>

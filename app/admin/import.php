<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_login();
require_role('admin');

$db = get_db();
$error = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    if (!isset($_FILES['archive']) || $_FILES['archive']['error'] !== UPLOAD_ERR_OK) {
        $error = 'Файл не завантажився.';
    } else {
        $tmpDir = sys_get_temp_dir() . '/nyxilum-import-' . bin2hex(random_bytes(8));
        mkdir($tmpDir);
        try {
            $tarPath = $tmpDir . '/import.tar';
            move_uploaded_file($_FILES['archive']['tmp_name'], $tarPath);

            $archive = new PharData($tarPath);
            $archive->extractTo($tmpDir);

            $dataPath = $tmpDir . '/data.json';
            if (!is_file($dataPath)) {
                throw new Exception('У архіві нема data.json - це не експорт Nyxilum CMS.');
            }
            $data = json_decode(file_get_contents($dataPath), true);
            if (!$data) {
                throw new Exception('data.json пошкоджений або порожній.');
            }

            // Повна заміна, не злиття - саме тому потрібне явне підтвердження
            // на формі нижче (checkbox), а не просто кнопка "Імпортувати".
            $db->beginTransaction();
            foreach (['content', 'menu_items', 'media', 'settings', 'admin_users'] as $table) {
                $db->exec("DELETE FROM {$table}");
            }
            foreach ($data['content'] ?? [] as $row) {
                $cols = implode(', ', array_keys($row));
                $ph = implode(', ', array_fill(0, count($row), '?'));
                $db->prepare("INSERT INTO content ({$cols}) VALUES ({$ph})")->execute(array_values($row));
            }
            foreach ($data['menu_items'] ?? [] as $row) {
                $cols = implode(', ', array_keys($row));
                $ph = implode(', ', array_fill(0, count($row), '?'));
                $db->prepare("INSERT INTO menu_items ({$cols}) VALUES ({$ph})")->execute(array_values($row));
            }
            foreach ($data['media'] ?? [] as $row) {
                $cols = implode(', ', array_keys($row));
                $ph = implode(', ', array_fill(0, count($row), '?'));
                $db->prepare("INSERT INTO media ({$cols}) VALUES ({$ph})")->execute(array_values($row));
            }
            foreach ($data['settings'] ?? [] as $row) {
                $db->prepare('INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)')
                    ->execute([$row['setting_key'], $row['setting_value']]);
            }
            foreach ($data['admin_users'] ?? [] as $row) {
                $cols = implode(', ', array_keys($row));
                $ph = implode(', ', array_fill(0, count($row), '?'));
                $db->prepare("INSERT INTO admin_users ({$cols}) VALUES ({$ph})")->execute(array_values($row));
            }
            $db->commit();

            $uploadsDir = __DIR__ . '/../uploads';
            if (is_dir($tmpDir . '/uploads')) {
                foreach (scandir($tmpDir . '/uploads') as $file) {
                    if ($file === '.' || $file === '..') {
                        continue;
                    }
                    copy($tmpDir . '/uploads/' . $file, $uploadsDir . '/' . $file);
                }
            }

            $success = true;
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            $error = $e->getMessage();
        } finally {
            array_map('unlink', glob("$tmpDir/*") ?: []);
            array_map('unlink', glob("$tmpDir/uploads/*") ?: []);
            @rmdir($tmpDir . '/uploads');
            @rmdir($tmpDir);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Імпорт — Nyxilum CMS</title>
    <link rel="stylesheet" href="admin.css">
</head>
<body>
    <?php include __DIR__ . '/nav.php'; ?>

    <main class="admin-main">
        <h1>Імпорт сайту</h1>

        <?php if ($success) : ?>
            <p style="color: green;">Готово - дані відновлено. Якщо змінився список адмінів, можливо доведеться увійти заново.</p>
        <?php endif; ?>
        <?php if ($error !== '') : ?>
            <p class="error"><?php echo htmlspecialchars($error); ?></p>
        <?php endif; ?>

        <p style="color: var(--danger);">
            <strong>Увага:</strong> імпорт ПОВНІСТЮ замінює поточний контент, меню, медіа, налаштування й акаунти адмінів
            даними з файлу. Це незворотно для того, що є зараз.
        </p>

        <form class="admin-form" method="post" action="import.php" enctype="multipart/form-data">
            <?php echo csrf_field(); ?>
            <label>
                Файл бекапу (.tar)
                <input type="file" name="archive" accept=".tar" required>
            </label>
            <label style="flex-direction: row; align-items: center;">
                <input type="checkbox" required style="width: auto;">
                Розумію, що це замінить поточні дані
            </label>
            <button type="submit">Імпортувати</button>
        </form>
    </main>
</body>
</html>

<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/activity.php';
require_login();

$db = get_db();
$uploadDir = __DIR__ . '/../uploads';

// Дозволені типи - звіряємо РЕАЛЬНИЙ вміст файлу через finfo, а не
// $_FILES[...]['type'] (це просто заголовок, який надсилає браузер -
// його легко підмінити, довіряти йому не можна).
const ALLOWED_MIME = [
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/gif' => 'gif',
    'image/webp' => 'webp',
];
const MAX_SIZE = 5 * 1024 * 1024; // 5 МБ

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    if ($action === 'upload' && isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
        $tmpPath = $_FILES['file']['tmp_name'];
        $size = $_FILES['file']['size'];

        if ($size > MAX_SIZE) {
            $error = 'Файл завеликий (максимум 5 МБ).';
        } else {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $tmpPath);
            finfo_close($finfo);

            if (!isset(ALLOWED_MIME[$mime])) {
                $error = 'Дозволені лише зображення (JPEG, PNG, GIF, WebP).';
            } else {
                // Випадкове ім'я на диску - НІКОЛИ не довіряємо оригінальній
                // назві файлу для шляху (обхід каталогів, перезапис чужого
                // файлу, спроби підсунути виконуваний файл під картинку).
                $ext = ALLOWED_MIME[$mime];
                $filename = bin2hex(random_bytes(16)) . '.' . $ext;
                $originalName = basename($_FILES['file']['name']);

                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }

                if (move_uploaded_file($tmpPath, $uploadDir . '/' . $filename)) {
                    $stmt = $db->prepare('INSERT INTO media (filename, original_name, mime_type, size_bytes) VALUES (?, ?, ?, ?)');
                    $stmt->execute([$filename, $originalName, $mime, $size]);
                    log_activity('upload', 'media', (int) $db->lastInsertId(), $originalName);
                } else {
                    $error = 'Не вдалось зберегти файл.';
                }
            }
        }
    } elseif ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        $stmt = $db->prepare('SELECT filename FROM media WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if ($row) {
            $path = $uploadDir . '/' . $row['filename'];
            if (is_file($path)) {
                unlink($path);
            }
            $db->prepare('DELETE FROM media WHERE id = ?')->execute([$id]);
            log_activity('delete', 'media', $id, $row['filename']);
        }
    }

    if ($error === '') {
        header('Location: media.php');
        exit;
    }
}

$items = $db->query('SELECT * FROM media ORDER BY uploaded_at DESC')->fetchAll();
?>
<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Медіа — Nyxilum CMS</title>
    <link rel="stylesheet" href="admin.css">
</head>
<body>
    <?php include __DIR__ . '/nav.php'; ?>

    <main class="admin-main">
        <h1>Медіа</h1>

        <?php if ($error !== '') : ?>
            <p class="error"><?php echo htmlspecialchars($error); ?></p>
        <?php endif; ?>

        <form class="admin-form" method="post" action="media.php" enctype="multipart/form-data">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="upload">
            <label>
                Зображення (JPEG/PNG/GIF/WebP, до 5 МБ)
                <input type="file" name="file" accept="image/*" required>
            </label>
            <button type="submit">Завантажити</button>
        </form>

        <div class="media-grid">
            <?php foreach ($items as $item) : ?>
                <div class="media-item">
                    <img src="/uploads/<?php echo htmlspecialchars($item['filename']); ?>" alt="<?php echo htmlspecialchars($item['original_name']); ?>">
                    <div class="media-url" onclick="navigator.clipboard.writeText(this.textContent.trim())" title="Клікни, щоб скопіювати">/uploads/<?php echo htmlspecialchars($item['filename']); ?></div>
                    <form method="post" action="media.php" onsubmit="return confirm('Видалити файл?');">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?php echo (int) $item['id']; ?>">
                        <button type="submit" class="delete-btn">Видалити</button>
                    </form>
                </div>
            <?php endforeach; ?>
            <?php if (!$items) : ?>
                <p class="admin-list-empty">Медіафайлів поки немає.</p>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>

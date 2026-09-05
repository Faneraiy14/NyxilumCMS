<?php
require_once __DIR__ . '/includes/db.php';

// Захист: якщо конфіг уже є, встановлювач більше не має ЖОДНОГО шансу
// щось виконати - інакше випадковий повторний запуск міг би спробувати
// пересоздати таблиці на вже робочому сайті з реальними даними.
if (is_installed()) {
    http_response_code(403);
    echo 'Nyxilum CMS вже встановлено. Видали app/config.php вручну, якщо справді хочеш встановити заново (це НЕ видаляє саму базу даних).';
    exit;
}

$error = '';
$step = 1;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dbHost = trim($_POST['db_host'] ?? 'localhost');
    $dbName = trim($_POST['db_name'] ?? '');
    $dbUser = trim($_POST['db_user'] ?? '');
    $dbPass = $_POST['db_pass'] ?? '';
    $siteName = trim($_POST['site_name'] ?? 'Nyxilum CMS');
    $adminUser = trim($_POST['admin_user'] ?? '');
    $adminPass = $_POST['admin_pass'] ?? '';

    if ($dbName === '' || $dbUser === '' || $adminUser === '') {
        $error = 'Заповни назву бази, користувача БД і логін адміна.';
    } elseif (strlen($adminPass) < 8) {
        $error = 'Пароль адміна - мінімум 8 символів.';
    } else {
        try {
            $pdo = new PDO(
                "mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4",
                $dbUser,
                $dbPass,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );

            // Якщо таблиці вже є (напр. повторний запуск після невдалого
            // кроку) - не падаємо на "table already exists", а просто
            // пропускаємо створення. is_installed() вище вже захищає від
            // ЗАПУСКУ встановлювача на живому сайті, це - додаткова
            // страховка в межах самого встановлення.
            $tableCheck = $pdo->query("SHOW TABLES LIKE 'admin_users'")->fetch();
            if (!$tableCheck) {
                $sql = file_get_contents(__DIR__ . '/db/tables.sql');
                foreach (array_filter(array_map('trim', explode(';', $sql))) as $statement) {
                    $pdo->exec($statement);
                }
            }

            $hash = password_hash($adminPass, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare('INSERT INTO admin_users (username, password_hash, role) VALUES (?, ?, ?)');
            $stmt->execute([$adminUser, $hash, 'admin']);

            $stmt = $pdo->prepare('UPDATE settings SET setting_value = ? WHERE setting_key = ?');
            $stmt->execute([$siteName, 'site_name']);

            $configContent = "<?php\n"
                . "define('DB_HOST', " . var_export($dbHost, true) . ");\n"
                . "define('DB_NAME', " . var_export($dbName, true) . ");\n"
                . "define('DB_USER', " . var_export($dbUser, true) . ");\n"
                . "define('DB_PASS', " . var_export($dbPass, true) . ");\n";
            file_put_contents(CONFIG_PATH, $configContent);

            header('Location: admin/login.php');
            exit;
        } catch (PDOException $e) {
            $error = 'Помилка бази даних: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Встановлення — Nyxilum CMS</title>
    <link rel="stylesheet" href="admin/admin.css">
</head>
<body class="login-page">
    <form class="login-form" method="post" action="install.php" style="width: min(420px, calc(100% - 32px));">
        <h1>Встановлення Nyxilum CMS</h1>
        <p style="color: var(--muted); font-size: 0.85rem; margin-top: -8px;">
            Базу даних і користувача БД треба створити заздалегідь (адміністратором сервера) - встановлювач лише застосовує до неї таблиці, не створює саму базу.
        </p>

        <?php if ($error !== '') : ?>
            <p class="error"><?php echo htmlspecialchars($error); ?></p>
        <?php endif; ?>

        <label>Назва сайту <input type="text" name="site_name" value="Nyxilum CMS" required></label>

        <hr style="border-color: var(--border); width: 100%;">

        <label>Хост БД <input type="text" name="db_host" value="localhost" required></label>
        <label>Назва бази даних <input type="text" name="db_name" required></label>
        <label>Користувач БД <input type="text" name="db_user" required></label>
        <label>Пароль БД <input type="password" name="db_pass"></label>

        <hr style="border-color: var(--border); width: 100%;">

        <label>Логін адміністратора <input type="text" name="admin_user" required></label>
        <label>Пароль адміністратора (мін. 8 символів) <input type="password" name="admin_pass" required minlength="8"></label>

        <button type="submit">Встановити</button>
    </form>
</body>
</html>

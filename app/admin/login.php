<?php
require_once __DIR__ . '/../includes/auth.php';

start_admin_session();

if (is_logged_in()) {
    header('Location: index.php');
    exit;
}

if (has_pending_2fa()) {
    header('Location: verify-2fa.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    $result = attempt_login($username, $password);
    if ($result === 'ok') {
        header('Location: index.php');
        exit;
    }
    if ($result === 'need_2fa') {
        header('Location: verify-2fa.php');
        exit;
    }

    $error = 'Невірний логін або пароль.';
}
?>
<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Вхід — Nyxilum CMS</title>
    <link rel="stylesheet" href="admin.css">
</head>
<body class="login-page">
    <form class="login-form" method="post" action="login.php">
        <h1>Nyxilum CMS</h1>

        <?php if ($error !== '') : ?>
            <p class="error"><?php echo htmlspecialchars($error); ?></p>
        <?php endif; ?>

        <label>
            Логін
            <input type="text" name="username" autofocus required>
        </label>

        <label>
            Пароль
            <input type="password" name="password" required>
        </label>

        <button type="submit">Увійти</button>
    </form>
</body>
</html>

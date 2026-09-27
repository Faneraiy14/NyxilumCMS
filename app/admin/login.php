<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';

start_admin_session();

// Об'єднана система акаунтів (27.09.2026) - ця сторінка тепер спільна
// для панелі керування (admin/editor) І звичайних відвідувачів (роль
// user), що лише коментують на сайті - куди саме вести після входу
// залежить від ролі, а не однаково для всіх, як було раніше.
function redirect_after_login(): void
{
    header('Location: ' . (in_array(current_role(), ['admin', 'editor'], true) ? 'index.php' : '/'));
    exit;
}

if (is_logged_in()) {
    redirect_after_login();
}

if (has_pending_2fa()) {
    header('Location: verify-2fa.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    $result = attempt_login($username, $password);
    if ($result === 'ok') {
        redirect_after_login();
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
        <?php echo csrf_field(); ?>
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
        <p><a href="register.php" class="cancel-link">Зареєструватись</a> - для звичайних відвідувачів (лишати коментарі)</p>
    </form>
</body>
</html>

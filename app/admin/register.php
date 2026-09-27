<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';

// ПУБЛІЧНА сторінка - навмисно НЕМАЄ require_login()/require_admin_panel()
// на початку, на відміну від решти файлів у цій теці. Реєстрація завжди
// створює роль 'user' (жорстко в register_account(), не звідси) - вище
// не піднятись самому, лише власник сайту може підвищити роль пізніше
// через admin/users.php.
start_admin_session();

if (is_logged_in()) {
    header('Location: ' . (in_array(current_role(), ['admin', 'editor'], true) ? 'index.php' : '/'));
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $error = register_account((string) ($_POST['username'] ?? ''), (string) ($_POST['password'] ?? ''));
    if ($error === '') {
        header('Location: /');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Реєстрація — Nyxilum CMS</title>
    <link rel="stylesheet" href="admin.css">
</head>
<body class="login-page">
    <form class="login-form" method="post" action="register.php">
        <?php echo csrf_field(); ?>
        <h1>Nyxilum CMS</h1>

        <?php if ($error !== '') : ?>
            <p class="error"><?php echo htmlspecialchars($error); ?></p>
        <?php endif; ?>

        <label>
            Логін
            <input type="text" name="username" pattern="[a-zA-Z0-9_]{3,32}" title="3-32 символи: латинські літери, цифри, підкреслення" autofocus required>
        </label>

        <label>
            Пароль
            <input type="password" name="password" minlength="8" required>
        </label>

        <button type="submit">Зареєструватись</button>
        <p><a href="login.php" class="cancel-link">Вже маєте акаунт? Увійти</a></p>
    </form>
</body>
</html>

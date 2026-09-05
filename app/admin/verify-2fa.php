<?php
require_once __DIR__ . '/../includes/auth.php';

start_admin_session();

if (is_logged_in()) {
    header('Location: index.php');
    exit;
}

if (!has_pending_2fa()) {
    header('Location: login.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = trim($_POST['code'] ?? '');

    if (verify_2fa_code($code)) {
        header('Location: index.php');
        exit;
    }

    $error = has_pending_2fa()
        ? 'Невірний код. Спробуй ще раз.'
        : 'Забагато невдалих спроб - увійди заново.';
}
?>
<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Код підтвердження — Nyxilum CMS</title>
    <link rel="stylesheet" href="admin.css">
</head>
<body class="login-page">
    <form class="login-form" method="post" action="verify-2fa.php">
        <h1>Nyxilum CMS</h1>
        <p>Введи код з додатка-автентифікатора.</p>

        <?php if ($error !== '') : ?>
            <p class="error"><?php echo htmlspecialchars($error); ?></p>
        <?php endif; ?>

        <label>
            Код
            <input type="text" name="code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6"
                   autocomplete="one-time-code" autofocus required>
        </label>

        <button type="submit">Підтвердити</button>
    </form>
</body>
</html>

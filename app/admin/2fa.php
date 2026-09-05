<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_login();

$db = get_db();
$myId = (int) $_SESSION['admin_id'];

$stmt = $db->prepare('SELECT username, totp_secret, totp_enabled FROM admin_users WHERE id = ?');
$stmt->execute([$myId]);
$me = $stmt->fetch();

$error = '';
$freshlyEnabled = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    if ($action === 'start') {
        // Новий секрет при кожному "Увімкнути" - безпечно, бо totp_enabled
        // лишається 0, доки не підтвердять кодом з додатка.
        $secret = totp_generate_secret();
        $db->prepare('UPDATE admin_users SET totp_secret = ?, totp_enabled = 0 WHERE id = ?')->execute([$secret, $myId]);
        $me['totp_secret'] = $secret;
        $me['totp_enabled'] = 0;
    } elseif ($action === 'confirm') {
        $code = trim($_POST['code'] ?? '');
        if ($me['totp_secret'] && totp_verify($me['totp_secret'], $code)) {
            $db->prepare('UPDATE admin_users SET totp_enabled = 1 WHERE id = ?')->execute([$myId]);
            $me['totp_enabled'] = 1;
            $freshlyEnabled = true;
            log_activity('enable_2fa', 'user', $myId, $me['username']);
        } else {
            $error = 'Невірний код. Спробуй ще раз.';
        }
    } elseif ($action === 'disable') {
        $db->prepare('UPDATE admin_users SET totp_secret = NULL, totp_enabled = 0 WHERE id = ?')->execute([$myId]);
        $me['totp_secret'] = null;
        $me['totp_enabled'] = 0;
        log_activity('disable_2fa', 'user', $myId, $me['username']);
    }
}

$otpUri = $me['totp_secret'] ? totp_uri($me['totp_secret'], $me['username']) : '';
?>
<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>2FA — Nyxilum CMS</title>
    <link rel="stylesheet" href="admin.css">
</head>
<body>
    <?php include __DIR__ . '/nav.php'; ?>

    <main class="admin-main">
        <h1>Двофакторна автентифікація</h1>

        <?php if ($error !== '') : ?>
            <p class="error"><?php echo htmlspecialchars($error); ?></p>
        <?php endif; ?>

        <?php if ((int) $me['totp_enabled'] === 1) : ?>
            <?php if ($freshlyEnabled) : ?>
                <p class="success">2FA увімкнено. Тепер після пароля буде питати код з додатка.</p>
            <?php else : ?>
                <p>2FA зараз <strong>увімкнена</strong> для акаунта «<?php echo htmlspecialchars($me['username']); ?>».</p>
            <?php endif; ?>

            <form class="admin-form" method="post" action="2fa.php" onsubmit="return confirm('Вимкнути 2FA для свого акаунта?');">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="disable">
                <button type="submit" class="delete-btn">Вимкнути 2FA</button>
            </form>
        <?php elseif ($me['totp_secret']) : ?>
            <p>Відскануй QR-код у додатку-автентифікаторі (Google Authenticator, Aegis і т.д.),
               потім введи згенерований код нижче, щоб підтвердити.</p>

            <div id="qr-code"></div>
            <p>Або введи ключ вручну: <code><?php echo htmlspecialchars($me['totp_secret']); ?></code></p>

            <form class="admin-form" method="post" action="2fa.php">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="confirm">
                <label>
                    Код з додатка
                    <input type="text" name="code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6"
                           autocomplete="one-time-code" autofocus required>
                </label>
                <button type="submit">Підтвердити й увімкнути</button>
            </form>

            <form class="admin-form" method="post" action="2fa.php">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="start">
                <button type="submit">Згенерувати новий код (якщо не встиг відсканувати)</button>
            </form>

            <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
            <script>
                new QRCode(document.getElementById('qr-code'), {
                    text: <?php echo json_encode($otpUri); ?>,
                    width: 200,
                    height: 200,
                });
            </script>
        <?php else : ?>
            <p>2FA зараз <strong>вимкнена</strong>. Увімкнення додасть другий крок при вході -
               код з додатка-автентифікатора на телефоні, окрім пароля.</p>

            <form class="admin-form" method="post" action="2fa.php">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="start">
                <button type="submit">Увімкнути 2FA</button>
            </form>
        <?php endif; ?>
    </main>
</body>
</html>

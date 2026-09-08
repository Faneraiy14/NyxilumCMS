<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php'; // start_admin_session() - той самий PHP-сеанс/кукі, лише свій ключ у $_SESSION

// Акаунти ВІДВІДУВАЧІВ сайту (реєстрація/коментарі) - геть окрема
// система від admin_users/auth.php: свій ключ сесії (site_user_id, не
// admin_id), тож людина теоретично може бути залогінена в адмінку І
// мати окремий акаунт відвідувача одночасно, без конфлікту.

function site_register(string $username, string $email, string $password): string
{
    $username = trim($username);
    $email = strtolower(trim($email));

    if (!preg_match('/^[a-zA-Z0-9_]{3,32}$/', $username)) {
        return 'Логін - 3-32 символи, лише латинські літери/цифри/підкреслення.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return 'Некоректний email.';
    }
    if (strlen($password) < 8) {
        return 'Пароль має бути щонайменше 8 символів.';
    }

    $db = get_db();
    $stmt = $db->prepare('SELECT id FROM site_users WHERE username = ? OR email = ?');
    $stmt->execute([$username, $email]);
    if ($stmt->fetch()) {
        return 'Цей логін або email вже зареєстровано.';
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);
    $db->prepare('INSERT INTO site_users (username, email, password_hash) VALUES (?, ?, ?)')
        ->execute([$username, $email, $hash]);

    site_login_as((int) $db->lastInsertId(), $username);
    return '';
}

// Логін чи email в одному полі - зручніше для людини, не треба
// пам'ятати, яким саме реєструвався.
function site_attempt_login(string $usernameOrEmail, string $password): bool
{
    $usernameOrEmail = trim($usernameOrEmail);
    $stmt = get_db()->prepare('SELECT id, username, password_hash FROM site_users WHERE username = ? OR email = ?');
    $stmt->execute([$usernameOrEmail, strtolower($usernameOrEmail)]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        return false;
    }

    site_login_as((int) $user['id'], $user['username']);
    return true;
}

function site_login_as(int $id, string $username): void
{
    start_admin_session();
    session_regenerate_id(true);
    $_SESSION['site_user_id'] = $id;
    $_SESSION['site_username'] = $username;
}

/** @return array{id: int, username: string}|null */
function current_site_user(): ?array
{
    start_admin_session();
    if (empty($_SESSION['site_user_id'])) {
        return null;
    }
    return ['id' => (int) $_SESSION['site_user_id'], 'username' => (string) $_SESSION['site_username']];
}

function site_logout(): void
{
    start_admin_session();
    unset($_SESSION['site_user_id'], $_SESSION['site_username']);
}

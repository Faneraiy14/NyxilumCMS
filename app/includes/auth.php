<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/activity.php';
require_once __DIR__ . '/totp.php';

// Тайм-аут сесії - 2 години бездіяльності. Без цього залогінена сесія
// жила б вічно (поки сам не натиснеш "Вийти") - якщо хтось отримає
// доступ до відкритого браузера пізніше, він і так залишиться
// залогінений в адмінку.
const SESSION_TIMEOUT = 7200;

// Скільки часу є на введення 2FA-коду після вірного пароля, і скільки
// невдалих спроб коду дозволено, перш ніж треба заново вводити пароль -
// без цього хтось міг би підбирати 6-значний код нескінченно.
const PENDING_2FA_TIMEOUT = 300;
const PENDING_2FA_MAX_ATTEMPTS = 5;

function start_admin_session(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

function is_logged_in(): bool
{
    start_admin_session();

    if (empty($_SESSION['admin_id'])) {
        return false;
    }

    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > SESSION_TIMEOUT) {
        session_unset();
        session_destroy();
        return false;
    }

    $_SESSION['last_activity'] = time();
    return true;
}

// Викликати на початку КОЖНОЇ сторінки адмінки, крім login.php -
// не залогінений одразу летить на форму входу, далі код сторінки
// взагалі не виконується.
function require_login(): void
{
    if (!is_logged_in()) {
        header('Location: login.php');
        exit;
    }
}

// Повертає 'ok' (повний вхід виконано), 'need_2fa' (пароль вірний, чекаємо
// код з додатка) або 'fail' (невірний логін/пароль).
function attempt_login(string $username, string $password): string
{
    $stmt = get_db()->prepare('SELECT id, password_hash, role, totp_enabled FROM admin_users WHERE username = ?');
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    // password_verify сам розбирається з форматом хешу (bcrypt/argon2) -
    // порівняння відбувається в СТАЛИЙ час (timing-safe), на відміну
    // від == чи ===, що вразливі до timing-атак на пароль по символах.
    if (!$user || !password_verify($password, $user['password_hash'])) {
        log_activity('login_failed', 'auth', null, '', $username);
        return 'fail';
    }

    start_admin_session();
    session_regenerate_id(true); // новий ID сесії при вході - захист від session fixation

    if ((int) $user['totp_enabled'] === 1) {
        $_SESSION['pending_admin_id'] = $user['id'];
        $_SESSION['pending_admin_started'] = time();
        $_SESSION['pending_2fa_attempts'] = 0;
        return 'need_2fa';
    }

    $_SESSION['admin_id'] = $user['id'];
    $_SESSION['admin_role'] = $user['role'];
    $_SESSION['last_activity'] = time();
    log_activity('login', 'auth', (int) $user['id'], '', $username);
    return 'ok';
}

function has_pending_2fa(): bool
{
    start_admin_session();

    if (!isset($_SESSION['pending_admin_id'])) {
        return false;
    }

    if ((time() - ($_SESSION['pending_admin_started'] ?? 0)) > PENDING_2FA_TIMEOUT) {
        clear_pending_2fa();
        return false;
    }

    return true;
}

function clear_pending_2fa(): void
{
    unset($_SESSION['pending_admin_id'], $_SESSION['pending_admin_started'], $_SESSION['pending_2fa_attempts']);
}

// Другий крок входу - викликати з verify-2fa.php після attempt_login()
// повернув 'need_2fa'.
function verify_2fa_code(string $code): bool
{
    if (!has_pending_2fa()) {
        return false;
    }

    $pendingId = $_SESSION['pending_admin_id'];
    $stmt = get_db()->prepare('SELECT username, role, totp_secret FROM admin_users WHERE id = ?');
    $stmt->execute([$pendingId]);
    $user = $stmt->fetch();

    if (!$user || !$user['totp_secret'] || !totp_verify($user['totp_secret'], $code)) {
        log_activity('login_2fa_failed', 'auth', (int) $pendingId, '', $user['username'] ?? 'невідомо');
        $_SESSION['pending_2fa_attempts'] = ($_SESSION['pending_2fa_attempts'] ?? 0) + 1;
        if ($_SESSION['pending_2fa_attempts'] >= PENDING_2FA_MAX_ATTEMPTS) {
            clear_pending_2fa();
        }
        return false;
    }

    session_regenerate_id(true);
    $_SESSION['admin_id'] = $pendingId;
    $_SESSION['admin_role'] = $user['role'];
    $_SESSION['last_activity'] = time();
    clear_pending_2fa();
    log_activity('login', 'auth', (int) $pendingId, '2FA', $user['username']);
    return true;
}

function current_role(): ?string
{
    start_admin_session();
    return $_SESSION['admin_role'] ?? null;
}

// Викликати ПІСЛЯ require_login() на сторінках, доступних лише 'admin'
// (settings.php, users.php) - editor, що спробує зайти напряму за URL,
// отримає 403, а не побачить вміст сторінки.
function require_role(string $role): void
{
    if (current_role() !== $role) {
        http_response_code(403);
        echo 'Доступ заборонено: потрібна роль "' . htmlspecialchars($role) . '".';
        exit;
    }
}

function logout(): void
{
    start_admin_session();
    session_unset();
    session_destroy();
}

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
    $_SESSION['admin_username'] = $username;
    $_SESSION['last_activity'] = time();
    log_activity('login', 'auth', (int) $user['id'], '', $username);
    return 'ok';
}

// Об'єднана система акаунтів (27.09.2026) - те, що раніше було ОКРЕМОЮ
// таблицею site_users/site_auth.php (акаунти відвідувачів лише для
// коментарів), тепер просто ще один рядок у admin_users з роллю 'user'.
// Один спільний логін/сесія для будь-якої ролі - "залогінений" (для
// коментарів) і "має доступ в адмінку" (admin/editor) - тепер РІЗНІ
// перевірки (is_logged_in() і require_admin_panel() нижче), а не різні
// таблиці/сесійні ключі.
//
// Порожній рядок - успіх, інакше текст помилки для форми. Роль ЗАВЖДИ
// 'user' тут - жорстко в INSERT, без жодного поля вибору ролі у формі
// (самостійна реєстрація ніколи не дає admin/editor - лише "Підвищити"
// в admin/users.php власником сайту може змінити роль).
function register_account(string $username, string $password): string
{
    $username = trim($username);

    if (!preg_match('/^[a-zA-Z0-9_]{3,32}$/', $username)) {
        return 'Логін - 3-32 символи, лише латинські літери/цифри/підкреслення.';
    }
    if (strlen($password) < 8) {
        return 'Пароль має бути щонайменше 8 символів.';
    }

    $db = get_db();
    $stmt = $db->prepare('SELECT id FROM admin_users WHERE username = ?');
    $stmt->execute([$username]);
    if ($stmt->fetch()) {
        return 'Цей логін вже зареєстровано.';
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);
    $db->prepare("INSERT INTO admin_users (username, password_hash, role) VALUES (?, ?, 'user')")
        ->execute([$username, $hash]);

    // Одразу логінимо - новий 'user'-акаунт totp_enabled=0 за визначенням
    // (щойно створений, 2FA ще ніде не вмикав), тому пряме встановлення
    // сесії тут, без гілки "need_2fa", яка є в attempt_login().
    start_admin_session();
    session_regenerate_id(true);
    $_SESSION['admin_id'] = (int) $db->lastInsertId();
    $_SESSION['admin_role'] = 'user';
    $_SESSION['admin_username'] = $username;
    $_SESSION['last_activity'] = time();
    log_activity('register', 'auth', (int) $_SESSION['admin_id'], '', $username);
    return '';
}

/** @return array{id: int, username: string, role: string}|null */
function current_account(): ?array
{
    if (!is_logged_in()) {
        return null;
    }
    return [
        'id' => (int) ($_SESSION['admin_id'] ?? 0),
        'username' => (string) ($_SESSION['admin_username'] ?? ''),
        'role' => (string) ($_SESSION['admin_role'] ?? ''),
    ];
}

// require_login() лишається "залогінений хоч якийсь акаунт" (досить для
// коментарів) - ця, нова функція додатково вимагає роль admin/editor,
// тобто справжній доступ до панелі керування, а не просто будь-який
// зареєстрований відвідувач. Викликати на початку КОЖНОЇ сторінки
// адмінки замість require_login() (крім login.php/register.php).
function require_admin_panel(): void
{
    require_login();
    if (!in_array(current_role(), ['admin', 'editor'], true)) {
        http_response_code(403);
        echo 'Доступ заборонено: потрібна роль адміністратора чи редактора.';
        exit;
    }
}

/**
 * Читає з $_SESSION, а на таймауті САМА чистить pending-стан
 * (clear_pending_2fa()) - два виклики поспіль можуть дати РІЗНИЙ
 * результат (напр. verify-2fa.php: другий виклик після невдалої
 * verify_2fa_code(), яка сама могла щойно вичерпати ліміт спроб і
 * скинути pending-стан) - не чиста функція.
 *
 * @phpstan-impure
 */
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
    $_SESSION['admin_username'] = $user['username'];
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

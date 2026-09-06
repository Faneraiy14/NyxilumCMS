<?php
require_once __DIR__ . '/db.php';

// Спільні для admin/activity.php (повний журнал) і admin/index.php
// (дашборд, стрічка останніх дій) - один словник підписів замість двох
// копій, які легко розійдуться, коли з'явиться новий action/entity_type.
const ACTION_LABELS = [
    'create' => 'Створення',
    'update' => 'Оновлення',
    'update_role' => 'Зміна ролі',
    'delete' => 'Видалення',
    'upload' => 'Завантаження',
    'reset_password' => 'Скидання пароля',
    'login' => 'Вхід',
    'login_failed' => 'Невдалий вхід',
    'login_2fa_failed' => 'Невірний код 2FA',
    'enable_2fa' => 'Увімкнення 2FA',
    'disable_2fa' => 'Вимкнення 2FA',
];

const ENTITY_LABELS = [
    'content' => 'контент',
    'menu_item' => 'пункт меню',
    'media' => 'медіа',
    'settings' => 'налаштування',
    'user' => 'користувач',
    'auth' => 'авторизація',
    'category' => 'категорія',
];

// Викликати ПІСЛЯ require_login() - бере логін з поточної сесії, а не як
// окремий параметр, щоб не забувати/плутати, від чийого імені пишеться дія.
// $usernameOverride - виняток для login.php: там ще НЕМА сесії (при невдалій
// спробі входу) або сесія щойно створена (при вдалій) - передаємо ім'я напряму.
function log_activity(string $action, string $entityType, ?int $entityId = null, string $details = '', ?string $usernameOverride = null): void
{
    if ($usernameOverride !== null) {
        $username = $usernameOverride;
    } else {
        $adminId = $_SESSION['admin_id'] ?? null;
        if ($adminId !== null) {
            $stmt = get_db()->prepare('SELECT username FROM admin_users WHERE id = ?');
            $stmt->execute([$adminId]);
            $username = $stmt->fetchColumn() ?: 'невідомо';
        } else {
            $username = 'невідомо';
        }
    }

    $stmt = get_db()->prepare(
        'INSERT INTO activity_log (admin_username, action, entity_type, entity_id, details) VALUES (?, ?, ?, ?, ?)'
    );
    $stmt->execute([$username, $action, $entityType, $entityId, $details ?: null]);
}

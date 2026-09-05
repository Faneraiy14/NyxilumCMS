<?php
require_once __DIR__ . '/db.php';

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

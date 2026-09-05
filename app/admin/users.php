<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/activity.php';
require_login();
require_role('admin');

$db = get_db();
$myId = (int) $_SESSION['admin_id'];
$error = '';

function count_admins(PDO $db): int
{
    return (int) $db->query("SELECT COUNT(*) FROM admin_users WHERE role = 'admin'")->fetchColumn();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $role = ($_POST['role'] ?? 'editor') === 'admin' ? 'admin' : 'editor';

        if ($username !== '' && strlen($password) >= 8) {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $db->prepare('INSERT INTO admin_users (username, password_hash, role) VALUES (?, ?, ?)');
            $stmt->execute([$username, $hash, $role]);
            log_activity('create', 'user', (int) $db->lastInsertId(), "{$username} ({$role})");
        } else {
            $error = 'Логін не може бути порожнім, пароль - мінімум 8 символів.';
        }
    } elseif ($action === 'update_role') {
        $id = (int) ($_POST['id'] ?? 0);
        $role = ($_POST['role'] ?? 'editor') === 'admin' ? 'admin' : 'editor';

        // Не можна забрати останнього admin'а - інакше НІХТО більше не
        // потрапить сюди чи в settings.php, щоб це виправити.
        if ($role === 'editor' && $id === $myId) {
            $error = 'Не можна понизити самого себе, поки ти єдиний адмін.';
        } else {
            $current = $db->prepare('SELECT username, role FROM admin_users WHERE id = ?');
            $current->execute([$id]);
            $currentRow = $current->fetch();
            $currentRole = $currentRow['role'] ?? null;

            if ($currentRole === 'admin' && $role === 'editor' && count_admins($db) <= 1) {
                $error = 'Не можна понизити останнього адміна в системі.';
            } else {
                $db->prepare('UPDATE admin_users SET role = ? WHERE id = ?')->execute([$role, $id]);
                log_activity('update_role', 'user', $id, ($currentRow['username'] ?? '') . " -> {$role}");
            }
        }
    } elseif ($action === 'reset_password') {
        $id = (int) ($_POST['id'] ?? 0);
        $password = $_POST['password'] ?? '';
        if (strlen($password) >= 8) {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $db->prepare('UPDATE admin_users SET password_hash = ? WHERE id = ?')->execute([$hash, $id]);
            $targetUsername = $db->prepare('SELECT username FROM admin_users WHERE id = ?');
            $targetUsername->execute([$id]);
            log_activity('reset_password', 'user', $id, (string) $targetUsername->fetchColumn());
        } else {
            $error = 'Пароль - мінімум 8 символів.';
        }
    } elseif ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id === $myId) {
            $error = 'Не можна видалити самого себе.';
        } else {
            $target = $db->prepare('SELECT username, role FROM admin_users WHERE id = ?');
            $target->execute([$id]);
            $targetRow = $target->fetch();
            $targetRole = $targetRow['role'] ?? null;
            if ($targetRole === 'admin' && count_admins($db) <= 1) {
                $error = 'Не можна видалити останнього адміна в системі.';
            } else {
                $db->prepare('DELETE FROM admin_users WHERE id = ?')->execute([$id]);
                log_activity('delete', 'user', $id, $targetRow['username'] ?? '');
            }
        }
    }

    if ($error === '') {
        header('Location: users.php');
        exit;
    }
}

$users = $db->query('SELECT id, username, role, created_at FROM admin_users ORDER BY created_at ASC')->fetchAll();
?>
<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Користувачі — Nyxilum CMS</title>
    <link rel="stylesheet" href="admin.css">
</head>
<body>
    <?php include __DIR__ . '/nav.php'; ?>

    <main class="admin-main">
        <h1>Користувачі адмінки</h1>

        <?php if ($error !== '') : ?>
            <p class="error"><?php echo htmlspecialchars($error); ?></p>
        <?php endif; ?>

        <form class="admin-form" method="post" action="users.php">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="create">
            <label>Логін <input type="text" name="username" required></label>
            <label>Пароль (мін. 8 символів) <input type="password" name="password" required minlength="8"></label>
            <label>
                Роль
                <select name="role">
                    <option value="editor">editor (контент/меню/медіа)</option>
                    <option value="admin">admin (усе + користувачі/налаштування)</option>
                </select>
            </label>
            <button type="submit">Створити користувача</button>
        </form>

        <ul class="admin-list">
            <?php foreach ($users as $user) : ?>
                <li>
                    <div class="admin-list-item-header">
                        <strong><?php echo htmlspecialchars($user['username']); ?></strong>
                        <?php echo $user['id'] === $myId ? '(ти)' : ''; ?>
                        <span class="admin-list-date"><?php echo htmlspecialchars($user['role']); ?> · з <?php echo htmlspecialchars($user['created_at']); ?></span>
                    </div>
                    <div class="admin-list-actions">
                        <form method="post" action="users.php" style="display:inline-flex; gap:6px; align-items:center;">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="action" value="update_role">
                            <input type="hidden" name="id" value="<?php echo (int) $user['id']; ?>">
                            <select name="role" onchange="this.form.submit()">
                                <option value="editor" <?php echo $user['role'] === 'editor' ? 'selected' : ''; ?>>editor</option>
                                <option value="admin" <?php echo $user['role'] === 'admin' ? 'selected' : ''; ?>>admin</option>
                            </select>
                        </form>
                        <form method="post" action="users.php" style="display:inline-flex; gap:6px;" onsubmit="const p=prompt('Новий пароль (мін. 8 символів):'); if(!p){return false;} this.querySelector('[name=password]').value=p;">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="action" value="reset_password">
                            <input type="hidden" name="id" value="<?php echo (int) $user['id']; ?>">
                            <input type="hidden" name="password" value="">
                            <button type="submit">Скинути пароль</button>
                        </form>
                        <?php if ($user['id'] !== $myId) : ?>
                            <form method="post" action="users.php" onsubmit="return confirm('Видалити користувача?');">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?php echo (int) $user['id']; ?>">
                                <button type="submit" class="delete-btn">Видалити</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
    </main>
</body>
</html>

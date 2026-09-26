<?php

const CONFIG_PATH = __DIR__ . '/../config.php';

function is_installed(): bool
{
    return is_file(CONFIG_PATH);
}

function get_db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        // РЕАЛЬНИЙ БАГ, знайдений 26.09.2026 (переніс код у my-hub,
        // спробував прогнати тести): env-змінні перевірялись ПІСЛЯ
        // is_installed()/require CONFIG_PATH - на свіжому чекауті (як
        // GitHub Actions CI, де app/config.php немає, він у .gitignore)
        // це означало header('Location: /install.php'); exit; ЩЕ ДО
        // того, як тестові env-змінні взагалі бралися до уваги -
        // composer test у CI мовчки НІЧОГО не запускав (exit 0, нуль
        // виводу - навіть банер PHPUnit не встигав надрукуватись, бо
        // TestRunner ще на етапі завантаження bootstrap.php через
        // require app/includes/db.php одразу викликав exit() зсередини
        // get_db()). Локально це маскувалось випадковим лишнім
        // app/config.php від ручного тестування - у "чистому" CI-
        // чекауті файлу нема ніколи. Виправлено - env-змінна DB_HOST
        // перевіряється ПЕРШОЮ, ДО будь-якої перевірки config.php.
        $envDbHost = getenv('DB_HOST');
        if ($envDbHost !== false) {
            $dbHost = $envDbHost;
            $dbPort = getenv('DB_PORT') ?: '3306';
            $dbName = getenv('DB_NAME') ?: '';
            $dbUser = getenv('DB_USER') ?: '';
            $dbPass = getenv('DB_PASS') !== false ? getenv('DB_PASS') : '';
        } else {
            if (!is_installed()) {
                header('Location: /install.php');
                exit;
            }
            require CONFIG_PATH; // визначає DB_HOST, DB_NAME, DB_USER, DB_PASS
            $dbHost = DB_HOST;
            $dbPort = '3306';
            $dbName = DB_NAME;
            $dbUser = DB_USER;
            $dbPass = DB_PASS;
        }

        $pdo = new PDO(
            'mysql:host=' . $dbHost . ';port=' . $dbPort . ';dbname=' . $dbName . ';charset=utf8mb4',
            $dbUser,
            $dbPass,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]
        );
    }
    return $pdo;
}

// SQL-фрагмент "цей запис реально видимий відвідувачам зараз" -
// status='published' саме по собі недостатньо: publish_at дозволяє
// підготувати запис заздалегідь (позначити published), а він лишається
// невидимим, доки не настане вказаний час. NULL publish_at - видно
// одразу. Один спільний фрагмент замість дублювання цієї умови в
// кожному з half-дюжини публічних запитів (головна/sitemap/feed/
// пошук/категорія/один запис) - легко забути додати другу половину
// умови в новому місці, якщо писати її щоразу вручну.
function published_condition(string $columnPrefix = ''): string
{
    return "{$columnPrefix}status = 'published' AND ({$columnPrefix}publish_at IS NULL OR {$columnPrefix}publish_at <= NOW())";
}

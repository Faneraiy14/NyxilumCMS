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
        if (!is_installed()) {
            header('Location: /install.php');
            exit;
        }
        require CONFIG_PATH; // визначає DB_HOST, DB_NAME, DB_USER, DB_PASS

        // env-змінні мають пріоритет над config.php - ЛИШЕ для тестів
        // (tests/bootstrap.php), щоб підключатись до окремої тестової
        // бази, не чіпаючи config.php реального інсталу. У звичайній
        // роботі сайту ці env-змінні просто не виставлені, і все йде як
        // раніше, з config.php.
        $dbHost = getenv('DB_HOST') ?: DB_HOST;
        $dbPort = getenv('DB_PORT') ?: '3306';
        $dbName = getenv('DB_NAME') ?: DB_NAME;
        $dbUser = getenv('DB_USER') ?: DB_USER;
        $dbPass = getenv('DB_PASS') !== false ? getenv('DB_PASS') : DB_PASS;

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

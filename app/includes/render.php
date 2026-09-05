<?php

function get_setting(PDO $db, string $key, string $default = ''): string
{
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        foreach ($db->query('SELECT setting_key, setting_value FROM settings') as $row) {
            $cache[$row['setting_key']] = $row['setting_value'];
        }
    }
    return $cache[$key] ?? $default;
}

function render(string $template, array $vars = []): void
{
    extract($vars);
    require __DIR__ . '/../templates/header.php';
    require __DIR__ . "/../templates/{$template}.php";
    require __DIR__ . '/../templates/footer.php';
}

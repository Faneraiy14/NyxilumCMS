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

        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]
        );
    }
    return $pdo;
}

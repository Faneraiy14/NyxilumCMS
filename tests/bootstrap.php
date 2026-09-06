<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../app/includes/db.php';
require_once __DIR__ . '/../app/includes/categories.php';

// Тести реально пишуть/видаляють рядки (create/delete тестового
// контенту, категорій і т.д.) у базі, на яку вказує поточне
// підключення - якщо це справжня "nyxilum_cms" (реальний сайт), запуск
// тут ризикує зіпсувати реальні дані (той самий урок, що й у
// sunshine-edit-magic - дядя Вова прямо попереджав про цей сценарій).
//
// Перевіряємо не якийсь окремий прапорець (APP_ENV=test і подібне) -
// такий прапорець легко виставити випадково навіть проти реальної бази,
// і перевірка тоді нічого насправді не гарантує. Замість цього дивимось
// на РЕАЛЬНЕ ім'я бази з уже встановленого з'єднання (SELECT DATABASE()),
// те саме, яким користується get_db() у продакшн-коді.
$dbName = get_db()->query('SELECT DATABASE()')->fetchColumn();
if (!is_string($dbName) || !str_contains($dbName, 'test')) {
    fwrite(STDERR, <<<MSG

        ВІДМОВЛЯЮСЬ запускати тести: поточна база "{$dbName}" не містить "test".

        Тести реально пишуть і видаляють рядки в базі, до якої підключено
        get_db() (config.php або env-змінні DB_*). Якщо це не спеціально
        створена тестова база - запуск тут ризикує зіпсувати реальні дані.

        Створи окрему базу з "test" у назві (напр. nyxilum_cms_test) і
        запусти composer test так:

          DB_NAME=nyxilum_cms_test composer test


        MSG);
    exit(1);
}

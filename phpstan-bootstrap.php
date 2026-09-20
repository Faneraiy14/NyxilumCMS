<?php
// Лише для PHPStan, НЕ виконується сайтом - DB_HOST/DB_NAME/DB_USER/
// DB_PASS насправді визначаються через `require CONFIG_PATH` у
// includes/db.php (app/config.php - той самий config.local.php-патерн,
// що й в my-hub/sunshine-edit-magic - НІКОЛИ не в git, бо там реальні
// дані підключення). У CI цього файлу нема, тому без цього бутстрапу
// PHPStan бачить get_db() як звернення до неіснуючих констант.
// Значення довільні - PHPStan цікавить лише факт, що константи РЕАЛЬНО
// існують під час require, не їхній вміст.
define('DB_HOST', '');
define('DB_NAME', '');
define('DB_USER', '');
define('DB_PASS', '');

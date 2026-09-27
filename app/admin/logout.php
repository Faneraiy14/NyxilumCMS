<?php
require_once __DIR__ . '/../includes/auth.php';

// Роль треба зчитати ДО logout() (він чистить сесію) - звичайний
// відвідувач (роль 'user') після виходу повертається на сайт, а не на
// форму входу в панель керування, якою вона все одно ніколи не
// користувалась (27.09.2026, об'єднана система акаунтів).
$wasAdminOrEditor = in_array(current_role(), ['admin', 'editor'], true);

logout();
header('Location: ' . ($wasAdminOrEditor ? 'login.php' : '/'));
exit;

<?php
// CSRF-захист форм. Ідея: сервер генерує випадковий токен, кладе в
// сесію І в приховане поле форми. При відправці форми звіряємо -
// збігаються. Чужий сайт, який намагається відправити форму від
// твого імені (без відома, що всередині твоєї сесії), НЕ знає цей
// токен - запит відхиляється.

function csrf_token(): string
{
    start_admin_session();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token()) . '">';
}

function csrf_verify(): void
{
    start_admin_session();
    $submitted = $_POST['csrf_token'] ?? '';
    $expected = $_SESSION['csrf_token'] ?? '';

    // hash_equals - порівняння в сталий час (timing-safe), той самий
    // принцип, що password_verify в auth.php.
    if ($expected === '' || !hash_equals($expected, $submitted)) {
        http_response_code(403);
        die('Помилка безпеки форми (CSRF). Онови сторінку й спробуй ще раз.');
    }
}

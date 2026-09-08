<?php
/** @var string $error */
?>
<section class="auth-form-page">
    <h1>Реєстрація</h1>
    <?php if ($error !== '') : ?>
        <p class="error"><?php echo htmlspecialchars($error); ?></p>
    <?php endif; ?>
    <form method="post" action="/register" class="auth-form">
        <?php echo csrf_field(); ?>
        <label>
            Логін
            <input type="text" name="username" pattern="[a-zA-Z0-9_]{3,32}" title="3-32 символи: латинські літери, цифри, підкреслення" required autofocus>
        </label>
        <label>
            Email
            <input type="email" name="email" required>
        </label>
        <label>
            Пароль
            <input type="password" name="password" minlength="8" required>
        </label>
        <button type="submit">Зареєструватись</button>
    </form>
    <p>Вже маєте акаунт? <a href="/login">Увійти</a></p>
</section>

<?php
/** @var string $error */
?>
<section class="auth-form-page">
    <h1>Вхід</h1>
    <?php if ($error !== '') : ?>
        <p class="error"><?php echo htmlspecialchars($error); ?></p>
    <?php endif; ?>
    <form method="post" action="/login" class="auth-form">
        <?php echo csrf_field(); ?>
        <label>
            Логін або email
            <input type="text" name="username" required autofocus>
        </label>
        <label>
            Пароль
            <input type="password" name="password" required>
        </label>
        <button type="submit">Увійти</button>
    </form>
    <p>Ще немає акаунта? <a href="/register">Зареєструватись</a></p>
</section>

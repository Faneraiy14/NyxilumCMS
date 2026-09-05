<nav class="admin-nav">
    <span class="admin-nav-title">Nyxilum CMS</span>
    <a href="/" target="_blank">На сайт</a>
    <a href="index.php">Дашборд</a>
    <a href="content.php">Контент</a>
    <a href="categories.php">Категорії</a>
    <a href="menu.php">Меню</a>
    <a href="media.php">Медіа</a>
    <?php if (current_role() === 'admin') : ?>
        <a href="settings.php">Налаштування</a>
        <a href="users.php">Користувачі</a>
        <a href="activity.php">Журнал</a>
        <a href="export.php">Експорт</a>
        <a href="import.php">Імпорт</a>
    <?php endif; ?>
    <a href="2fa.php">2FA</a>
    <a href="logout.php" class="admin-nav-logout">Вийти</a>
</nav>

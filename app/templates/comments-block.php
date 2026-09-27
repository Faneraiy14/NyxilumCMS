<?php
/** @var array<string, mixed> $item
 *  @var array<int, array<string, mixed>> $itemComments список коментарів (username/body/created_at)
 *  @var array<string, mixed>|null $siteUser поточний залогінений відвідувач, якщо є
 *
 *  Спільний партиал для page.php і type-post.php - той самий блок
 *  коментарів під будь-яким типом контенту, без дублювання розмітки.
 *  body коментаря - НЕДОВІРЕНИЙ ввід (пише будь-який зареєстрований
 *  відвідувач), тож завжди htmlspecialchars(), на відміну від
 *  item['body'] вище в самих шаблонах.
 */
?>
<section class="comments">
    <?php // 🔥 ВИПРАВЛЕНО (27.09.2026): "Коментарі (0)"/"Коментарів поки
    // немає" раніше показувались завжди, навіть коли коментарів справді
    // нема - зайвий шум для звичайного відвідувача (реальна скарга
    // користувача). Форма/запрошення лишити коментар нижче - і далі
    // завжди видимі, незалежно від цього - щоб можна було стати першим. ?>
    <?php if ($itemComments) : ?>
        <h2>Коментарі (<?php echo count($itemComments); ?>)</h2>
        <?php foreach ($itemComments as $comment) : ?>
            <div class="comment">
                <strong><?php echo htmlspecialchars($comment['username']); ?></strong>
                <span class="comment-date"><?php echo htmlspecialchars(date('d.m.Y H:i', strtotime($comment['created_at']))); ?></span>
                <p><?php echo nl2br(htmlspecialchars($comment['body'])); ?></p>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <?php if ($siteUser) : ?>
        <form method="post" action="/comment" class="comment-form">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="content_id" value="<?php echo (int) $item['id']; ?>">
            <textarea name="body" placeholder="Ваш коментар…" maxlength="2000" required></textarea>
            <button type="submit">Залишити коментар</button>
        </form>
        <p class="comment-as">Ви залогінені як <?php echo htmlspecialchars($siteUser['username']); ?> · <a href="/admin/logout.php">Вийти</a></p>
    <?php else : ?>
        <p class="comment-login-hint"><a href="/admin/login.php">Увійдіть</a> або <a href="/admin/register.php">зареєструйтесь</a>, щоб залишити коментар.</p>
    <?php endif; ?>
</section>

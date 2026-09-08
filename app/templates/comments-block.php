<?php
/** @var array $item
 *  @var array $itemComments список коментарів (username/body/created_at)
 *  @var array|null $siteUser поточний залогінений відвідувач, якщо є
 *
 *  Спільний партиал для page.php і type-post.php - той самий блок
 *  коментарів під будь-яким типом контенту, без дублювання розмітки.
 *  body коментаря - НЕДОВІРЕНИЙ ввід (пише будь-який зареєстрований
 *  відвідувач), тож завжди htmlspecialchars(), на відміну від
 *  item['body'] вище в самих шаблонах.
 */
?>
<section class="comments">
    <h2>Коментарі (<?php echo count($itemComments); ?>)</h2>

    <?php foreach ($itemComments as $comment) : ?>
        <div class="comment">
            <strong><?php echo htmlspecialchars($comment['username']); ?></strong>
            <span class="comment-date"><?php echo htmlspecialchars(date('d.m.Y H:i', strtotime($comment['created_at']))); ?></span>
            <p><?php echo nl2br(htmlspecialchars($comment['body'])); ?></p>
        </div>
    <?php endforeach; ?>
    <?php if (!$itemComments) : ?>
        <p class="admin-list-empty">Коментарів поки немає.</p>
    <?php endif; ?>

    <?php if ($siteUser) : ?>
        <form method="post" action="/comment" class="comment-form">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="content_id" value="<?php echo (int) $item['id']; ?>">
            <textarea name="body" placeholder="Ваш коментар…" maxlength="2000" required></textarea>
            <button type="submit">Залишити коментар</button>
        </form>
        <p class="comment-as">Ви залогінені як <?php echo htmlspecialchars($siteUser['username']); ?> · <a href="/logout">Вийти</a></p>
    <?php else : ?>
        <p class="comment-login-hint"><a href="/login">Увійдіть</a> або <a href="/register">зареєструйтесь</a>, щоб залишити коментар.</p>
    <?php endif; ?>
</section>

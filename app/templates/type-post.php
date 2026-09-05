<?php
/** @var array $item трастований контент, редагується лише через адмінку
 *  Відрізняється від звичайного page.php: показує дату публікації,
 *  типовий вигляд для "блог-запису" замість статичної сторінки.
 */
?>
<article class="page-content post-content">
    <p class="post-date"><?php echo htmlspecialchars(date('d.m.Y', strtotime($item['updated_at']))); ?></p>
    <h1><?php echo htmlspecialchars($item['title']); ?></h1>
    <div class="page-body"><?php echo $item['body']; ?></div>
</article>

<?php
/** @var array $item трастований контент, редагується лише через адмінку.
 * body тепер реальний HTML від Quill-редактора (не простий текст) -
 * виводимо як є, без htmlspecialchars/nl2br, інакше теги показались би
 * буквально замість форматування. Безпечно саме тому, що це поле пише
 * лише залогінений адмін через власну панель, не сторонній ввід.
 */
?>
<article class="page-content">
    <h1><?php echo htmlspecialchars($item['title']); ?></h1>
    <div class="page-body"><?php echo $item['body']; ?></div>
</article>

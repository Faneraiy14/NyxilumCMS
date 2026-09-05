<?php
/** @var array $item трастований контент, редагується лише через адмінку.
 * body тепер реальний HTML від Quill-редактора (не простий текст) -
 * виводимо як є, без htmlspecialchars/nl2br, інакше теги показались би
 * буквально замість форматування. Безпечно саме тому, що це поле пише
 * лише залогінений адмін через власну панель, не сторонній ввід.
 * @var array $itemCategories
 */
?>
<article class="page-content">
    <h1><?php echo htmlspecialchars($item['title']); ?></h1>
    <div class="page-body"><?php echo $item['body']; ?></div>
    <?php if ($itemCategories) : ?>
        <p class="content-categories">
            <?php foreach ($itemCategories as $cat) : ?>
                <a href="/category/<?php echo rawurlencode($cat['slug']); ?>" class="category-badge"><?php echo htmlspecialchars($cat['name']); ?></a>
            <?php endforeach; ?>
        </p>
    <?php endif; ?>
</article>

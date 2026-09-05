<?php
/**
 * @var array $category {id, name, slug, description}
 * @var array $items опубліковані записи цієї категорії
 */
?>
<section class="hero">
    <h1><?php echo htmlspecialchars($category['name']); ?></h1>
    <?php if ($category['description']) : ?>
        <p><?php echo htmlspecialchars($category['description']); ?></p>
    <?php endif; ?>
</section>

<?php if ($items) : ?>
    <ul class="content-list">
        <?php foreach ($items as $item) : ?>
            <li>
                <a class="content-card" href="/<?php echo rawurlencode($item['slug']); ?>">
                    <span class="content-title"><?php echo htmlspecialchars($item['title']); ?></span>
                    <span class="content-type"><?php echo htmlspecialchars($item['type']); ?></span>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>
<?php else : ?>
    <p class="empty-state">У цій категорії поки немає опублікованого контенту.</p>
<?php endif; ?>

<?php
/** @var array $items */
?>
<section class="hero">
    <h1><?php echo htmlspecialchars($siteName); ?></h1>
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
    <p class="empty-state">Контенту поки немає.</p>
<?php endif; ?>

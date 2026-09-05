<?php
/** @var array $items
 *  @var string $query
 */
?>
<h1>Пошук<?php echo $query !== '' ? ': "' . htmlspecialchars($query) . '"' : ''; ?></h1>

<form method="get" action="/search" class="search-form">
    <input type="text" name="q" value="<?php echo htmlspecialchars($query); ?>" placeholder="Що шукаємо?">
    <button type="submit">Знайти</button>
</form>

<?php if ($query === '') : ?>
    <p class="empty-state">Введи запит вище.</p>
<?php elseif (!$items) : ?>
    <p class="empty-state">Нічого не знайдено за запитом "<?php echo htmlspecialchars($query); ?>".</p>
<?php else : ?>
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
<?php endif; ?>

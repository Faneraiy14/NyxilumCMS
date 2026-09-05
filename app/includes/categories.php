<?php

// Категорії - одна гнучка many-to-many прив'язка до контенту, а не
// окрема паралельна система "тегів" - того самого механізму досить
// для обох сценаріїв (і "категорія" на сторінці, і "тег" на записі).

/** @return array<int, array{id:int, name:string, slug:string}> */
function get_categories_for_content(PDO $db, int $contentId): array
{
    $stmt = $db->prepare(
        'SELECT c.id, c.name, c.slug FROM categories c
         JOIN content_categories cc ON cc.category_id = c.id
         WHERE cc.content_id = ? ORDER BY c.name'
    );
    $stmt->execute([$contentId]);
    return $stmt->fetchAll();
}

/**
 * Повністю замінює набір категорій контенту - той самий підхід, що й
 * усюди в CMS для списків, якими керує форма (простіше й надійніше,
 * ніж рахувати diff доданих/прибраних елементів).
 *
 * @param list<int> $categoryIds
 */
function set_content_categories(PDO $db, int $contentId, array $categoryIds): void
{
    $db->prepare('DELETE FROM content_categories WHERE content_id = ?')->execute([$contentId]);
    if ($categoryIds === []) {
        return;
    }

    $stmt = $db->prepare('INSERT IGNORE INTO content_categories (content_id, category_id) VALUES (?, ?)');
    foreach (array_unique($categoryIds) as $categoryId) {
        $stmt->execute([$contentId, $categoryId]);
    }
}

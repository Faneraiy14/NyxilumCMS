<?php

declare(strict_types=1);

namespace Tests;

use PDO;
use PHPUnit\Framework\TestCase;

final class CategoriesTest extends TestCase
{
    private PDO $db;
    private int $contentId;
    private int $categoryIdA;
    private int $categoryIdB;

    protected function setUp(): void
    {
        $this->db = get_db();

        $this->db->prepare(
            "INSERT INTO content (type, slug, title, status) VALUES ('page', ?, 'PHPUnit test content', 'draft')"
        )->execute(['__phpunit_test_categories__']);
        $this->contentId = (int) $this->db->lastInsertId();

        $this->db->prepare('INSERT INTO categories (name, slug) VALUES (?, ?)')
            ->execute(['PHPUnit A', '__phpunit_test_cat_a__']);
        $this->categoryIdA = (int) $this->db->lastInsertId();

        $this->db->prepare('INSERT INTO categories (name, slug) VALUES (?, ?)')
            ->execute(['PHPUnit B', '__phpunit_test_cat_b__']);
        $this->categoryIdB = (int) $this->db->lastInsertId();
    }

    protected function tearDown(): void
    {
        $this->db->prepare('DELETE FROM content WHERE id = ?')->execute([$this->contentId]);
        $this->db->prepare('DELETE FROM categories WHERE id IN (?, ?)')->execute([$this->categoryIdA, $this->categoryIdB]);
    }

    public function testANewContentItemHasNoCategories(): void
    {
        $this->assertSame([], get_categories_for_content($this->db, $this->contentId));
    }

    public function testSetContentCategoriesAttachesTheGivenCategories(): void
    {
        set_content_categories($this->db, $this->contentId, [$this->categoryIdA, $this->categoryIdB]);

        $names = array_column(get_categories_for_content($this->db, $this->contentId), 'name');
        sort($names);
        $this->assertSame(['PHPUnit A', 'PHPUnit B'], $names);
    }

    public function testSetContentCategoriesFullyReplacesThePreviousSet(): void
    {
        set_content_categories($this->db, $this->contentId, [$this->categoryIdA, $this->categoryIdB]);
        set_content_categories($this->db, $this->contentId, [$this->categoryIdA]);

        $names = array_column(get_categories_for_content($this->db, $this->contentId), 'name');
        $this->assertSame(['PHPUnit A'], $names);
    }

    public function testSetContentCategoriesWithAnEmptyArrayDetachesEverything(): void
    {
        set_content_categories($this->db, $this->contentId, [$this->categoryIdA, $this->categoryIdB]);
        set_content_categories($this->db, $this->contentId, []);

        $this->assertSame([], get_categories_for_content($this->db, $this->contentId));
    }

    /**
     * Регресійний тест на реальну поведінку схеми: content_categories.
     * category_id має ON DELETE CASCADE - видалення категорії само
     * прибирає прив'язки, контент лишається живим.
     */
    public function testDeletingACategoryCascadesButKeepsTheContentRow(): void
    {
        set_content_categories($this->db, $this->contentId, [$this->categoryIdA]);

        $this->db->prepare('DELETE FROM categories WHERE id = ?')->execute([$this->categoryIdA]);

        $this->assertSame([], get_categories_for_content($this->db, $this->contentId));
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM content WHERE id = ?');
        $stmt->execute([$this->contentId]);
        $this->assertSame(1, (int) $stmt->fetchColumn());
        // categoryIdA вже видалено тут - tearDown()'івський DELETE на
        // нього ж - no-op (рядка вже нема), не помилка.
    }
}

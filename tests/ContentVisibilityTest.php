<?php

declare(strict_types=1);

namespace Tests;

use PDO;
use PHPUnit\Framework\TestCase;

/**
 * published_condition() саме по собі - чистий рядок (див.
 * PublishedConditionTest) - але справжня перевірка того, що вона
 * дає, це реальний SELECT проти реальних рядків з усіма трьома
 * комбінаціями status/publish_at, які насправді трапляються в
 * адмінці (index.php покладається саме на цю умову для головної/
 * sitemap/feed/пошуку/категорій/окремого запису).
 */
final class ContentVisibilityTest extends TestCase
{
    private PDO $db;
    /** @var list<int> */
    private array $insertedIds = [];

    protected function setUp(): void
    {
        $this->db = get_db();
    }

    protected function tearDown(): void
    {
        if ($this->insertedIds !== []) {
            $placeholders = implode(',', array_fill(0, count($this->insertedIds), '?'));
            $this->db->prepare("DELETE FROM content WHERE id IN ({$placeholders})")->execute($this->insertedIds);
        }
    }

    private function insert(string $slugSuffix, string $status, ?string $publishAt): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO content (type, slug, title, status, publish_at) VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute(['page', '__phpunit_test_visibility_' . $slugSuffix, 'PHPUnit visibility test', $status, $publishAt]);
        $id = (int) $this->db->lastInsertId();
        $this->insertedIds[] = $id;
        return $id;
    }

    private function isVisible(int $id): bool
    {
        $stmt = $this->db->prepare('SELECT 1 FROM content WHERE id = ? AND ' . published_condition());
        $stmt->execute([$id]);
        return $stmt->fetchColumn() !== false;
    }

    public function testDraftIsNeverVisibleRegardlessOfPublishAt(): void
    {
        $this->assertFalse($this->isVisible($this->insert('draft', 'draft', null)));
    }

    public function testPublishedWithNoPublishAtIsVisibleImmediately(): void
    {
        $this->assertTrue($this->isVisible($this->insert('now', 'published', null)));
    }

    public function testPublishedWithAFuturePublishAtIsNotYetVisible(): void
    {
        $future = date('Y-m-d H:i:s', time() + 3600);
        $this->assertFalse($this->isVisible($this->insert('future', 'published', $future)));
    }

    public function testPublishedWithAPastPublishAtIsVisible(): void
    {
        $past = date('Y-m-d H:i:s', time() - 3600);
        $this->assertTrue($this->isVisible($this->insert('past', 'published', $past)));
    }
}

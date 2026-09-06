<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;

final class PublishedConditionTest extends TestCase
{
    public function testBuildsTheConditionWithoutAPrefix(): void
    {
        $this->assertSame(
            "status = 'published' AND (publish_at IS NULL OR publish_at <= NOW())",
            published_condition()
        );
    }

    public function testAppliesAColumnPrefixForJoinedQueries(): void
    {
        $this->assertSame(
            "content.status = 'published' AND (content.publish_at IS NULL OR content.publish_at <= NOW())",
            published_condition('content.')
        );
    }
}

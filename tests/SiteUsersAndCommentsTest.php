<?php

declare(strict_types=1);

namespace Tests;

use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Акаунти відвідувачів (реєстрація/вхід) і коментарі - геть окрема
 * система від admin_users/auth.php (Sviatoslav: "реєстрація коментарі
 * потрібно зробити в моїй CMS", раніше floated "третю роль" в самій
 * адмінці й сам відхилив цю ідею - це не те саме).
 */
final class SiteUsersAndCommentsTest extends TestCase
{
    private PDO $db;
    private int $contentId;

    protected function setUp(): void
    {
        $this->db = get_db();

        $this->db->prepare(
            "INSERT INTO content (type, slug, title, status) VALUES ('page', ?, 'PHPUnit test content', 'published')"
        )->execute(['__phpunit_test_comments__']);
        $this->contentId = (int) $this->db->lastInsertId();

        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        $this->db->prepare('DELETE FROM comments WHERE content_id = ?')->execute([$this->contentId]);
        $this->db->prepare('DELETE FROM content WHERE id = ?')->execute([$this->contentId]);
        $this->db->prepare("DELETE FROM site_users WHERE username LIKE '__phpunit_test_%'")->execute();
        $_SESSION = [];
    }

    public function testSiteRegisterRejectsAShortPassword(): void
    {
        $error = site_register('__phpunit_test_user__', 'phpunit@example.com', 'short');
        $this->assertNotSame('', $error);

        $stmt = $this->db->prepare('SELECT COUNT(*) FROM site_users WHERE username = ?');
        $stmt->execute(['__phpunit_test_user__']);
        $this->assertSame(0, (int) $stmt->fetchColumn());
    }

    public function testSiteRegisterRejectsAnInvalidEmail(): void
    {
        $error = site_register('__phpunit_test_user__', 'not-an-email', 'irrelevant123');
        $this->assertNotSame('', $error);
    }

    public function testSiteRegisterRejectsAUsernameWithDisallowedCharacters(): void
    {
        $error = site_register('bad name!', 'phpunit@example.com', 'irrelevant123');
        $this->assertNotSame('', $error);
    }

    public function testSiteRegisterCreatesTheAccountAndLogsIn(): void
    {
        $error = site_register('__phpunit_test_user__', 'phpunit@example.com', 'RealPassword123');

        $this->assertSame('', $error);
        $this->assertNotNull(current_site_user());
        $this->assertSame('__phpunit_test_user__', current_site_user()['username']);
    }

    public function testSiteRegisterTwiceWithTheSameUsernameFails(): void
    {
        site_register('__phpunit_test_user__', 'first@example.com', 'RealPassword123');
        $_SESSION = [];

        $error = site_register('__phpunit_test_user__', 'second@example.com', 'RealPassword123');

        $this->assertNotSame('', $error);
    }

    public function testSiteAttemptLoginWithTheWrongPasswordFails(): void
    {
        site_register('__phpunit_test_user__', 'phpunit@example.com', 'RealPassword123');
        site_logout();

        $this->assertFalse(site_attempt_login('__phpunit_test_user__', 'wrong-password'));
        $this->assertNull(current_site_user());
    }

    public function testSiteAttemptLoginByEmailWorksTooNotJustUsername(): void
    {
        site_register('__phpunit_test_user__', 'phpunit@example.com', 'RealPassword123');
        site_logout();

        $this->assertTrue(site_attempt_login('phpunit@example.com', 'RealPassword123'));
        $this->assertSame('__phpunit_test_user__', current_site_user()['username']);
    }

    public function testSiteLogoutClearsTheSession(): void
    {
        site_register('__phpunit_test_user__', 'phpunit@example.com', 'RealPassword123');

        site_logout();

        $this->assertNull(current_site_user());
    }

    public function testAddCommentRejectsAnEmptyBody(): void
    {
        site_register('__phpunit_test_user__', 'phpunit@example.com', 'RealPassword123');
        $userId = current_site_user()['id'];

        $error = add_comment($this->db, $this->contentId, $userId, '   ');

        $this->assertNotSame('', $error);
        $this->assertSame([], get_comments_for_content($this->db, $this->contentId));
    }

    public function testAddCommentThenGetCommentsForContentReturnsItWithTheUsername(): void
    {
        site_register('__phpunit_test_user__', 'phpunit@example.com', 'RealPassword123');
        $userId = current_site_user()['id'];

        $error = add_comment($this->db, $this->contentId, $userId, 'Реальний коментар PHPUnit.');

        $this->assertSame('', $error);
        $comments = get_comments_for_content($this->db, $this->contentId);
        $this->assertCount(1, $comments);
        $this->assertSame('Реальний коментар PHPUnit.', $comments[0]['body']);
        $this->assertSame('__phpunit_test_user__', $comments[0]['username']);
    }

    /**
     * Регресійний тест на реальну поведінку схеми: comments.site_user_id
     * має ON DELETE CASCADE - видалення акаунта відвідувача забирає й
     * усі його коментарі, а не лишає "осиротілі" рядки.
     */
    public function testDeletingASiteUserCascadesTheirComments(): void
    {
        site_register('__phpunit_test_user__', 'phpunit@example.com', 'RealPassword123');
        $userId = current_site_user()['id'];
        add_comment($this->db, $this->contentId, $userId, 'Коментар, що має зникнути.');

        $this->db->prepare('DELETE FROM site_users WHERE id = ?')->execute([$userId]);

        $this->assertSame([], get_comments_for_content($this->db, $this->contentId));
    }
}

<?php

declare(strict_types=1);

namespace Tests;

use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Об'єднана система акаунтів (27.09.2026) - звичайні відвідувачі
 * (реєстрація/вхід/коментарі) тепер теж рядки в admin_users, з роллю
 * 'user', а не окрема таблиця site_users. Причина зміни: власник сайту
 * хотів мати змогу "підвищити" зареєстрованого відвідувача до editor/
 * admin - неможливо, якщо це справді геть окрема таблиця без жодного
 * зв'язку з ролями (див. коментар, який був тут раніше - явно
 * протилежне рішення від 05.09.2026, свідомо переглянуте зараз).
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
        $this->db->prepare("DELETE FROM admin_users WHERE username LIKE '__phpunit_test_%'")->execute();
        $_SESSION = [];
    }

    public function testRegisterAccountRejectsAShortPassword(): void
    {
        $error = register_account('__phpunit_test_user__', 'short');
        $this->assertNotSame('', $error);

        $stmt = $this->db->prepare('SELECT COUNT(*) FROM admin_users WHERE username = ?');
        $stmt->execute(['__phpunit_test_user__']);
        $this->assertSame(0, (int) $stmt->fetchColumn());
    }

    public function testRegisterAccountRejectsAUsernameWithDisallowedCharacters(): void
    {
        $error = register_account('bad name!', 'irrelevant123');
        $this->assertNotSame('', $error);
    }

    public function testRegisterAccountCreatesTheAccountWithRoleUserAndLogsIn(): void
    {
        $error = register_account('__phpunit_test_user__', 'RealPassword123');

        $this->assertSame('', $error);
        $this->assertNotNull(current_account());
        $this->assertSame('__phpunit_test_user__', current_account()['username']);
        $this->assertSame('user', current_account()['role']);

        // Регресійний тест на реальну вимогу: самостійна реєстрація
        // НІКОЛИ не дає admin/editor, хай там що прийшло б у запиті -
        // register_account() навіть не приймає роль параметром.
        $stmt = $this->db->prepare('SELECT role FROM admin_users WHERE username = ?');
        $stmt->execute(['__phpunit_test_user__']);
        $this->assertSame('user', $stmt->fetchColumn());
    }

    public function testRegisterAccountTwiceWithTheSameUsernameFails(): void
    {
        register_account('__phpunit_test_user__', 'RealPassword123');
        $_SESSION = [];

        $error = register_account('__phpunit_test_user__', 'RealPassword123');

        $this->assertNotSame('', $error);
    }

    public function testAttemptLoginWithTheWrongPasswordFails(): void
    {
        register_account('__phpunit_test_user__', 'RealPassword123');
        logout();

        $this->assertSame('fail', attempt_login('__phpunit_test_user__', 'wrong-password'));
        $this->assertNull(current_account());
    }

    public function testAttemptLoginWithTheRightPasswordSucceeds(): void
    {
        register_account('__phpunit_test_user__', 'RealPassword123');
        logout();

        $this->assertSame('ok', attempt_login('__phpunit_test_user__', 'RealPassword123'));
        $this->assertSame('__phpunit_test_user__', current_account()['username']);
    }

    public function testLogoutClearsTheSession(): void
    {
        register_account('__phpunit_test_user__', 'RealPassword123');

        logout();

        $this->assertNull(current_account());
    }

    public function testAddCommentRejectsAnEmptyBody(): void
    {
        register_account('__phpunit_test_user__', 'RealPassword123');
        $userId = current_account()['id'];

        $error = add_comment($this->db, $this->contentId, $userId, '   ');

        $this->assertNotSame('', $error);
        $this->assertSame([], get_comments_for_content($this->db, $this->contentId));
    }

    public function testAddCommentThenGetCommentsForContentReturnsItWithTheUsername(): void
    {
        register_account('__phpunit_test_user__', 'RealPassword123');
        $userId = current_account()['id'];

        $error = add_comment($this->db, $this->contentId, $userId, 'Реальний коментар PHPUnit.');

        $this->assertSame('', $error);
        $comments = get_comments_for_content($this->db, $this->contentId);
        $this->assertCount(1, $comments);
        $this->assertSame('Реальний коментар PHPUnit.', $comments[0]['body']);
        $this->assertSame('__phpunit_test_user__', $comments[0]['username']);
    }

    /**
     * Регресійний тест на реальну поведінку схеми: comments.user_id
     * має ON DELETE CASCADE - видалення акаунта забирає й усі його
     * коментарі, а не лишає "осиротілі" рядки. Той самий тест, що був
     * для site_users - тепер проти admin_users (спільна таблиця для
     * акаунтів усіх ролей).
     */
    public function testDeletingAnAccountCascadesTheirComments(): void
    {
        register_account('__phpunit_test_user__', 'RealPassword123');
        $userId = current_account()['id'];
        add_comment($this->db, $this->contentId, $userId, 'Коментар, що має зникнути.');

        $this->db->prepare('DELETE FROM admin_users WHERE id = ?')->execute([$userId]);

        $this->assertSame([], get_comments_for_content($this->db, $this->contentId));
    }
}

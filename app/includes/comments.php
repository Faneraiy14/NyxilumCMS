<?php

// body - НЕ trusted HTML (на відміну від content.body, куди пише лише
// адмін через Quill) - коментар пише будь-який зареєстрований
// відвідувач, тож templates/*.php виводять body через htmlspecialchars,
// ніколи як сирий HTML.

/** @return array<int, array{id:int, body:string, created_at:string, username:string}> */
function get_comments_for_content(PDO $db, int $contentId): array
{
    $stmt = $db->prepare(
        'SELECT c.id, c.body, c.created_at, u.username FROM comments c
         JOIN site_users u ON u.id = c.site_user_id
         WHERE c.content_id = ? ORDER BY c.created_at ASC'
    );
    $stmt->execute([$contentId]);
    return $stmt->fetchAll();
}

/** @return string Помилка, якщо є; порожній рядок - успіх. */
function add_comment(PDO $db, int $contentId, int $siteUserId, string $body): string
{
    $body = trim($body);
    if ($body === '') {
        return 'Порожній коментар.';
    }
    if (mb_strlen($body) > 2000) {
        return 'Коментар задовгий (максимум 2000 символів).';
    }

    $db->prepare('INSERT INTO comments (content_id, site_user_id, body) VALUES (?, ?, ?)')
        ->execute([$contentId, $siteUserId, $body]);
    return '';
}

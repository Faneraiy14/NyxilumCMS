CREATE DATABASE IF NOT EXISTS nyxilum_cms CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE nyxilum_cms;

CREATE TABLE admin_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(64) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    -- admin: усе, включно з користувачами й налаштуваннями.
    -- editor: контент/меню/медіа, без доступу до users.php/settings.php.
    role ENUM('admin', 'editor') NOT NULL DEFAULT 'editor',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    -- TOTP-секрет (base32) з'являється лише коли адмін вмикає 2FA собі сам
    -- через admin/2fa.php - totp_enabled лишається 0, доки він не підтвердить
    -- код з додатка (щоб не заблокувати самого себе неправильним секретом).
    totp_secret VARCHAR(32) NULL,
    totp_enabled TINYINT(1) NOT NULL DEFAULT 0
);

-- Один "гнучкий" тип замість окремих таблиць на кожен тип контенту (як
-- було в my-hub: notes/pages/links окремо) - саме це робить це CMS, а не
-- ще одним персональним хабом. `type` - будь-який довільний рядок
-- (напр. "page", "post"), новий тип не потребує нової таблиці/міграції.
CREATE TABLE content (
    id INT AUTO_INCREMENT PRIMARY KEY,
    type VARCHAR(64) NOT NULL DEFAULT 'page',
    slug VARCHAR(255) UNIQUE NOT NULL,
    lang VARCHAR(8) NOT NULL DEFAULT 'uk',
    title VARCHAR(255) NOT NULL,
    meta_title VARCHAR(255) NULL,
    meta_description VARCHAR(500) NULL,
    body LONGTEXT,
    status ENUM('draft', 'published') NOT NULL DEFAULT 'draft',
    -- NULL - публікується одразу, коли status='published'. Значення в
    -- майбутньому - публічно видиме лише ПІСЛЯ настання цього часу
    -- (публічні запити фільтрують publish_at <= NOW()), хоча
    -- status уже 'published' - дозволяє підготувати запис заздалегідь,
    -- не чіпаючи саму колонку status у день публікації.
    publish_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_type_status (type, status)
);

CREATE TABLE menu_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    label VARCHAR(255) NOT NULL,
    url VARCHAR(255) NOT NULL,
    sort_order INT NOT NULL DEFAULT 0
);

CREATE TABLE media (
    id INT AUTO_INCREMENT PRIMARY KEY,
    filename VARCHAR(255) NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    mime_type VARCHAR(64) NOT NULL,
    size_bytes INT NOT NULL,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE activity_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admin_username VARCHAR(64) NOT NULL,
    action VARCHAR(32) NOT NULL,
    entity_type VARCHAR(32) NOT NULL,
    entity_id INT NULL,
    details VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_created (created_at)
);

-- Категорії - вільна багато-до-багатьох прив'язка до контенту через
-- content_categories, а не окрема система "тегів" поруч - одного
-- гнучкого механізму досить для обох сценаріїв (і "категорія" на
-- сторінці, і "тег" на записі).
CREATE TABLE categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) UNIQUE NOT NULL,
    description VARCHAR(500) NULL
);

CREATE TABLE content_categories (
    content_id INT NOT NULL,
    category_id INT NOT NULL,
    PRIMARY KEY (content_id, category_id),
    FOREIGN KEY (content_id) REFERENCES content(id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE
);

CREATE TABLE settings (
    setting_key VARCHAR(64) PRIMARY KEY,
    setting_value TEXT
);

INSERT INTO settings (setting_key, setting_value) VALUES
    ('site_name', 'Nyxilum CMS'),
    ('default_lang', 'uk'),
    -- Версія СХЕМИ БД (не версія коду!) - коли з'явиться апдейтер, він
    -- звірятиме це число й запускатиме тільки міграції новіші за нього,
    -- замість припущення "у всіх однакова структура таблиць".
    ('schema_version', '9');

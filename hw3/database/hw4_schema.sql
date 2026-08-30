USE cse135_analytics;

CREATE TABLE IF NOT EXISTS users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL,
    email VARCHAR(254) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM(
        'super_admin',
        'analyst',
        'viewer'
    ) NOT NULL DEFAULT 'viewer',
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    last_login_at DATETIME(3),
    created_at DATETIME(3) NOT NULL
        DEFAULT CURRENT_TIMESTAMP(3),
    updated_at DATETIME(3) NOT NULL
        DEFAULT CURRENT_TIMESTAMP(3)
        ON UPDATE CURRENT_TIMESTAMP(3),

    CONSTRAINT users_username_unique
        UNIQUE (username),

    CONSTRAINT users_email_unique
        UNIQUE (email),

    INDEX users_role_active_index (role, is_active)
);

CREATE TABLE IF NOT EXISTS user_section_permissions (
    user_id BIGINT UNSIGNED NOT NULL,
    section_name ENUM(
        'technology',
        'performance',
        'behavior'
    ) NOT NULL,
    created_at DATETIME(3) NOT NULL
        DEFAULT CURRENT_TIMESTAMP(3),

    PRIMARY KEY (user_id, section_name),

    CONSTRAINT section_permissions_user_fk
        FOREIGN KEY (user_id)
        REFERENCES users(id)
        ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS saved_reports (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    report_key VARCHAR(100) NOT NULL,
    title VARCHAR(150) NOT NULL,
    category ENUM(
        'technology',
        'performance',
        'behavior'
    ) NOT NULL,
    guiding_question TEXT NOT NULL,
    analyst_comments TEXT,
    created_by BIGINT UNSIGNED,
    is_published BOOLEAN NOT NULL DEFAULT FALSE,
    created_at DATETIME(3) NOT NULL
        DEFAULT CURRENT_TIMESTAMP(3),
    updated_at DATETIME(3) NOT NULL
        DEFAULT CURRENT_TIMESTAMP(3)
        ON UPDATE CURRENT_TIMESTAMP(3),

    CONSTRAINT saved_reports_key_unique
        UNIQUE (report_key),

    INDEX saved_reports_category_index (
        category,
        is_published
    ),

    INDEX saved_reports_creator_index (created_by),

    CONSTRAINT saved_reports_creator_fk
        FOREIGN KEY (created_by)
        REFERENCES users(id)
        ON DELETE SET NULL
);

GRANT SELECT
ON cse135_analytics.performance_data
TO 'cse135_reporting'@'localhost';

GRANT SELECT
ON cse135_analytics.activity_events
TO 'cse135_reporting'@'localhost';

GRANT SELECT, INSERT, UPDATE, DELETE
ON cse135_analytics.users
TO 'cse135_reporting'@'localhost';

GRANT SELECT, INSERT, UPDATE, DELETE
ON cse135_analytics.user_section_permissions
TO 'cse135_reporting'@'localhost';

GRANT SELECT, INSERT, UPDATE, DELETE
ON cse135_analytics.saved_reports
TO 'cse135_reporting'@'localhost';
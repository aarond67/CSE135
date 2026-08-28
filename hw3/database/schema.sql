CREATE DATABASE IF NOT EXISTS cse135_analytics
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE cse135_analytics;

CREATE TABLE IF NOT EXISTS sessions (
    session_id CHAR(36) PRIMARY KEY,
    first_seen DATETIME(3) NOT NULL,
    last_seen DATETIME(3) NOT NULL
);

CREATE TABLE IF NOT EXISTS static_data (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    session_id CHAR(36) NOT NULL,
    page_url TEXT NOT NULL,
    collected_at DATETIME(3) NOT NULL,
    user_agent TEXT,
    language VARCHAR(35),
    cookies_enabled BOOLEAN,
    javascript_enabled BOOLEAN,
    images_enabled BOOLEAN,
    css_enabled BOOLEAN,
    screen_width INT UNSIGNED,
    screen_height INT UNSIGNED,
    window_width INT UNSIGNED,
    window_height INT UNSIGNED,
    network_type VARCHAR(32),
    effective_type VARCHAR(32),
    downlink DECIMAL(8,2),
    rtt INT UNSIGNED,
    save_data BOOLEAN,
    raw_data JSON NOT NULL,
    INDEX static_session_index (session_id),
    CONSTRAINT static_session_fk
        FOREIGN KEY (session_id)
        REFERENCES sessions(session_id)
        ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS performance_data (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    session_id CHAR(36) NOT NULL,
    page_url TEXT NOT NULL,
    collected_at DATETIME(3) NOT NULL,
    page_load_start DATETIME(3),
    page_load_end DATETIME(3),
    total_load_time_ms DECIMAL(12,3),
    navigation_timing JSON NOT NULL,
    INDEX performance_session_index (session_id),
    CONSTRAINT performance_session_fk
        FOREIGN KEY (session_id)
        REFERENCES sessions(session_id)
        ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS activity_events (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    session_id CHAR(36) NOT NULL,
    page_url TEXT NOT NULL,
    event_type VARCHAR(50) NOT NULL,
    event_time DATETIME(3) NOT NULL,
    event_data JSON NOT NULL,
    INDEX activity_session_index (session_id),
    INDEX activity_type_time_index (event_type, event_time),
    CONSTRAINT activity_session_fk
        FOREIGN KEY (session_id)
        REFERENCES sessions(session_id)
        ON DELETE CASCADE
);
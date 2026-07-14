CREATE TABLE IF NOT EXISTS shared_access_links (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token CHAR(64) NOT NULL,
    type VARCHAR(20) NOT NULL DEFAULT 'trainer',
    display_name VARCHAR(120) NOT NULL DEFAULT 'Пользователь FoodTracker',
    timezone_offset SMALLINT NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    expires_at DATETIME NOT NULL,
    last_viewed_at DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    revoked_at DATETIME DEFAULT NULL,
    UNIQUE KEY uniq_shared_access_token (token),
    INDEX idx_shared_access_user (user_id, type, is_active),
    INDEX idx_shared_access_expiry (is_active, expires_at),
    CONSTRAINT fk_shared_access_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT chk_shared_access_type CHECK (type IN ('trainer')),
    CONSTRAINT chk_shared_access_timezone CHECK (timezone_offset BETWEEN -840 AND 840)
);

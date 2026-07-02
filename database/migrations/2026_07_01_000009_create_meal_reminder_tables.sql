CREATE TABLE IF NOT EXISTS user_meal_reminder_settings (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    meal_type VARCHAR(20) NOT NULL,
    reminder_time TIME NOT NULL,
    remind_before_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 15,
    timezone_offset SMALLINT NOT NULL DEFAULT 0,
    enabled TINYINT(1) NOT NULL DEFAULT 1,
    last_meal_at DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_meal_reminder_user_type (user_id, meal_type),
    INDEX idx_meal_reminder_enabled (enabled),
    CONSTRAINT fk_meal_reminder_user
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT chk_meal_reminder_type
        CHECK (meal_type IN ('breakfast', 'lunch', 'dinner')),
    CONSTRAINT chk_meal_reminder_before
        CHECK (remind_before_minutes <= 180),
    CONSTRAINT chk_meal_reminder_timezone
        CHECK (timezone_offset BETWEEN -840 AND 840)
);

CREATE TABLE IF NOT EXISTS notification_queue (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    notification_type VARCHAR(40) NOT NULL DEFAULT 'meal_reminder',
    meal_type VARCHAR(20) NOT NULL,
    local_date DATE NOT NULL,
    send_at DATETIME NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    sent_at DATETIME DEFAULT NULL,
    last_error TEXT DEFAULT NULL,
    payload JSON DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_notification_user_type_date (user_id, notification_type, meal_type, local_date),
    INDEX idx_notification_dispatch (status, send_at),
    INDEX idx_notification_user_created (user_id, created_at),
    CONSTRAINT fk_notification_queue_user
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT chk_notification_meal_type
        CHECK (meal_type IN ('breakfast', 'lunch', 'dinner')),
    CONSTRAINT chk_notification_status
        CHECK (status IN ('pending', 'processing', 'sent', 'skipped', 'failed'))
);

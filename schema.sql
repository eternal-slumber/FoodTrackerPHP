-- FoodTracker Database Schema

-- Таблица пользователей
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tg_id BIGINT UNIQUE NOT NULL,
    weight FLOAT NOT NULL,
    height INT NOT NULL,
    age INT NOT NULL,
    gender ENUM('male', 'female') NOT NULL,
    activity_level VARCHAR(20) DEFAULT 'medium',
    goal VARCHAR(20) DEFAULT 'maintenance',
    daily_goal INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_tg_id (tg_id)
);

-- Таблица приемов пищи
CREATE TABLE IF NOT EXISTS meals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    food_description TEXT,
    calories INT NOT NULL,
    proteins FLOAT DEFAULT 0,
    fats FLOAT DEFAULT 0,
    carbs FLOAT DEFAULT 0,
    total_weight INT DEFAULT NULL,
    image_path VARCHAR(500),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_created_at (created_at),
    INDEX idx_meals_user_created (user_id, created_at)
);

-- Таблица продуктов внутри приема пищи
CREATE TABLE IF NOT EXISTS meal_products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    meal_id INT NOT NULL,
    name VARCHAR(120) NOT NULL,
    weight INT NOT NULL,
    processing VARCHAR(50) DEFAULT '',
    calories INT NOT NULL DEFAULT 0,
    proteins FLOAT DEFAULT 0,
    fats FLOAT DEFAULT 0,
    carbs FLOAT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (meal_id) REFERENCES meals(id) ON DELETE CASCADE,
    INDEX idx_meal_products_meal_id (meal_id)
);

-- Кэш единого AI-вывода по питанию за локальный день пользователя
CREATE TABLE IF NOT EXISTS daily_nutrition_insights (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    local_date DATE NOT NULL,
    timezone_offset SMALLINT NOT NULL DEFAULT 0,
    context_hash CHAR(64) NOT NULL,
    short_summary VARCHAR(280) NOT NULL,
    day_analysis TEXT NOT NULL,
    next_meal JSON NOT NULL,
    model VARCHAR(180) NOT NULL,
    generated_at DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_daily_insight_user_date (user_id, local_date),
    INDEX idx_daily_insight_generated (generated_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Настройки привычного времени напоминаний о приемах пищи
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

-- Очередь конкретных отправок, которую выбирает общий cron
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

-- Таблица rate limit / daily quota окон
CREATE TABLE IF NOT EXISTS rate_limits (
    id INT AUTO_INCREMENT PRIMARY KEY,
    scope_key VARCHAR(120) NOT NULL,
    action VARCHAR(60) NOT NULL,
    window_start DATETIME NOT NULL,
    attempts INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_rate_limit_window (scope_key, action, window_start),
    INDEX idx_rate_limits_cleanup (window_start)
);

-- Таблицы админ-панели
CREATE TABLE IF NOT EXISTS admin_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    telegram_id BIGINT NOT NULL,
    admin_login VARCHAR(190) DEFAULT NULL,
    password_hash VARCHAR(255) DEFAULT NULL,
    username VARCHAR(120) DEFAULT NULL,
    role VARCHAR(40) NOT NULL DEFAULT 'admin',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    last_login_at DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_admin_users_telegram_id (telegram_id),
    UNIQUE KEY uniq_admin_users_admin_login (admin_login),
    INDEX idx_admin_users_active_role (is_active, role)
);

CREATE TABLE IF NOT EXISTS ai_models (
    id INT AUTO_INCREMENT PRIMARY KEY,
    model_key VARCHAR(120) NOT NULL,
    provider VARCHAR(60) NOT NULL,
    api_model VARCHAR(180) NOT NULL,
    display_name VARCHAR(180) NOT NULL,
    base_url VARCHAR(500) DEFAULT NULL,
    is_primary TINYINT(1) NOT NULL DEFAULT 0,
    is_fallback TINYINT(1) NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    sort_order INT NOT NULL DEFAULT 100,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_ai_models_model_key (model_key),
    INDEX idx_ai_models_active (is_active),
    INDEX idx_ai_models_provider (provider)
);

CREATE TABLE IF NOT EXISTS app_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(120) NOT NULL,
    setting_value TEXT DEFAULT NULL,
    value_type VARCHAR(30) NOT NULL DEFAULT 'string',
    description VARCHAR(255) DEFAULT NULL,
    updated_by_admin_id INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_app_settings_key (setting_key),
    INDEX idx_app_settings_updated_by (updated_by_admin_id),
    FOREIGN KEY (updated_by_admin_id) REFERENCES admin_users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS admin_audit_logs (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    admin_user_id INT DEFAULT NULL,
    action VARCHAR(80) NOT NULL,
    entity_type VARCHAR(80) DEFAULT NULL,
    entity_id VARCHAR(80) DEFAULT NULL,
    old_value JSON DEFAULT NULL,
    new_value JSON DEFAULT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    user_agent VARCHAR(500) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_admin_audit_logs_admin_created (admin_user_id, created_at),
    INDEX idx_admin_audit_logs_action_created (action, created_at),
    FOREIGN KEY (admin_user_id) REFERENCES admin_users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS ai_requests (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    user_id INT DEFAULT NULL,
    ai_model_id INT DEFAULT NULL,
    request_type VARCHAR(60) NOT NULL DEFAULT 'meal_analysis',
    status VARCHAR(30) NOT NULL,
    response_time_ms INT DEFAULT NULL,
    prompt_tokens INT DEFAULT NULL,
    completion_tokens INT DEFAULT NULL,
    estimated_cost DECIMAL(12, 6) DEFAULT NULL,
    error_message TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ai_requests_user_created (user_id, created_at),
    INDEX idx_ai_requests_model_created (ai_model_id, created_at),
    INDEX idx_ai_requests_status_created (status, created_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (ai_model_id) REFERENCES ai_models(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS user_events (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    user_id INT DEFAULT NULL,
    event_name VARCHAR(80) NOT NULL,
    event_data JSON DEFAULT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    user_agent VARCHAR(500) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_events_user_created (user_id, created_at),
    INDEX idx_user_events_name_created (event_name, created_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS system_logs (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    level VARCHAR(20) NOT NULL,
    channel VARCHAR(60) NOT NULL DEFAULT 'app',
    message TEXT NOT NULL,
    context JSON DEFAULT NULL,
    exception_class VARCHAR(255) DEFAULT NULL,
    trace_id VARCHAR(80) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_system_logs_level_created (level, created_at),
    INDEX idx_system_logs_channel_created (channel, created_at),
    INDEX idx_system_logs_trace_id (trace_id)
);

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

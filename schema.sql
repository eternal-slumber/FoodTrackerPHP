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
    INDEX idx_created_at (created_at)
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

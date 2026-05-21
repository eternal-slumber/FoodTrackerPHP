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

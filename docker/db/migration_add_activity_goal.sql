-- Миграция: Добавление полей activity и goal в таблицу users
-- Выполнить в Docker: docker exec foodtracker-db mysql -uroot -proot foodtracker_db < /docker-entrypoint-initdb.d/migration_add_activity_goal.sql

ALTER TABLE users 
ADD COLUMN activity_level DECIMAL(3,2) DEFAULT 1.55 AFTER daily_goal,
ADD COLUMN goal DECIMAL(3,2) DEFAULT 1.0 AFTER activity_level;

-- Индекс для быстрого поиска
CREATE INDEX idx_users_tg_id ON users(tg_id);
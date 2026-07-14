ALTER TABLE shared_access_links
    MODIFY COLUMN expires_at DATETIME NULL,
    ADD COLUMN duration_days SMALLINT UNSIGNED NULL DEFAULT 30 AFTER timezone_offset,
    ADD COLUMN show_meals TINYINT(1) NOT NULL DEFAULT 1 AFTER duration_days,
    ADD COLUMN show_nutrition TINYINT(1) NOT NULL DEFAULT 1 AFTER show_meals,
    ADD COLUMN show_history TINYINT(1) NOT NULL DEFAULT 1 AFTER show_nutrition,
    ADD COLUMN show_ai_analysis TINYINT(1) NOT NULL DEFAULT 1 AFTER show_history,
    ADD COLUMN show_profile_params TINYINT(1) NOT NULL DEFAULT 0 AFTER show_ai_analysis;

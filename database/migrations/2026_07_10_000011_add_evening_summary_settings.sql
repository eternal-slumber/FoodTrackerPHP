ALTER TABLE users
    ADD COLUMN evening_summary_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER meal_reminders_enabled,
    ADD COLUMN evening_summary_time TIME NOT NULL DEFAULT '21:00:00' AFTER evening_summary_enabled;

ALTER TABLE users
    ADD COLUMN meal_reminders_enabled TINYINT(1) NOT NULL DEFAULT 1
    AFTER daily_goal;

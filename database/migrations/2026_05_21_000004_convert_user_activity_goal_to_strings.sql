ALTER TABLE users
    MODIFY COLUMN activity_level VARCHAR(20) DEFAULT 'medium',
    MODIFY COLUMN goal VARCHAR(20) DEFAULT 'maintenance';

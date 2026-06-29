ALTER TABLE admin_users
    ADD COLUMN admin_login VARCHAR(190) DEFAULT NULL AFTER telegram_id,
    ADD COLUMN password_hash VARCHAR(255) DEFAULT NULL AFTER admin_login;

CREATE UNIQUE INDEX uniq_admin_users_admin_login ON admin_users (admin_login);


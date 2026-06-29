set -eu

admin_db_name="${DB_NAME:-}"
admin_db_user="${ADMIN_DB_USER:-}"
admin_db_pass="${ADMIN_DB_PASS:-}"

if [ -z "$admin_db_name" ] || [ -z "$admin_db_user" ] || [ -z "$admin_db_pass" ]; then
    echo "Skipping admin DB user creation: DB_NAME, ADMIN_DB_USER or ADMIN_DB_PASS is empty."
    return 0 2>/dev/null || exit 0
fi

case "$admin_db_name:$admin_db_user" in
    *[!A-Za-z0-9_:]*)
        echo "Invalid DB_NAME or ADMIN_DB_USER. Only letters, numbers and underscore are allowed."
        return 1 2>/dev/null || exit 1
        ;;
esac

mysql --protocol=socket -uroot -p"$MYSQL_ROOT_PASSWORD" <<-EOSQL
CREATE USER IF NOT EXISTS '${admin_db_user}'@'%' IDENTIFIED BY '${admin_db_pass}';
GRANT SELECT, INSERT, UPDATE, DELETE ON \`${admin_db_name}\`.* TO '${admin_db_user}'@'%';
FLUSH PRIVILEGES;
EOSQL


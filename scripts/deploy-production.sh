#!/usr/bin/env bash

set -eu

deploy_sha="${1:-}"

if ! printf '%s' "$deploy_sha" | grep -Eq '^[0-9a-f]{40}$'; then
    echo "A full 40-character deployment SHA is required" >&2
    exit 1
fi

cd /opt/foodtracker

echo "== Checkout deployment commit =="
git fetch --no-tags origin "$deploy_sha"
git checkout --detach "$deploy_sha"

deployed_sha="$(git rev-parse HEAD)"

if [ "$deployed_sha" != "$deploy_sha" ]; then
    echo "Expected $deploy_sha, but checked out $deployed_sha" >&2
    exit 1
fi

compose=(
    docker compose
    -p foodtracker-prod
    --env-file .env
    -f docker/docker-compose.yml
)

echo "== Ensure production database and app are running =="
"${compose[@]}" up -d db app

echo "== Backup production database =="
mkdir -p /root/foodtracker-backup/auto

"${compose[@]}" exec -T db \
    sh -c 'mysqldump -u root -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE"' \
    | gzip > "/root/foodtracker-backup/auto/database-before-deploy-$(date +%Y-%m-%d_%H-%M-%S).sql.gz"

echo "== Install Composer dependencies =="
"${compose[@]}" exec -T app \
    composer install \
    --no-interaction \
    --prefer-dist \
    --no-progress \
    --no-dev \
    --optimize-autoloader

echo "== Run migrations =="
"${compose[@]}" exec -T app composer run migrate

echo "== Build frontend =="
"${compose[@]}" exec -T app composer run build:frontend

echo "== Fix storage permissions =="
"${compose[@]}" exec -T app sh -c \
    'mkdir -p /var/www/storage/uploads &&
     chown -R www-data:www-data /var/www/storage &&
     chmod -R 775 /var/www/storage'

echo "== Rebuild production containers =="
"${compose[@]}" up -d --build

echo "== Health check =="
curl --fail --silent --show-error \
    --retry 5 \
    --retry-all-errors \
    --retry-delay 5 \
    --max-time 15 \
    https://app.mycaloriebot.ru > /dev/null

echo "== Production deployment completed: $deployed_sha =="
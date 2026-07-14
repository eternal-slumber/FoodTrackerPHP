#!/usr/bin/env bash

set -eu

deploy_sha="${1:-}"

if ! printf '%s' "$deploy_sha" | grep -Eq '^[0-9a-f]{40}$'; then
    echo "A full 40-character deployment SHA is required" >&2
    exit 1
fi

cd /opt/foodtracker-staging

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
    -p foodtracker-staging
    --env-file .env
    -f docker/docker-compose.staging.yml
)

echo "== Build and start staging containers =="
"${compose[@]}" up -d --build

echo "== Composer install =="
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

echo "== Health check =="
curl --fail --silent --show-error \
    --retry 5 \
    --retry-delay 3 \
    https://staging.mycaloriebot.ru > /dev/null

echo "== Staging deployment completed: $deployed_sha =="
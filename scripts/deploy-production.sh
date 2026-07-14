#!/usr/bin/env bash

set -eu

deploy_sha="${1:-}"
if ! printf '%s' "$deploy_sha" | grep -Eq '^[0-9a-f]{40}$'; then
    echo "A full 40-character deployment SHA is required" >&2
    exit 1
fi

git fetch --no-tags origin "$deploy_sha"
git checkout --detach "$deploy_sha"

deployed_sha="$(git rev-parse HEAD)"
if [ "$deployed_sha" != "$deploy_sha" ]; then
    echo "Expected $deploy_sha, but checked out $deployed_sha" >&2
    exit 1
fi

docker compose --env-file .env.db -f docker/docker-compose.yml run --rm app composer install --no-interaction --prefer-dist --no-progress --no-dev --optimize-autoloader
docker compose --env-file .env.db -f docker/docker-compose.yml run --rm app composer migrate
docker compose --env-file .env.db -f docker/docker-compose.yml run --rm app composer build:frontend

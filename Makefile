SHELL := /bin/sh

.PHONY: install build check lint test test-frontend test-php version-check package docker-up docker-down integration

install:
	bun install --frozen-lockfile
	composer install --no-interaction --prefer-dist

build:
	bun run build

lint:
	bun run typecheck
	bun run lint
	bun run format:check
	composer cs:check
	composer phpstan

test: test-frontend test-php

test-frontend:
	bun run test

test-php:
	composer test

check: lint test build version-check

version-check:
	php scripts/check-release-version.php

package:
	bash ./scripts/package.sh

docker-up:
	docker compose up -d db redis mailpit nextcloud
	docker compose run --rm bootstrap

docker-down:
	docker compose down

integration:
	sh ./tests/integration/webdav-read-only.sh

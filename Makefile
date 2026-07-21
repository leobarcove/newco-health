PHP := /usr/local/opt/php@8.3/bin/php

.PHONY: up down api web test build

up:
	docker compose -f infra/docker-compose.yml up -d

down:
	docker compose -f infra/docker-compose.yml down

api:
	cd apps/api && $(PHP) artisan migrate && $(PHP) artisan serve

web:
	npm run dev --workspace apps/web

test:
	cd apps/api && PAO_DISABLE=1 $(PHP) vendor/bin/pest
	npm run build --workspace apps/web

build:
	npm run build --workspace apps/web

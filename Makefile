PHP := /usr/local/opt/php@8.3/bin/php

.PHONY: up down fresh seed api web reverb queue test e2e build

## docker services (postgres, redis, mailpit, minio) — optional; sqlite is the default DB
up:
	docker compose -f infra/docker-compose.yml up -d

down:
	docker compose -f infra/docker-compose.yml down

## wipe + migrate + seed the full dev dataset (see DevSeeder for the sign-ins)
fresh:
	cd apps/api && PAO_DISABLE=1 $(PHP) artisan migrate:fresh --seed

## re-run seeders on the existing database
seed:
	cd apps/api && PAO_DISABLE=1 $(PHP) artisan db:seed

## API + Filament backoffice on :8000
api:
	cd apps/api && $(PHP) artisan migrate && $(PHP) artisan serve

## patient/doctor/sponsor PWA on :5173 (proxies /api → :8000)
web:
	npm run dev --workspace apps/web

## websockets on :8080 (optional — chat falls back to polling without it)
reverb:
	cd apps/api && $(PHP) artisan reverb:start

## queue worker + scheduled jobs (reminders, no-show sweep) — optional locally
queue:
	cd apps/api && $(PHP) artisan schedule:work

## watch simulated outbound SMS/WhatsApp (OTPs, reminders, nudges)
sms:
	tail -f apps/api/storage/logs/laravel.log | grep --line-buffered sms.outbound

test:
	cd apps/api && PAO_DISABLE=1 $(PHP) vendor/bin/pest
	npm run build --workspace apps/web

e2e:
	cd apps/web && npx playwright test

build:
	npm run build --workspace apps/web

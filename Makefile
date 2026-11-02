# Local development helpers for Nusa Creator Studio.
# Requires Docker (with Compose), PHP 8.4.27+ or 8.5.12+ with pdo_pgsql, Composer and Node 22.18+.

API := apps/api
WEB := apps/web

.PHONY: help setup services wait-db api-setup web-setup dev api queue web test lint stop

help:
	@echo "make setup   - start services, install dependencies, migrate and seed demo data"
	@echo "make dev     - run API (:8000), queue worker and web app (:5173) together"
	@echo "make test    - run backend and frontend test suites"
	@echo "make lint    - run Pint, vue-tsc, ESLint and oxlint in check mode"
	@echo "make stop    - stop the Docker services"

setup: services wait-db api-setup web-setup
	@echo "Ready. Run 'make dev' and open http://localhost:5173 (demo@example.test / password)."

services:
	docker compose up -d postgres redis mailpit

wait-db:
	@echo "Waiting for PostgreSQL..."
	@until docker compose exec -T postgres pg_isready -U nusa -d nusa_creator >/dev/null 2>&1; do sleep 1; done

api-setup:
	cd $(API) && composer install
	cd $(API) && test -f .env || cp .env.example .env
	cd $(API) && grep -q '^APP_KEY=base64' .env || php artisan key:generate
	cd $(API) && php artisan migrate --seed --force

web-setup:
	cd $(WEB) && npm ci
	cd $(WEB) && test -f .env.local || cp .env.example .env.local

dev:
	npx --yes concurrently@9 -k -n api,queue,web -c blue,magenta,green \
		"$(MAKE) api" "$(MAKE) queue" "$(MAKE) web"

api:
	cd $(API) && php artisan serve --port=8000

queue:
	cd $(API) && php artisan queue:work --tries=1 --timeout=0

web:
	cd $(WEB) && npm run dev

test:
	cd $(API) && php artisan test
	cd $(WEB) && npx vitest run

lint:
	cd $(API) && ./vendor/bin/pint --test
	cd $(WEB) && npx vue-tsc --build && npx eslint . && npx oxlint .

stop:
	docker compose stop

# Makefile — local Docker workflow. Run `make help` for the list of targets.

SHELL := /bin/bash
.DEFAULT_GOAL := help

export UID := $(shell id -u)
export GID := $(shell id -g)

# Host ports: the environment or `make up APP_PORT=9000` wins over .env, then the compose defaults apply.
env_value = $(shell grep -E '^$(1)=' .env 2>/dev/null | cut -d= -f2)
APP_PORT ?= $(or $(call env_value,APP_PORT),8080)
PROD_PORT ?= $(or $(call env_value,PROD_PORT),8081)
export APP_PORT PROD_PORT

COMPOSE := docker compose
# -T keeps exec usable without a TTY (CI, scripts).
EXEC := $(COMPOSE) exec -T app
NODE := $(COMPOSE) run --rm --no-deps -T node

.PHONY: help env up demo down logs sh test stan cs fix lint check lock front-lint front-test build prod-up prod-down

help: ## Show this help
	@grep -E '^[a-zA-Z_-]+:.*?## ' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-11s\033[0m %s\n", $$1, $$2}'

env: ## Create .env from .env.example with generated secrets (skipped if present)
	@sh bin/init-env

up: env ## Build and start everything, install dependencies, generate JWT keys on the first run
	@if [ -f package-lock.json ]; then \
		$(COMPOSE) --profile frontend up -d --build; \
	else \
		echo "package-lock.json is missing: starting without the Vite dev server (see bin/lock-assets)."; \
		$(COMPOSE) up -d --build; \
	fi
	$(EXEC) composer install --no-interaction
	$(EXEC) php bin/console lexik:jwt:generate-keypair --skip-if-exists
	@echo "App: http://localhost:$(APP_PORT)"

demo: env ## Like up, but with fictional sample data instead of TMDB (no token needed)
	@sh bin/init-demo-account
	@$(MAKE) --no-print-directory up TMDB_DEMO=1
	@echo "Demo mode: log in as '$$(grep -E '^AUTH_USERNAME=' .env | cut -d= -f2-)' with AUTH_PASSWORD from .env. 'make up' switches back to TMDB."

down: ## Stop and remove containers
	$(COMPOSE) --profile frontend down

logs: ## Follow the container logs
	$(COMPOSE) --profile frontend logs -f

sh: ## Open a shell in the PHP container
	$(COMPOSE) exec app sh

test: ## Run the PHPUnit suites (no network, no TMDB token needed)
	$(EXEC) vendor/bin/phpunit

stan: ## Static analysis (PHPStan, level max, tests included)
	$(EXEC) php bin/console cache:warmup --env=test --quiet
	$(EXEC) vendor/bin/phpstan analyse --memory-limit=1G --no-progress

cs: ## Check code style (PHP CS Fixer, dry run)
	$(EXEC) vendor/bin/php-cs-fixer fix --dry-run --diff

fix: ## Fix code style (PHP CS Fixer)
	$(EXEC) vendor/bin/php-cs-fixer fix

lint: cs stan ## Code style, PHPStan, container/YAML/Twig lint, composer validate
	$(EXEC) composer validate --strict
	$(EXEC) php bin/console lint:container
	$(EXEC) php bin/console lint:yaml config --parse-tags
	$(EXEC) php bin/console lint:twig templates

check: lint test ## Backend checks as in CI (front-lint, front-test and build cover the frontend)

lock: ## Create package-lock.json in a node container (dependencies at least 14 days old)
	docker run --rm -v "$(CURDIR)":/app -w /app -u "$(UID):$(GID)" -e HOME=/tmp -e npm_config_cache=/tmp/.npm node:22-alpine sh bin/lock-assets

# Installed strictly from the committed lock file, without lifecycle scripts.
node_modules: package-lock.json
	$(NODE) npm ci --ignore-scripts
	@touch node_modules

front-lint: node_modules ## Lint the frontend (ESLint)
	$(NODE) npm run lint

front-test: node_modules ## Run the frontend tests (Vitest)
	$(NODE) npm run test

build: node_modules ## Production build of the frontend into public/build
	$(NODE) npm run build

prod-up: env ## Production-like stack (compiled assets, no dev server) on PROD_PORT
	@test -f config/jwt/private.pem || { echo "Run 'make up' first: it generates the JWT key pair."; exit 1; }
	$(COMPOSE) -f compose.prod.yaml up -d --build --wait
	@echo "Production-like app: http://localhost:$(PROD_PORT)"

prod-down: ## Stop the production-like stack
	$(COMPOSE) -f compose.prod.yaml down

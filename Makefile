# Makefile — local Docker workflow. Run `make help` for the list of targets.

SHELL := /bin/bash
.DEFAULT_GOAL := help

export UID := $(shell id -u)
export GID := $(shell id -g)

COMPOSE := docker compose
# -T keeps exec usable without a TTY (CI, scripts).
EXEC := $(COMPOSE) exec -T app
NODE := $(COMPOSE) run --rm --no-deps -T node

.PHONY: help env up down logs sh test stan cs fix lint check lock front-lint front-test build

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
	@echo "App: http://localhost:$$(grep -E '^APP_PORT=' .env | cut -d= -f2)"

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

check: lint test ## Everything CI runs for the backend

lock: ## Create package-lock.json in a node container (dependencies at least 14 days old)
	docker run --rm -v "$(CURDIR)":/app -w /app -u "$(UID):$(GID)" -e HOME=/tmp -e npm_config_cache=/tmp/.npm node:22-alpine sh bin/lock-assets

front-lint: ## Lint the frontend (ESLint)
	$(NODE) npm run lint

front-test: ## Run the frontend tests (Vitest)
	$(NODE) npm run test

build: ## Production build of the frontend into public/build
	$(NODE) npm run build

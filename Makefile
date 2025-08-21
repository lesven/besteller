# Makefile für Besteller Symfony-Anwendung
# Harmonisiert mit Referenz-Makefile: einheitliche Targets für Docker & Symfony

COMPOSE_FILE := docker-compose.yml
DC_BASE := docker
DC_ARGS := compose -f $(COMPOSE_FILE)

# Service-Namen (anpassen, falls docker-compose.yml andere Namen nutzt)
PHP_SERVICE := php
WEB_SERVICE := webserver
DB_SERVICE := database
MAILHOG_SERVICE := mailhog

.PHONY: help build up up-foreground up-d down down-remove restart ps logs logs-php exec-php console deploy deploy-prod composer-install composer-update composer-update-phpunit cache-clear cache-warmup migrate migrate-status test coverage fresh recreate-db start stop install clear-cache phpstan phpstan-fix phpstan-baseline phpstan-clear phpmd shell create-user change-password initial-install

# Default environment is development. Override by running `make <target> ENV=prod`
ENV ?= dev
ifeq ($(ENV),prod)
APP_DEBUG=0
else
APP_DEBUG=1
endif
export APP_ENV=$(ENV)
export APP_DEBUG

help: ## Zeigt diese Hilfe an
	@echo "Makefile - gängige Targets für Docker und Symfony/Scripts"
	@echo "  make build           -> Docker-Images bauen (no-cache, pull)"
	@echo "  make up              -> Docker-Compose up (detached)"
	@echo "  make up-foreground   -> Docker-Compose up (foreground)"
	@echo "  make up-d            -> Alias für detached up"
	@echo "  make down            -> Stoppt alle Compose-Container (sicher, löscht keine Volumes)"
	@echo "  make down-remove     -> Docker-Compose down (entfernt volumes & orphans)"
	@echo "  make restart         -> Restart aller Services"
	@echo "  make ps              -> Anzeigen laufender Compose-Services"
	@echo "  make logs            -> Logs aller Services (folgen)"
	@echo "  make logs-php        -> Logs des PHP-Service"
	@echo "  make exec-php        -> Interaktiv in den PHP-Container (bash)"
	@echo "  make console         -> Symfony Console ausführen im PHP-Container (nutze ARGS='...')"
	@echo "  make deploy          -> Vollständiger Deploy-Flow (GIT_PULL=1, SKIP_MIGRATIONS=1, SKIP_COMPOSER=1 möglich)"
	@echo "  make composer-install-> composer install im PHP-Container"
	@echo "  make composer-update -> composer update im PHP-Container"
	@echo "  make cache-clear     -> Symfony Cache leeren (dev & prod)"
	@echo "  make cache-warmup    -> Symfony Cache vorerwärmen"
	@echo "  make migrate         -> Doctrine-Migrations ausführen"
	@echo "  make migrate-status  -> Migration-Status anzeigen"
	@echo "  make test            -> PHPUnit-Tests im PHP-Container ausführen"
	@echo "  make fresh           -> full rebuild + composer install + migrate + cache-warmup"
	@echo "  make recreate-db     -> DB-Volume entfernen und DB neu starten"

## Build Docker images (no-cache, pull latest base images)
build: ## Baut Docker-Images (no-cache, pull)
	@echo "==> Building docker images"
	@$(DC_BASE) $(DC_ARGS) build --pull --no-cache

## Start in foreground
up-foreground: ## docker compose up (foreground)
	@echo "==> docker compose up (foreground)"
	@$(DC_BASE) $(DC_ARGS) up --build

## Start detached (default "up")
up: ## docker compose up -d (detached)
	@echo "==> docker compose up -d"
	@$(DC_BASE) $(DC_ARGS) up -d --build

## alias to match older Makefile 'start'
up-d: up
start: up

## Stop containers only (safer) - alias for older 'stop'
down: ## stop containers only, volumes preserved
	@echo "==> docker compose stop"
	@$(DC_BASE) $(DC_ARGS) stop
stop: down

## Full down: stop and remove containers, networks, volumes and orphans (legacy behavior)
down-remove: ## Entfernt Volumes & Orphans (vorsichtig)
	@echo "==> docker compose down (remove volumes & orphans)"
	@sh -c '\
printf "ACHTUNG: Dadurch werden Container, Netzwerke und Docker-Volumes gelöscht.\n"; \
printf "Willst du fortfahren? Tippe y und Enter zum Bestätigen: "; \
read -r ans; \
if [ "$$ans" = "y" ] || [ "$$ans" = "Y" ]; then \
	echo "-> Ausführen: $(DC_BASE) $(DC_ARGS) down --volumes --remove-orphans"; \
	$(DC_BASE) $(DC_ARGS) down --volumes --remove-orphans; \
else \
	echo "Abgebrochen."; \
fi'

restart: ## Neustart aller Services
	@$(DC_BASE) $(DC_ARGS) restart

ps: ## Zeigt Compose-Services
	@$(DC_BASE) $(DC_ARGS) ps

logs: ## Zeigt Container-Logs (folgen)
	@$(DC_BASE) $(DC_ARGS) logs -f --tail=200

logs-php: ## Logs des PHP-Services
	@$(DC_BASE) $(DC_ARGS) logs -f --tail=200 $(PHP_SERVICE)

## Interaktives Shell in PHP-Container
exec-php: ## Exec into PHP-Container (bash)
	@echo "==> Exec into $(PHP_SERVICE)"
	@$(DC_BASE) $(DC_ARGS) exec $(PHP_SERVICE) bash

## Symfony Console passthrough: make console ARGS="cache:clear --env=prod"
console: ## Führt php bin/console im PHP-Container aus (ARGS verwenden)
	@echo "==> Running php bin/console in $(PHP_SERVICE): $(ARGS)"
	@$(DC_BASE) $(DC_ARGS) exec -T $(PHP_SERVICE) php bin/console $(ARGS)

## Composer helpers (führen Composer im PHP-Container aus)
composer-install: ## composer install im PHP-Container
	@echo "==> composer install im $(PHP_SERVICE)"
	@$(DC_BASE) $(DC_ARGS) exec -T $(PHP_SERVICE) composer install --no-interaction --prefer-dist $(if $(filter $(ENV),prod),--no-dev --optimize-autoloader,)

composer-update: ## composer update im PHP-Container
	@echo "==> composer update im $(PHP_SERVICE)"
	@$(DC_BASE) $(DC_ARGS) exec -T $(PHP_SERVICE) composer update --no-interaction

composer-update-phpunit: ## Update nur phpunit in composer.lock
	@echo "==> composer update phpunit/phpunit im $(PHP_SERVICE)"
	@$(DC_BASE) $(DC_ARGS) exec -T $(PHP_SERVICE) composer update phpunit/phpunit --with-dependencies --no-interaction || \
		echo "composer update phpunit failed"

## Symfony cache
cache-clear: ## symfony cache:clear (dev & prod)
	@echo "==> symfony cache:clear (dev & prod)"
	@$(DC_BASE) $(DC_ARGS) exec -T $(PHP_SERVICE) php bin/console cache:clear --no-warmup || true
	@$(DC_BASE) $(DC_ARGS) exec -T $(PHP_SERVICE) php bin/console cache:clear --env=prod --no-warmup || true

cache-warmup: ## symfony cache:warmup (prod)
	@echo "==> symfony cache:warmup (prod)"
	@$(DC_BASE) $(DC_ARGS) exec -T $(PHP_SERVICE) php bin/console cache:warmup --env=prod || true

## Doctrine Migrations

migrate-status: ## doctrine:migrations:status
	@echo "==> doctrine:migrations:status"
	@$(DC_BASE) $(DC_ARGS) exec -T $(PHP_SERVICE) php bin/console doctrine:migrations:status


migrate: ## doctrine:migrations:migrate (no interaction)
	@echo "==> doctrine:migrations:migrate (no interaction)"
	@$(DC_BASE) $(DC_ARGS) exec -T $(PHP_SERVICE) php bin/console doctrine:migrations:migrate --no-interaction

## Tests (PHPUnit)

test: ## Führt PHPUnit-Tests im PHP-Container aus
	@echo "==> Running PHPUnit tests inside $(PHP_SERVICE)"
	@$(DC_BASE) $(DC_ARGS) exec -T $(PHP_SERVICE) bash -lc "if [ -f vendor/bin/phpunit ]; then vendor/bin/phpunit --colors=always; else echo 'phpunit not found, run composer install first'; exit 1; fi"

coverage: ## Erzeugt Coverage (HTML + text)
	@echo "==> Running PHPUnit coverage inside $(PHP_SERVICE)"
	@$(DC_BASE) $(DC_ARGS) exec -T $(PHP_SERVICE) /bin/sh -lc "echo '=== php -v ===' && php -v && echo '=== php -m (xdebug?) ===' && php -m | grep -i xdebug || true && if [ -f vendor/bin/phpunit ]; then mkdir -p var/coverage && XDEBUG_MODE=coverage XDEBUG_CONFIG='start_with_request=1' vendor/bin/phpunit --colors=always --coverage-html var/coverage --coverage-text; else echo 'phpunit not found, run composer install first'; exit 1; fi"

## Recreate DB: stop, remove volumes and bring up database only

recreate-db: ## Recreate DB volume and start database
	@echo "==> Recreate DB volume and start database"
	@$(DC_BASE) $(DC_ARGS) down --volumes --remove-orphans
	@$(DC_BASE) $(DC_ARGS) up -d $(DB_SERVICE)

## Full fresh flow: rebuild, start, composer install, migrate
fresh: build up composer-install migrate cache-warmup

## Deploy flow: bring new code, ensure containers, run composer, migrations, cache, assets
# Variables:
#   GIT_PULL=1           -> run 'git pull' on the host before deploying
#   SKIP_COMPOSER=1      -> skip composer install
#   SKIP_MIGRATIONS=1    -> skip doctrine migrations
#   SKIP_ASSETS=1        -> skip assets:install
deploy: ## Vollständiger Deploy-Flow (mehr Optionen via ENV vars)
	@bash -lc '\
set -e; \
echo "==> Deploy flow started"; \
if [ "$(GIT_PULL)" = "1" ]; then \
	echo "-> Running git pull on host"; \
	git pull || { echo "git pull failed"; exit 1; }; \
fi; \
echo "-> Pulling images and starting services"; \
$(DC_BASE) $(DC_ARGS) pull || true; \
$(DC_BASE) $(DC_ARGS) up -d --build; \
if [ "$(SKIP_COMPOSER)" != "1" ]; then \
	echo "-> composer install in $(PHP_SERVICE)"; \
	$(DC_BASE) $(DC_ARGS) exec -T $(PHP_SERVICE) composer install --no-interaction --prefer-dist $(if $(filter $(ENV),prod),--no-dev --optimize-autoloader,) || { echo "composer install failed"; exit 1; }; \
else \
	echo "-> Skipping composer install"; \
fi; \
if [ "$(SKIP_MIGRATIONS)" != "1" ]; then \
	echo "-> Running doctrine migrations"; \
	$(DC_BASE) $(DC_ARGS) exec -T $(PHP_SERVICE) php bin/console doctrine:migrations:migrate --no-interaction || { echo "migrations failed"; exit 1; }; \
else \
	echo "-> Skipping migrations"; \
fi; \
echo "-> Clearing and warming cache (prod)"; \
$(DC_BASE) $(DC_ARGS) exec -T $(PHP_SERVICE) php bin/console cache:clear --no-warmup || true; \
$(DC_BASE) $(DC_ARGS) exec -T $(PHP_SERVICE) php bin/console cache:warmup --env=prod || true; \
if [ "$(SKIP_ASSETS)" != "1" ]; then \
	echo "-> Installing assets (if symfony/asset available)"; \
	$(DC_BASE) $(DC_ARGS) exec -T $(PHP_SERVICE) php bin/console assets:install --symlink --relative || true; \
else \
	echo "-> Skipping assets"; \
fi; \
echo "==> Deploy flow finished"; \
	'

deploy-prod: ## Deploy für Produktion (ENV=prod)
	@$(MAKE) deploy ENV=prod

## Backwards-compatible aliases / project-specific helpers
install: composer-install ## alias: install -> composer-install

clear-cache: cache-clear ## alias

phpstan: ## Führt statische Analyse mit PHPStan aus
	@$(DC_BASE) $(DC_ARGS) exec -T $(PHP_SERVICE) vendor/bin/phpstan analyse --memory-limit=512M || true

phpstan-fix:
	@$(DC_BASE) $(DC_ARGS) exec -T $(PHP_SERVICE) vendor/bin/phpstan analyse --memory-limit=1G || true

phpstan-baseline:
	@$(DC_BASE) $(DC_ARGS) exec -T $(PHP_SERVICE) vendor/bin/phpstan analyse --memory-limit=512M --generate-baseline || true

phpstan-clear:
	@$(DC_BASE) $(DC_ARGS) exec -T $(PHP_SERVICE) vendor/bin/phpstan clear-result-cache || true

phpmd:
	@$(DC_BASE) $(DC_ARGS) exec -T $(PHP_SERVICE) vendor/bin/phpmd src text phpmd.xml || true

shell: exec-php

create-user: ## Erstellt einen Admin-Benutzer (EMAIL und PASSWORD als Parameter)
	@$(DC_BASE) $(DC_ARGS) exec -T $(PHP_SERVICE) php bin/console app:user:create $(EMAIL) $(PASSWORD)

change-password: ## Ändert Passwort eines Benutzers (EMAIL und PASSWORD als Parameter)
	@$(DC_BASE) $(DC_ARGS) exec -T $(PHP_SERVICE) php bin/console app:user:change-password $(EMAIL) $(PASSWORD)

initial-install: ## Initialer Setup-Flow (build, up, install, cache, migrate, create-user)
	@$(DC_BASE) $(DC_ARGS) down || true
	@$(DC_BASE) $(DC_ARGS) build --no-cache
	@$(DC_BASE) $(DC_ARGS) up -d
	@$(MAKE) composer-install
	@$(MAKE) cache-clear
	@$(MAKE) migrate
	@$(MAKE) create-user EMAIL="example@admin.dev" PASSWORD="administrator1234567890"
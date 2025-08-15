# Makefile für Besteller Symfony Anwendung

.PHONY: help build start stop restart install migrate clear-cache test test-db-create test-schema test-setup test-functional

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
	@echo "Verfügbare Befehle:"
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-20s\033[0m %s\n", $$1, $$2}'

deploy-prod: ## Bereitstellung für Produktion (ENV=prod wird automatisch gesetzt)
	@$(MAKE) deploy ENV=prod 

deploy: ## Führt alle Schritte für die Bereitstellung aus
	git reset --hard HEAD
	git pull
	@$(MAKE) build
	@$(MAKE) install
	@$(MAKE) start

build: ## Baut Docker Container
	docker compose build

start: ## Startet die Anwendung
	docker compose up -d

stop: ## Stoppt die Anwendung
	docker compose down

restart: ## Neustart der Anwendung
	docker compose restart

install: ## Installiert Symfony und Abhängigkeiten
	docker compose exec -e APP_ENV=$(ENV) -e APP_DEBUG=$(APP_DEBUG) php composer install $(if $(filter $(ENV),prod),--no-dev --optimize-autoloader,)
	docker compose exec -e APP_ENV=$(ENV) -e APP_DEBUG=$(APP_DEBUG) php bin/console doctrine:migrations:migrate --no-interaction

migrate: ## Führt Datenbank-Migrationen aus
	docker compose exec php bin/console doctrine:migrations:migrate --no-interaction

clear-cache: ## Löscht den Symfony Cache
	docker compose exec php bin/console cache:clear

test: ## Führt Tests aus
	docker compose exec php vendor/bin/phpunit

# --- Test helper targets (Docker) -------------------------------------------------
test-db-create: ## Erstellt die Test-Datenbank und setzt Rechte (MariaDB)
	docker compose exec db bash -lc "mysql -uroot -proot -e \"CREATE DATABASE IF NOT EXISTS besteller_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; GRANT ALL PRIVILEGES ON besteller_test.* TO 'besteller'@'%' IDENTIFIED BY 'besteller'; FLUSH PRIVILEGES;\""

test-schema: ## Baut das Test-Schema auf: migrations (empfohlen) oder fallback schema:create
	docker compose exec php bash -lc "php bin/console doctrine:migrations:migrate --env=test --no-interaction || php bin/console doctrine:schema:create --env=test --no-interaction"

test-setup: test-db-create test-schema ## Bereitet die Test-DB vor (DB anlegen + Schema)
	@echo "Test DB prepared"

test-functional: test-setup ## Führt die funktionalen Tests (Test-DB wird vorbereitet)
	docker compose exec php bash -lc "vendor/bin/phpunit -c phpunit.xml.dist $${TEST:-tests/}"


phpstan: ## Führt statische Analyse mit PHPStan aus
	docker compose exec php vendor/bin/phpstan analyse --memory-limit=512M

phpstan-fix: ## Führt PHPStan mit höherem Memory-Limit aus (1GB)
	docker compose exec php vendor/bin/phpstan analyse --memory-limit=1G

phpstan-baseline: ## Erstellt eine PHPStan Baseline für bestehende Fehler
	docker compose exec php vendor/bin/phpstan analyse --memory-limit=512M --generate-baseline

phpstan-clear: ## Löscht PHPStan Cache
	docker compose exec php vendor/bin/phpstan clear-result-cache

phpmd: ## Prüft den Code mit PHP Mess Detector
	docker compose exec php vendor/bin/phpmd src text phpmd.xml

logs: ## Zeigt Container Logs
	docker compose logs -f

shell: ## Öffnet eine Shell im PHP Container
	docker compose exec php bash

create-user: ## Erstellt einen Admin-Benutzer (EMAIL und PASSWORD als Parameter)
	docker compose exec php bin/console app:user:create $(EMAIL) $(PASSWORD)

change-password: ## Ändert Passwort eines Benutzers (EMAIL und PASSWORD als Parameter)
	docker compose exec php bin/console app:user:change-password $(EMAIL) $(PASSWORD)

initial-install:
	docker compose down
	docker compose build --no-cache
	docker compose up -d
	@$(MAKE) install
	@$(MAKE) clear-cache
	@$(MAKE) migrate
	@$(MAKE) create-user EMAIL="example@admin.dev" PASSWORD="administrator1234567890"
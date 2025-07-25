# Makefile für Besteller Symfony Anwendung

.PHONY: help build start stop restart install migrate clear-cache test

help: ## Zeigt diese Hilfe an
	@echo "Verfügbare Befehle:"
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-20s\033[0m %s\n", $$1, $$2}'

build: ## Baut Docker Container
	docker-compose build

start: ## Startet die Anwendung
	docker-compose up -d

stop: ## Stoppt die Anwendung
	docker-compose down

restart: ## Neustart der Anwendung
	docker-compose restart

install: ## Installiert Symfony und Abhängigkeiten
	docker-compose exec php composer install
	docker-compose exec php bin/console doctrine:migrations:migrate --no-interaction

migrate: ## Führt Datenbank-Migrationen aus
	docker-compose exec php bin/console doctrine:migrations:migrate --no-interaction

clear-cache: ## Löscht den Symfony Cache
	docker-compose exec php bin/console cache:clear

test: ## Führt Tests aus
	docker-compose exec php bin/phpunit

logs: ## Zeigt Container Logs
	docker-compose logs -f

shell: ## Öffnet eine Shell im PHP Container
	docker-compose exec php bash

create-user: ## Erstellt einen Admin-Benutzer (EMAIL und PASSWORD als Parameter)
	docker-compose exec php bin/console app:user:create $(EMAIL) $(PASSWORD)

change-password: ## Ändert Passwort eines Benutzers (EMAIL und PASSWORD als Parameter)
	docker-compose exec php bin/console app:user:change-password $(EMAIL) $(PASSWORD)

# Besteller - Symfony Docker Setup

Eine vollständig funktionsfähige Symfony 7.3 Anwendung für Mitarbeiterausstattung, deployed mit Docker.

## 🚀 Schnellstart

### Voraussetzungen
- Docker & Docker Compose
- Make (optional, für Convenience-Befehle)

### Installation & Start

```bash
# Repository klonen
git clone <repository-url>
cd besteller

# Container bauen und starten
docker-compose build
docker-compose up -d

# Anwendung ist verfügbar unter: http://localhost:8081
```

### Standard Admin-Benutzer
- **E-Mail:** admin@besteller.local
- **Passwort:** AdminPassword123456

## 📋 Verfügbare Befehle

### Docker Compose Befehle
```bash
# Container starten
docker-compose up -d

# Container stoppen
docker-compose down

# Container neu bauen
docker-compose build

# Logs anzeigen
docker-compose logs -f php
docker-compose logs -f web
```

### Makefile Befehle (optional)
```bash
make help          # Zeigt alle verfügbaren Befehle
make start          # Startet die Anwendung
make stop           # Stoppt die Anwendung
make build          # Baut Container neu
make logs           # Zeigt Logs
make shell          # Öffnet Shell im PHP Container
make migrate        # Führt Migrationen aus
make clear-cache    # Löscht Symfony Cache
make phpstan        # Führt statische Analyse aus
make phpmd         # Prüft den Code mit PHP Mess Detector
```

### Benutzerverwaltung
```bash
# Neuen Admin-Benutzer erstellen
docker-compose exec php bin/console app:user:create admin@example.com MeinSicheresPasswort123

# Passwort ändern
docker-compose exec php bin/console app:user:change-password admin@example.com NeuesPasswort123456
```

### Datenbank-Befehle
```bash
# Migrationen ausführen
docker-compose exec php bin/console doctrine:migrations:migrate

# Schema validieren
docker-compose exec php bin/console doctrine:schema:validate

# Neue Migration erstellen
docker-compose exec php bin/console doctrine:migrations:generate
```

## 🏗️ Architektur

### Container-Setup
- **web (nginx):** Webserver, Port 8081
- **php (php-fpm):** Symfony 7.3 Anwendung  
- **db (mariadb):** Datenbank (nur intern)

### Verzeichnisstruktur
```
besteller/
├── config/           # Symfony Konfiguration
├── docker/          # Docker Konfigurationsdateien
│   ├── nginx/       # Nginx Konfiguration
│   └── php/         # PHP-FPM Dockerfile & Scripts
├── migrations/      # Datenbank-Migrationen
├── public/          # Web-Root
├── src/             # PHP Quellcode
│   ├── Command/     # Console Commands
│   ├── Controller/  # MVC Controller
│   ├── Entity/      # Doctrine Entities
│   └── Service/     # Business Logic Services
├── templates/       # Twig Templates
├── docker-compose.yml
├── Makefile
└── README.md
```

## 🎯 Funktionen

### Für Führungskräfte (ohne Login)
- Zugriff über personalisierte Links
- Ausfüllen von Stücklisten
- Automatische E-Mail-Benachrichtigung
- Schutz vor Mehrfacheinsendungen

### Für Administratoren
- Login mit starken Passwörtern (min. 16 Zeichen)
- Erstellen und Bearbeiten von Stücklisten
- Verwalten von Gruppen mit verschiedenen Input-Typen:
  - Checkboxes (Mehrfachauswahl)
  - Radio Buttons (Einzelauswahl) 
  - Textfelder
- HTML E-Mail-Templates mit Platzhaltern
- Einsicht in alle Einsendungen
- Upload/Download von E-Mail-Templates

### Link-Format für Führungskräfte
```
http://localhost:8081/checklist/{checklist_id}?name=Max%20Mustermann&mitarbeiter_id=12345&email=max@example.com
```

### E-Mail-Template Platzhalter
- `{{name}}` - Name der Person
- `{{mitarbeiter_id}}` - Mitarbeiter-ID
- `{{stückliste}}` - Name der Stückliste
- `{{auswahl}}` - Strukturierte Ausgabe aller Auswahlen
- `{{rueckfragen_email}}` - Hinterlegte Rückfragen-Adresse

## 🛠️ Entwicklung

### Shell-Zugriff
```bash
# PHP Container Shell
docker-compose exec php bash

# Symfony Console
docker-compose exec php bin/console

# Composer Commands
docker-compose exec php composer install
```

### Cache Management
```bash
# Cache löschen
docker-compose exec php bin/console cache:clear

# Cache warmup
docker-compose exec php bin/console cache:warmup
```

### Debugging
```bash
# Container Status prüfen
docker-compose ps

# Container Logs
docker-compose logs php
docker-compose logs web
docker-compose logs db

# In Container debuggen
docker-compose exec php bash
docker-compose exec web sh
```

## 🔒 Sicherheit

- **Passwort-Richtlinie:** Mindestens 16 Zeichen für Admin-Benutzer
- **CSRF-Schutz:** Aktiviert für alle Formulare
- **Access Control:** Admin-Bereich erfordert Authentication
- **Link-basierter Zugang:** Öffentliche Formulare nur über Links erreichbar
- **Unique Constraints:** Verhindert Mehrfacheinsendungen pro Mitarbeiter

## 📊 Datenbank

### Entities
- **User:** Admin-Benutzer
- **Checklist:** Stücklisten mit E-Mail-Templates
- **ChecklistGroup:** Gruppen innerhalb von Stücklisten
- **GroupItem:** Einzelne Input-Elemente (Checkbox, Radio, Text)
- **Submission:** Einsendungen mit Unique Constraint

### Migrationen
Die Datenbank wird automatisch beim ersten Start initialisiert. Neue Migrationen können jederzeit ausgeführt werden.

## 🚨 Troubleshooting

### Port bereits belegt
```bash
# Andere Ports verwenden in docker-compose.yml
ports:
  - "8082:80"  # statt 8081:80
```

### Container startet nicht
```bash
# Alle Container stoppen und neu starten
docker-compose down
docker-compose up -d

# Container Logs prüfen
docker-compose logs
```

### Datenbankverbindung fehlgeschlagen
```bash
# Warten bis DB bereit ist
docker-compose logs db

# Container neu starten
docker-compose restart php
```

### Symfony Fehler
```bash
# Cache löschen
docker-compose exec php bin/console cache:clear

# Autoloader neu generieren
docker-compose exec php composer dump-autoload
```

## 📝 Deployment

Für Produktivumgebung:
1. `.env.local` mit Produktions-Einstellungen anpassen
2. `APP_ENV=prod` setzen
3. Starke Passwörter für DB und APP_SECRET verwenden
4. SSL/TLS Terminierung vor nginx einrichten
5. Backup-Strategie für MariaDB implementieren

## 💡 Tipps

- Verwenden Sie das Makefile für häufige Befehle
- Prüfen Sie regelmäßig die Container-Logs
- Backup der Datenbank vor Migrations
- Testen Sie Link-Generierung vor Versand an Führungskräfte
- Dokumentieren Sie Ihre E-Mail-Templates

## 📞 Support

Bei Problemen:
1. Container-Logs prüfen: `docker-compose logs`
2. Container-Status prüfen: `docker-compose ps`  
3. Shell-Zugriff für Debugging: `docker-compose exec php bash`
4. Cache löschen: `make clear-cache` oder `docker-compose exec php bin/console cache:clear`

# Besteller


# User Stories und Anforderungen – Stücklisten-Applikation für Mitarbeiterausstattung

## Einleitung

Ziel dieser Anwendung ist die digitale Erfassung von benötigter Ausstattung für neue Mitarbeitende. Führungskräfte erhalten dazu individuelle Links, über die sie eine spezifische Stückliste ausfüllen und absenden können. Die Daten werden gespeichert und per E-Mail an eine zentrale Zieladresse sowie an die Führungskraft selbst verschickt. Die Konfiguration erfolgt über eine Admin-Weboberfläche.

---

## User Stories

### 1. Nicht eingeloggte Nutzer (Führungskräfte)

#### US-1: Stückliste über personalisierten Link aufrufen
**Als** Führungskraft  
**möchte ich** über einen individuellen Link eine vorausgewählte Stückliste mit vorausgefüllten Daten aufrufen  
**damit** ich schnell und ohne Login die benötigte Ausstattung angeben kann.

**Akzeptanzkriterien:**
- Der Link enthält:
  - `stückliste_id`
  - `name`
  - `mitarbeiter_id`
  - `email` (E-Mail der Führungskraft)
- Beim Öffnen werden alle übergebenen Werte angezeigt und sind nicht änderbar.

#### US-2: Ausstattung auswählen
**Als** Führungskraft  
**möchte ich** pro Abschnitt passende Optionen auswählen oder Textfelder ausfüllen  
**damit** die Ausstattung korrekt und vollständig übermittelt wird.

**Akzeptanzkriterien:**
- Gruppen werden untereinander mit Überschrift und Beschreibung angezeigt.
- Innerhalb von Gruppen gibt es:
  - Checkbox-Elemente (Mehrfachauswahl)
  - Radiobuttons (Einzelauswahl)
  - Freitextfelder mit Label
- Nur gültige Auswahlkombinationen sind möglich.

#### US-3: Formular absenden
**Als** Führungskraft  
**möchte ich** nach dem Ausfüllen die Auswahl abschicken  
**damit** die Verantwortlichen die Information erhalten.

**Akzeptanzkriterien:**
- Nach dem Absenden:
  - wird eine E-Mail generiert und an die Stücklisten-Zieladresse sowie die Führungskraft gesendet.
  - wird der E-Mail-Text aus dem konfigurierten Template gerendert (Platzhalter werden ersetzt).
  - wird die Einsendung dauerhaft gespeichert.
- Danach erscheint eine Erfolgsmeldung oder (falls bereits abgesendet) ein Hinweis:  
  *„Für diese Person wurde die Stückliste bereits ausgefüllt.“*

---

### 2. Eingeloggte Admin-Nutzer

#### US-4: Als Admin anmelden
**Als** Administrator  
**möchte ich** mich mit E-Mail-Adresse und Passwort anmelden  
**damit** ich Zugriff auf die Verwaltung der Stücklisten habe.

**Akzeptanzkriterien:**
- Passwort muss mindestens 16 Zeichen lang sein.
- Login erfolgt rein über Benutzername (E-Mail) und Passwort.

#### US-5: Stücklisten verwalten
**Als** Admin  
**möchte ich** Stücklisten erstellen, bearbeiten und löschen  
**damit** ich verschiedene Konfigurationen pflegen kann.

**Akzeptanzkriterien:**
- Stückliste hat:
  - Titel
  - Ziel-E-Mail-Adresse
  - E-Mail-Template (HTML)
- Gruppen können hinzugefügt, bearbeitet oder gelöscht werden.
- In Gruppen können hinzugefügt werden:
  - Checkbox-Auswahl mit mehreren Optionen
  - Radiobutton-Auswahl mit mehreren Optionen
  - Freitextfeld mit Label
  - Beschreibungstext für die Gruppe

#### US-6: Einsendungen einsehen
**Als** Admin  
**möchte ich** alle bereits abgeschickten Stücklisten-Einsendungen sehen können  
**damit** ich den Überblick über übermittelte Daten habe.

**Akzeptanzkriterien:**
- Liste pro Stückliste mit Datum, Name, Mitarbeiter-ID und gerendertem E-Mail-Inhalt
- Einsendung löschen können um sie erneut absendbar zu machen
- Keine Downloadfunktion erforderlich

#### US-7: E-Mail-Templates verwalten
**Als** Admin  
**möchte ich** das E-Mail-Template als HTML-Datei hochladen und herunterladen können  
**damit** ich es extern bearbeiten und im System verwenden kann.

**Akzeptanzkriterien:**
- Vorschau des aktuellen Templates als reiner HTML-Text in einer Textbox
- Upload ersetzt das bisherige Template

#### US-9: Benutzer verwalten verwalten
**Als** Admin  
**möchte ich** die benutzer des Stücklisten systems verwalten
**damit** ich anderen Kollegen ermöglichen kann Stücklsiten zu erstellen und zu bearbeiten.

**Akzeptanzkriterien:**
- E-Mail adresse 
- Passwort (16 Zeichen)
- User bearbeiten
- User löschen
---

## Platzhalter im E-Mail-Template

Die folgenden Platzhalter stehen im HTML-Template zur Verfügung:

| Platzhalter       | Bedeutung                                               |
|-------------------|----------------------------------------------------------|
| `{{name}}`        | Name/Vorname der Person (aus Link)                       |
| `{{mitarbeiter_id}}` | Mitarbeitenden-ID (aus Link)                          |
| `{{stückliste}}`  | Name der Stückliste                                      |
| `{{auswahl}}`     | Strukturierte Ausgabe aller getätigten Auswahlen nach Gruppe |

---

#### US-8: E-Mail Einstellugnen verwalten
**Als** Admin  
**möchte ich** die Konfiguration des E-Mail Servers im Backend vornehmen können
**damit** ich einen beliebigen SMTP Server hinterlegen kann.

**Akzeptanzkriterien:**
- Server IP Adresse
- Server Port
- Username Passwort optional
- ignorieren von SSL Zertifikaten



## Nicht-funktionale Anforderungen

| Bereich               | Anforderung                                                                 |
|------------------------|----------------------------------------------------------------------------|
| Design                | Schlichtes neutrales UI, basierend auf Bootstrap                           |
| Sprache               | Deutsch (keine Mehrsprachigkeit erforderlich)                              |
| Datenschutz           | Einsendungen werden dauerhaft gespeichert, keine automatische Löschung     |
| Sicherheit            | Public-Formulare nur per Link aufrufbar; Login nur mit starkem Passwort    |
| Benutzerverwaltung    | Keine Selbstregistrierung, Benutzerverwaltung erfolgt über Symfony-Commands |
| Passwort-Reset        | Passwortänderung via Symfony CLI-Command                                    |
| Mehrfacheinsendungen  | Pro Kombination aus Stückliste + Mitarbeitenden-ID ist nur eine Einsendung erlaubt |
| Hosting               | Deployment erfolgt via Docker Compose, Symfony 7.3                          |
| API                   | Keine externe API, vollständige Bedienung über Weboberfläche               |

---

## Technische Rahmenbedingungen

- **Framework:** Symfony 7.3, MariaDB, Bootstrap Theme
- **Bereitstellung:** Docker Compose (z. B. mit nginx, PHP-FPM, PostgreSQL oder MariaDB)
- **Frontend:** Bootstrap für UI-Komponenten
- **Backend:** Symfony Commands zur Userverwaltung
- **Keine API-Anbindung oder externes SSO erforderlich**

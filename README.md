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
  - `checklist_id`
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
 - Liste pro Stückliste mit Datum, Name, Mitarbeiter-ID und E-Mail-Inhalt über einen Link in neuem Fenster
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

### Bestell-E-Mail-Templates (für abgeschickte Stücklisten)

| Platzhalter       | Bedeutung                                               |
|-------------------|----------------------------------------------------------|
| `{{name}}`        | Name/Vorname der Person (aus Link)                       |
| `{{mitarbeiter_id}}` | Mitarbeitenden-ID (aus Link)                          |
| `{{stückliste}}`  | Name der Stückliste                                      |
| `{{auswahl}}`     | Strukturierte Ausgabe aller getätigten Auswahlen nach Gruppe |
| `{{rueckfragen_email}}` | Hinterlegte Rückfragen-Adresse |

### Link-Versand-Templates (für den Versand von Stücklisten-Links)

| Platzhalter       | Bedeutung                                               |
|-------------------|----------------------------------------------------------|
| `{{recipient_name}}` | Name des Empfängers der E-Mail (Führungskraft)       |
| `{{empfaenger_name}}` | Alias für `{{recipient_name}}` (Rückwärtskompatibilität) |
| `{{person_name}}`    | Name der Person, für die bestellt wird                |
| `{{mitarbeiter_id}}` | Mitarbeitenden-ID der zu bestellenden Person         |
| `{{intro}}`          | Individueller Einleitungstext beim Versand          |
| `{{link}}`           | Der generierte Link zur Stückliste                  |
| `{{stückliste}}`     | Name der Stückliste                                  |

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

#### US-10: Rückfragen ermöglichen
**Als** Admin  
**möchte ich** bei jeder Liste auch eine E-Mail für Rückfragen hinterlegen können
**damit** ich in der E-Mail an die Führungskraft auch sowas schreiben kann wie "zu rückfragen zu dieser Bestellung wende dich an x@x.de"

**Akzeptanzkriterien:**
- optionales E-Mail Feld
- email wird auf validität geprüft
- das neue Feld steht bei den Email Variablen zur Verfügung
- das Standard Mail Template hat die neuen Möglichkeiten schon integriert

#### US-11: Stücklisten duplizieren
**Als** Admin  
**möchte ich** eine komplette Stückliste mit allen Inhalten, ohne die Einsendungen natürlich dpulizieren können
**damit** ich leicht für unsere einzlenen Standorte und Abteilugnen angepaste Stücklisten erstellen kann

**Akzeptanzkriterien:**
- alle Gruppen werden dupliziert
- alle Elemente in den Gruppen werden dupliziert
- die neue Liste heßt inital Duplikat von originalname

#### US-12: Versand eines Stücklisten links 
**Als** Admin  
**möchte ich** eeinen Link zu einer Stückliste versenden können
**damit** ich Bestellugnen starten kann und den Leuten nciht manuell einen Link generieren muss

**Akzeptanzkriterien:**
- Empfängername und Empfängeremail müssen hinterlegt werden
- PersonenID muss hinterlegt werden
- Personenname für die die Bestellung ist kann hinterlegt werden
- ein eigenes Email Template für den Versand ist hinterlegt
- eine individuelle Einleitungs für die Email kann beim Versand hinterlegt werden

#### US-13: Versand eines Stücklisten links über eine externe Software ermöglichen
**Als** externe Software  
**möchte ich** über einen API Aufruf einen Link zu einer Stückliste versenden können
**damit** ich Bestellugnen aus einem anderen Programm heraus starten kann und nicht manuell im Bestellprogramm den Versand starten muss

**Akzeptanzkriterien:**
- Empfängername und Empfängeremail müssen übergeben werden
- PersonenID muss übergeben werden
- Personenname für die die Bestellung übergeben hinterlegt werden
- es wird das Template der jeweiligen Checkliste genutzt
- eine individuelle Einleitungs für die Email kann beim Versand über die API übergeben  werden
- Aufruf ist ein Curl aufruf

#### US-14: Beispiellink soll immer unique sein
**Als** Administrator
**möchte ich** dass der Beispiellink zu einer Stücklist eimmer funktioniert und nicht schon einmal genutzt wurde
**damit** ich immer problemlos den link testen kann

**akzeptanzkriterien**
 - mitarbeiter_id soll eine GUID sein die bei jeden Aufruf von admin_checklist_edit neu generiert wird damit ich immmer einen uniqen link habe


 #### US-15: Es Soll möglich sein einen Firmennamen mit zu übergeben
**Als** Administrator
**möchte ich** dass der Link auch einen optionalen Firmennamen enthalten kann 
**damit ich** unsere Tochtergesellschaften darüber klassifizieren kann

**akzeptanzkriterien**
 - company_name soll als parameter mit übergeben werden können
 - der company_name muss über das formular admin_checklist_send_link eintragbar sein
- company_name ist ein stirng, max 128 Zeichen
- darf leer sein
- muss bei den einsendungen mit gespeichert werden
- soll unter admin_submissions_checklist angezeigt werden

 #### US-16: Es Soll möglich sein das Email Template für den Vorgesetzten pro Stückliste anzupassen
**Als** Administrator
**möchte ich** dass E-Mail Template für die Bestätigungsmail an den Vorgesetzen anpassen können und das soll für jede Stücklsite individuell möglich sein
**damit ich** da jederzeit passende Informationen reinmachen kann ohne Programmieraufwand zu haben und damit das ganze konsistent zu den normalen Bestellmails sind, die ja auch Stücklistenindividuell sind

**akzeptanzkriterien**
 - das Standardtemplate aus getConfirmationTemplate bleibt erhalten
 - wie bei den anderen Mailtemapltes unter admin_checklist_link_template kann ich direkt bearbeiten oder eine Datei hochladen
 - ich kann das aktuelle Temaplte runterladen
 - der Link zum besarbeiten befindet sich im admin_checklist_edit controller im  E-Mail Templates Block unterhalb vom BEstellemailtemaplte verwalten button und funktioniert analog zu admin_checklist_email_template
 - beide funktionalitäten benutzen den gleichen controller um Code duplikation zu vermeiden

#### US-17: Versand von ungültigen und doppelten IDs verhindern
**Als** Administrator
**möchte ich** dass Versand einer Bestellmail geprüft wird ob die Personen-ID gültig und frei ist
**damit ich** nicht aus Versehen einen Link versende der nicht funktioniert

**akzeptanzkriterien**
 - wenn es die Kombination Personen-ID für die gewählte checklist_id bereits in der Datenbank gibt kommt eine fehlermeldung

#### US-18: Automatisch zufällige Personen-Id erzeugen
**Als** Administrator
**möchte ich** beim Versand eine zufällige Personenid erzeugen können 
**damit ich** mir nicht selber was pseudozufälliges aussuchen muss wenn es mal keine nummer gibt

**akzeptanzkriterien**
 - wenn das Feld personenId leer gelassen wird, soll eine UUID genommen werden

#### US-19: Datenbank fixtures für die Listen
**Als** Entwickler
**möchte ich** mit einem einzigen Command die Datenbank zurücksetzen und mit IT-Ausstattungs-Fixtures befüllen
**damit ich** die Anwendung mit realistischen Testdaten für verschiedene Abteilungen schnell prüfen und weiterentwickeln kann.

**Rollenmatrix:**
**Funktionsübersicht:**

- Benutzerverwaltung (User anlegen, bearbeiten, löschen): nur Administrator
- Stücklisten erstellen/bearbeiten/löschen: Administrator, Editor
- Stücklisten versenden: Administrator, Versender
- Rollen ändern: nur Administrator (nicht für sich selbst)
- Einsendungen einsehen/löschen: Administrator, Editor
- E-Mail-Templates verwalten: Administrator, Editor
- Zugriff auf API-Endpunkte:
  - POST /users: nur Administrator
  - POST /checklists: Administrator, Editor
  - POST /send: Administrator, Versender
  - GET /submissions: Administrator, Editor

| Rolle         | Benutzerverwaltung | Stücklisten erstellen/bearbeiten/löschen | Stücklisten versenden | Eigene Rolle ändern |
|--------------|--------------------|------------------------------------------|----------------------|---------------------|
| Administrator | ✅                 | ✅                                       | ✅                   | ❌                  |
| Editor        | ❌                 | ✅                                       | ❌                   | ❌                  |
| Versender     | ❌                 | ❌                                       | ✅                   | ❌                  |

**Akzeptanzkriterien**
***✅ Command-Ausführung***

- Command app:database:fixtures löscht nur Geschäftsdaten (Checklisten, Gruppen, Items, Submissions), bestehende User-Accounts bleiben erhalten
- Command erstellt neue Fixture-Daten in einem Durchgang
- Command läuft nur in der Entwicklungsumgebung (APP_ENV=dev)

***✅ Datenstruktur***

- 3 Abteilungen: IT/Entwicklung, Marketing, Sonstige
- Alle 3 Abteilungen haben identische Hardware-Kategorien: Computer/Laptops, Monitore, Peripherie, Software/Lizenzen, Mobilgeräte, Büroausstattung
- 3-5 Hardware-Items pro Kategorie
- 2-7 Varianten pro Hardware-Item
- Jedes Hardware-Item hat genau einen Eingabetyp (entweder Checkboxen ODER Radiobuttons ODER Freitext)

***✅ Eingabetypen***

- Checklisten-Varianten (Mehrfachauswahl)
- Radio-Button-Varianten (Einfachauswahl)
- Freitext-Varianten

***✅ Abteilungs-spezifische Inhalte***

- IT/Entwicklung: Hochwertige Hardware, Entwicklungstools
- Marketing: Design-orientierte Ausstattung
- Sonstige: Standard-Büroausstattung

#### US-20: Nur Administratoren können auf die Benutzerverwaltung zugreifen, Editoren auf die Stücklisten editierung und Versender auf den Versand

**Als Betreiber** möchte ich verschiedene Rollen für die Applikation einbauen
**damit** nur berechtigte Nutzer Zugriff auf sensible Funktionen haben und die Administration entlastet wird.


**Akzeptanzkriterien**
- Alle bestehenden User sind standardmäßig Admin.
- Bei Neuanlage kann die Rolle Versender, Editor oder Administrator ausgewählt werden.
- Administratoren können auf die Benutzerverwaltung zugreifen und alle Funktionen nutzen.
- Editoren können Stücklisten erstellen, bearbeiten und löschen, aber keine Benutzer verwalten oder versenden.
- Versender können Stücklisten versenden, aber keine Benutzer verwalten oder Stücklisten bearbeiten.
- Die Sichtbarkeit von Menüpunkten und Seiten ist abhängig von der Rolle.
- Der Zugriff auf API-Endpunkte und Controller-Logik ist rollenbasiert eingeschränkt.
- Nur Administratoren können die Rolle eines Nutzers ändern, aber nicht die eigene Rolle.
- Jeder Nutzer hat immer genau eine Rolle.
- Testbarkeit: Ein Nutzer mit jeder Rolle wird angelegt und es wird geprüft, dass jeweils nur die erlaubten Funktionen sichtbar und nutzbar sind (z. B. Editor sieht keine Benutzerverwaltung, Versender kann keine Stücklisten bearbeiten). 
- im Dashboard unter /admin sind jeweils nur die Buttons vorhanden die für die jeweilige ROlle sinnvoll sind. Admin und Bearbeiter sieht alle, versender bekommt nur einen zum versand angeboten
- Mit der Rolle Versender werden ich nach dem erfolgreichen absenden auf das Dashboard weitergeleitet und bekomme dort eine Flashmessage mit der info dass die mail versendet wurde angezeigt 

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

## Beispiel: Link über die API generieren

Mit einem POST-Request an `/api/generate-link` kann ein gültiger Link zu einer vorhandenen Stückliste erstellt werden. Beispielaufruf:

```bash
curl -X POST https://besteller.example.com/api/generate-link \
     -H 'Content-Type: application/json' \
     -d '{
           "stückliste_id": 123,
           "mitarbeiter_name": "Max Muster",
           "mitarbeiter_id": "abc-123",
           "email_empfänger": "chef@example.com"
         }'
```

Die Antwort enthält den öffentlichen Link zur Auswahlseite:

```json
{
  "link": "https://besteller.example.com/auswahl?list=123&name=Max%20Muster&id=abc-123&email=chef@example.com"
}
```

## Beispiel: Link per E-Mail versenden

Mit einem POST-Request an `/api/send-link` kann der Link inklusive E-Mail direkt
an eine Führungskraft geschickt werden. Beispiel:

```bash

## CLI: Benutzerverwaltung & Rollen

Dieses Repository bietet ein Symfony-Console-Command zum Anlegen von Benutzern. Alle Operationen laufen innerhalb des Docker-Compose Stacks (lokal laufen Dienste nicht direkt auf dem Host).

- Standard: neu angelegte Benutzer erhalten die Rolle `ROLE_ADMIN`, sofern nicht anders angegeben.
- Verfügbare Rollen: `ROLE_ADMIN`, `ROLE_EDITOR`, `ROLE_SENDER`.

Beispiele (aus dem Projekt-Root):

```bash
# Tests lokal über Docker-Compose (Makefile alias)
make test

# Neuen Benutzer anlegen (Standardrolle ADMIN)
docker compose exec php bin/console app:user:create admin@example.com 'sehrLangesPasswort1234'

# Neuen Benutzer mit spezifischer Rolle anlegen
docker compose exec php bin/console app:user:create editor@example.com 'sehrLangesPasswort1234' --role=ROLE_EDITOR
```

Hinweis: Das Passwort muss mindestens 16 Zeichen lang sein.

## Hinweise zur Menü-Sichtbarkeit (Admin UI)

Die Sichtbarkeit von Admin-Menüpunkten sollte mit Twig und Symfony-Access-Control gesteuert werden. Beispiel (Twig):

```twig
{# Nur Admins sehen die Benutzerverwaltung #}
{% if is_granted('ROLE_ADMIN') %}
  <a href="{{ path('admin_users') }}">Benutzerverwaltung</a>
{% endif %}

{# Editor/sender bezogene Menüpunkte #}
{% if is_granted('ROLE_EDITOR') %}
  <a href="{{ path('admin_checklists') }}">Checklisten</a>
{% endif %}
```

Das Backend setzt die eigentliche Zugriffskontrolle über `IsGranted`-Attribute und eine Role-Hierarchy (ROLE_ADMIN → ROLE_EDITOR, ROLE_SENDER), sodass Administratoren automatisch Editor-/Sender-Rechte erhalten.

curl -X POST https://besteller.example.com/api/send-link \
     -H 'Content-Type: application/json' \
     -d '{
           "checklist_id": 123,
           "recipient_name": "Teamleiter",
           "recipient_email": "manager@example.com",
           "mitarbeiter_id": "abc-123",
           "person_name": "Max Muster",
           "intro": "Bitte füllen Sie die Liste zeitnah aus."
         }'
```

Die Antwort bestätigt den Versand und enthält den generierten Link:

```json
{
  "status": "sent",
  "link": "https://besteller.example.com/form?checklist_id=123&name=Max%20Muster&id=abc-123&email=manager@example.com"
}
```

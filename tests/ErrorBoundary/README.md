# Error Boundary Tests - Verhalten bei System-Fehlern

Dieses Verzeichnis enthält Tests für das Verhalten der Anwendung bei System-Fehlern und kritischen Ausnahmesituationen.

## Überblick

Die Error Boundary Tests prüfen, wie die Besteller-Anwendung mit verschiedenen System-Fehlern umgeht:

- **EmailService System-Fehler**: SMTP-Ausfälle, Netzwerkprobleme, Authentifizierungsfehler
- **LinkSender Service-Fehler**: Datenbankausfälle, Deadlocks, URL-Generator-Probleme
- **API Controller System-Fehler**: Kritische Fehler in API-Endpunkten
- **Dateisystem-Fehler**: Upload-Probleme, Speicherplatz-Erschöpfung, Berechtigungsfehler
- **Input-Validierung Grenzfälle**: Extreme Payloads, Memory-Exhaustion, Unicode-Probleme
- **Datenbank System-Fehler**: Verbindungsausfälle, Deadlocks, Speicher-Probleme

## Test-Klassen

### EmailServiceSystemErrorTest
**Datei**: `EmailServiceSystemErrorTest.php`
**Zweck**: Testen des E-Mail-Versands bei System-Fehlern

**Testfälle**:
- SMTP-Server nicht erreichbar
- Netzwerk-Timeouts
- Authentifizierung fehlgeschlagen
- Memory-Exhaustion bei großen E-Mails
- Datenbankverbindung verloren

### LinkSenderServiceSystemErrorTest
**Datei**: `LinkSenderServiceSystemErrorTest.php`
**Zweck**: Testen des Link-Versands bei System-Problemen

**Testfälle**:
- Datenbankverbindung fehlgeschlagen
- Datenbank-Deadlocks
- Lock-Wait-Timeouts
- URL-Generator-Fehler
- E-Mail-Service-Ausfälle
- Employee-Validator-Ausfälle
- Memory-Exhaustion

### ApiControllerSystemErrorTest
**Datei**: `ApiControllerSystemErrorTest.php`
**Zweck**: Testen der API-Endpunkte bei kritischen System-Fehlern

**Testfälle**:
- Datenbankverbindung fehlgeschlagen
- E-Mail-Service-Ausfälle
- Memory-Exhaustion
- Korrupte Request-Daten
- Netzwerk-Partitionierung
- Festplatte voll

### FileSystemErrorTest
**Datei**: `FileSystemErrorTest.php`
**Zweck**: Testen des Datei-Uploads bei Dateisystem-Problemen

**Testfälle**:
- Festplatte voll
- Datei zu groß
- Ungültiger MIME-Type
- Bösartige Inhalte (XSS-Schutz)
- Berechtigungsfehler
- Korrupte Dateien

### InputValidationSystemErrorTest
**Datei**: `InputValidationSystemErrorTest.php`
**Zweck**: Testen der Input-Validierung bei extremen Daten

**Testfälle**:
- Extrem große JSON-Payloads
- Tief verschachtelte Strukturen
- Ungültige UTF-8-Sequenzen
- Null-Byte-Injection
- Sehr lange Feldnamen
- Floating-Point-Grenzfälle
- Arrays mit vielen Elementen

### DatabaseSystemErrorTest
**Datei**: `DatabaseSystemErrorTest.php`
**Zweck**: Testen der Datenbank-Operationen bei System-Fehlern

**Testfälle**:
- Verbindungsausfälle
- Deadlocks
- Lock-Timeouts
- Tabellen nicht gefunden
- Query-Timeouts
- Festplatte voll
- Maximale Verbindungen erreicht
- Korrupte Indizes
- Server-Neustarts
- Transaktions-Rollbacks
- Memory-Exhaustion bei großen Ergebnismengen
- Read-Only-Modus
- Constraint-Verletzungen
- Character-Set-Fehler
- Langsame Abfragen getötet

## Testphilosophie

### 1. **Fehler-Typen**

Die Tests unterscheiden zwischen verschiedenen Arten von System-Fehlern:

- **Erwartete Fehler**: Werden ordentlich behandelt und führen zu benutzerfreundlichen Fehlermeldungen
- **System-Fehler**: Schwerwiegende Probleme, die nicht gefangen werden können (Memory-Exhaustion, Network-Partitions)
- **Sicherheitsfehler**: Versuche, die Anwendung zu kompromittieren

### 2. **Testansatz**

- **Simulation**: System-Fehler werden durch Mocks simuliert
- **Isolation**: Jeder Test prüft einen spezifischen Fehlerfall
- **Realität**: Tests basieren auf echten Fehlern, die in Produktionsumgebungen auftreten können
- **Dokumentation**: Jeder Test dokumentiert das erwartete Verhalten

### 3. **Erwartetes Verhalten**

**Bei behandelbaren Fehlern**:
- Benutzerfreundliche Fehlermeldungen
- Graceful Degradation
- Logging für Administratoren
- Redirect zu sicheren Zuständen

**Bei System-Fehlern**:
- Exception wird weitergegeben
- System bleibt in konsistentem Zustand
- Keine Datenverluste
- Ordentliches Fehler-Logging

## Ausführung der Tests

### Alle Error Boundary Tests ausführen
```bash
make test tests/ErrorBoundary/
```

### Einzelne Test-Klassen ausführen
```bash
# E-Mail-Service System-Fehler
make test tests/ErrorBoundary/EmailServiceSystemErrorTest.php

# Datenbank System-Fehler  
make test tests/ErrorBoundary/DatabaseSystemErrorTest.php

# API Controller System-Fehler
make test tests/ErrorBoundary/ApiControllerSystemErrorTest.php

# Dateisystem-Fehler
make test tests/ErrorBoundary/FileSystemErrorTest.php

# Input-Validierung Grenzfälle
make test tests/ErrorBoundary/InputValidationSystemErrorTest.php

# Link-Sender Service-Fehler
make test tests/ErrorBoundary/LinkSenderServiceSystemErrorTest.php
```

### Mit Coverage
```bash
make coverage
```

## Monitoring und Logging

### In Produktionsumgebung überwachen

Diese Tests helfen dabei, Monitoring für echte System-Fehler einzurichten:

1. **E-Mail-Versand-Probleme**: SMTP-Fehler, Authentifizierung
2. **Datenbank-Probleme**: Deadlocks, Timeouts, Verbindungsausfälle  
3. **Dateisystem-Probleme**: Speicherplatz, Berechtigungen
4. **Memory-Probleme**: Große Payloads, Speicher-Erschöpfung
5. **Netzwerk-Probleme**: Timeouts, Partitionierung

### Logging-Empfehlungen

```php
// Beispiel für System-Fehler-Logging
try {
    $emailService->generateAndSendEmail($submission);
} catch (TransportException $e) {
    $logger->critical('E-Mail-Versand fehlgeschlagen', [
        'error' => $e->getMessage(),
        'submission_id' => $submission->getId(),
        'recipient' => $submission->getEmail()
    ]);
    throw $e; // System-Fehler weiterwerfen
}
```

## Wartung

### Neue System-Fehler hinzufügen

1. **Analysieren**: Welche neuen System-Fehler können auftreten?
2. **Kategorisieren**: Behandelbar vs. System-Fehler vs. Sicherheitsproblem?
3. **Testen**: Neuen Test in passende Klasse hinzufügen
4. **Dokumentieren**: README und Code-Kommentare aktualisieren

### Tests aktualisieren

- **Regelmäßig überprüfen**: Sind alle Fehlerszenarien noch relevant?
- **Neue Abhängigkeiten**: Können neue Libraries neue Fehlertypen erzeugen?
- **Produktions-Feedback**: Aufgetretene Fehler in Tests aufnehmen

## Hinweise für Entwickler

### Behandelbare vs. System-Fehler

**Behandelbare Fehler** (sollten gefangen werden):
- Ungültige Benutzereingaben
- Erwartete Validierungsfehler
- Konfigurationsprobleme

**System-Fehler** (sollten weitergegeben werden):
- Memory-Exhaustion
- Datenbank-Server nicht erreichbar
- Festplatte voll
- Netzwerk-Partitionierung

### Best Practices

1. **Keine System-Fehler abfangen**: Lassen Sie schwerwiegende Fehler durchlaufen
2. **Benutzerfreundliche Behandlung**: Nur bei erwartbaren Problemen
3. **Logging**: Alle System-Fehler für Monitoring protokollieren
4. **Graceful Degradation**: Teilfunktionalität beibehalten wenn möglich
5. **Konsistenz**: System in konsistentem Zustand halten

## Testdaten-Sicherheit

Alle Tests verwenden:
- **Mock-Objekte**: Keine echten System-Ressourcen
- **Temporäre Dateien**: Werden automatisch aufgeräumt  
- **Sichere Test-Daten**: Keine produktionsähnlichen Daten
- **Isolation**: Tests beeinflussen sich nicht gegenseitig
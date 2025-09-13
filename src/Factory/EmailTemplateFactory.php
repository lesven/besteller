<?php

namespace App\Factory;

/**
 * Factory für die Erstellung von Standard-E-Mail-Templates.
 * 
 * Extrahiert die Template-Erstellung aus LoadFixturesCommand, 
 * um Wiederverwendung und einfache Anpassung zu ermöglichen.
 */
class EmailTemplateFactory
{
    /**
     * Erstellt das Standard-E-Mail-Template für Bestellbestätigungen.
     */
    public function getDefaultEmailTemplate(): string
    {
        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Neue Ausstattungsbestellung</title>
</head>
<body>
    <h2>Neue Ausstattungsbestellung</h2>
    
    <p><strong>Mitarbeiter:</strong> {{name}}</p>
    <p><strong>Mitarbeiter-ID:</strong> {{mitarbeiter_id}}</p>
    <p><strong>Stückliste:</strong> {{stückliste}}</p>
    
    <h3>Bestellte Ausstattung:</h3>
    {{auswahl}}
    
    <hr>
    <p>Bei Rückfragen wenden Sie sich an: {{rueckfragen_email}}</p>
</body>
</html>
HTML;
    }

    /**
     * Erstellt das Standard-E-Mail-Template für Link-Versendung.
     */
    public function getDefaultLinkEmailTemplate(): string
    {
        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Ausstattungsbestellung ausfüllen</title>
</head>
<body>
    <h2>Ausstattungsbestellung für neuen Mitarbeiter</h2>
    
    <p>Hallo {{recipient_name}},</p>
    
    <p>bitte füllen Sie die Ausstattungsliste für {{person_name}} aus:</p>
    
    <p><a href="{{link}}" style="background-color: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">Zur Ausstattungsliste</a></p>
    
    <p>{{intro}}</p>
    
    <p>Vielen Dank!</p>
</body>
</html>
HTML;
    }

    /**
     * Erstellt das Standard-E-Mail-Template für Bestätigungsmails.
     */
    public function getDefaultConfirmationEmailTemplate(): string
    {
        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Bestätigung Ihrer Ausstattungsbestellung</title>
</head>
<body>
    <h2>Bestätigung Ihrer Ausstattungsbestellung</h2>
    
    <p>Vielen Dank für das Ausfüllen der Ausstattungsliste!</p>
    
    <p><strong>Mitarbeiter:</strong> {{name}}</p>
    <p><strong>Mitarbeiter-ID:</strong> {{mitarbeiter_id}}</p>
    <p><strong>Stückliste:</strong> {{stückliste}}</p>
    
    <h3>Ihre Auswahl:</h3>
    {{auswahl}}
    
    <p>Die Bestellung wurde weitergeleitet und wird bearbeitet.</p>
    
    <hr>
    <p>Bei Rückfragen wenden Sie sich an: {{rueckfragen_email}}</p>
</body>
</html>
HTML;
    }
}
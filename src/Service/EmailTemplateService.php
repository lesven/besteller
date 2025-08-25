<?php

namespace App\Service;

use App\Entity\Submission;
use App\Entity\Checklist;

class EmailTemplateService
{
    public function __construct(
        private SubmissionService $submissionService
    ) {}

    /**
     * Renders email content from template and submission data
     */
    public function renderEmailTemplate(string $template, Submission $submission): string
    {
        return $this->replacePlaceholders($template, $submission);
    }

    /**
     * Gets the default email template for submissions
     */
    public function getDefaultSubmissionTemplate(): string
    {
        return sprintf(<<<'HTML'
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stückliste {{stückliste}}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .content {
            background-color: #ffffff;
            padding: 20px;
            border: 1px solid #dee2e6;
            border-radius: 5px;
        }
        h1 {
            color: #007bff;
            margin-top: 0;
        }
        h2 {
            color: #6c757d;
            border-bottom: 2px solid #007bff;
            padding-bottom: 5px;
        }
        h3 {
            color: #495057;
            margin-top: 25px;
            margin-bottom: 10px;
        }
        ul {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
        }
        li {
            margin-bottom: 8px;
        }
        .footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #dee2e6;
            color: #6c757d;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Neue Stückliste eingegangen</h1>
        <p><strong>Stückliste:</strong> {{stückliste}}</p>
    </div>

    <div class="content">
        <h2>Mitarbeiterinformationen</h2>
        <ul>
            <li><strong>Name:</strong> {{name}}</li>
            <li><strong>Mitarbeiter-ID:</strong> {{mitarbeiter_id}}</li>
        </ul>

        <h2>Ausgewählte Ausstattung</h2>
        {{auswahl}}
        <p>Bei Rückfragen zu dieser Bestellung wende dich an {{rueckfragen_email}}.</p>
    </div>

    <div class="footer">
        <p>Diese E-Mail wurde automatisch generiert vom Besteller-System.</p>
        <p>Eingereicht am: %s Uhr</p>
    </div>
</body>
</html>
HTML
, date('d.m.Y H:i'));
    }

    /**
     * Gets the default confirmation email template
     */
    public function getDefaultConfirmationTemplate(): string
    {
        return sprintf(<<<'HTML'
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bestätigung Stückliste {{stückliste}}</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 800px; margin: 0 auto; padding: 20px; }
        .header { background-color: #d4edda; padding: 20px; border-radius: 5px; margin-bottom: 20px; border: 1px solid #c3e6cb; }
        .content { background-color: #ffffff; padding: 20px; border: 1px solid #dee2e6; border-radius: 5px; }
        h1 { color: #155724; margin-top: 0; }
        h2 { color: #6c757d; border-bottom: 2px solid #28a745; padding-bottom: 5px; }
        h3 { color: #495057; margin-top: 25px; margin-bottom: 10px; }
        ul { background-color: #f8f9fa; padding: 15px; border-radius: 5px; }
        li { margin-bottom: 8px; }
        .success-message { background-color: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 15px; border-radius: 5px; margin-bottom: 20px; }
        .footer { margin-top: 30px; padding-top: 20px; border-top: 1px solid #dee2e6; color: #6c757d; font-size: 12px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>✓ Stückliste erfolgreich übermittelt</h1>
        <p>Vielen Dank für deine Eingabe für Mitarbeiter <strong>{{name}}</strong>!</p>
    </div>

    <div class="content">
        <div class="success-message">
            <strong>Die Bestellung wurde erfolgreich übermittelt.</strong><br>
            Die Bearbeitung erfolgt zeitnah durch unser Team.
        </div>

        <h2>Deine Angaben im Überblick</h2>
        <ul>
            <li><strong>Stückliste:</strong> {{stückliste}}</li>
            <li><strong>Mitarbeiter-ID:</strong> {{mitarbeiter_id}}</li>
        </ul>

        <h2>Deine Auswahl</h2>
        {{auswahl}}

        <p><strong>Nächste Schritte:</strong><br>
        Deine Angaben werden nun bearbeitet und die entsprechende Ausstattung wird vorbereitet.
        Bei Rückfragen wende dich an {{rueckfragen_email}}.</p>
    </div>

    <div class="footer">
        <p>Diese Bestätigungs-E-Mail wurde automatisch generiert.</p>
        <p>Übermittelt am: %s Uhr</p>
    </div>
</body>
</html>
HTML
, date('d.m.Y H:i'));
    }

    /**
     * Gets the default link email template
     */
    public function getDefaultLinkTemplate(): string
    {
        return <<<'HTML'
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stückliste {{stückliste}}</title>
</head>
<body>
    <p>Hallo {{empfaenger_name}},</p>
    <p>{{intro}}</p>
    <p>Bitte fülle die Stückliste <strong>{{stückliste}}</strong> für {{person_name}} (ID {{mitarbeiter_id}}) unter folgendem Link aus:</p>
    <p><a href="{{link}}">{{link}}</a></p>
    <p>Vielen Dank!</p>
</body>
</html>
HTML;
    }

    /**
     * Replaces placeholders in template with submission data
     */
    private function replacePlaceholders(string $template, Submission $submission): string
    {
        $auswahl = $this->submissionService->formatSubmissionForEmail($submission->getData());

        $placeholders = [
            '{{name}}' => $submission->getName(),
            '{{mitarbeiter_id}}' => $submission->getMitarbeiterId(),
            '{{stückliste}}' => $submission->getChecklist()?->getTitle() ?? '',
            '{{auswahl}}' => $auswahl,
            '{{rueckfragen_email}}' => $submission->getChecklist()?->getReplyEmail() ?? ''
        ];
        
        return str_replace(array_keys($placeholders), array_values($placeholders), $template);
    }

    /**
     * Renders link email template with placeholders
     */
    public function renderLinkTemplate(
        string $template,
        Checklist $checklist,
        string $recipientName,
        string $mitarbeiterId,
        ?string $personName,
        string $intro,
        string $link
    ): string {
        $placeholders = [
            '{{empfaenger_name}}' => $recipientName,
            '{{person_name}}' => $personName ?? '',
            '{{mitarbeiter_id}}' => $mitarbeiterId,
            '{{intro}}' => nl2br($intro),
            '{{link}}' => $link,
            '{{stückliste}}' => $checklist->getTitle(),
        ];

        return str_replace(array_keys($placeholders), array_values($placeholders), $template);
    }
}
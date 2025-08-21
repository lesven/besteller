<?php

namespace App\Service;

use App\Entity\Submission;
use App\Entity\Checklist;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\EmailSettings;
use Symfony\Component\Mime\Email;

class EmailService
{
    /**
     * @param MailerInterface    $mailer            Mailer zum Versenden der E-Mails
     * @param SubmissionService  $submissionService Service zum Aufbereiten der Bestelldaten
     * @param EntityManagerInterface $entityManager Datenbank-Zugriff für Einstellungen
     */
    public function __construct(
        private MailerInterface $mailer,
        private SubmissionService $submissionService,
        private EntityManagerInterface $entityManager
    ) {}

    private function getEmailSettings(): ?EmailSettings
    {
        return $this->entityManager->getRepository(EmailSettings::class)->find(1);
    }

    /**
     * Erstellt bei Bedarf einen Mailer mit benutzerdefinierten Einstellungen.
     *
     * @return MailerInterface Konfigurierte Mailer-Instanz
     */
    private function getMailer(): MailerInterface
    {
        $settings = $this->getEmailSettings();
        if (!$settings) {
            return $this->mailer;
        }

        $dsn = 'smtp://';
        if ($settings->getUsername()) {
            $dsn .= rawurlencode($settings->getUsername());
            if ($settings->getPassword()) {
                $dsn .= ':' . rawurlencode($settings->getPassword());
            }
            $dsn .= '@';
        }
        $dsn .= $settings->getHost() . ':' . $settings->getPort();
        if ($settings->isIgnoreSsl()) {
            $dsn .= '?verify_peer=0';
        }

        return new Mailer(Transport::fromDsn($dsn));
    }

    private function sendEmail(
        MailerInterface $mailer,
        string $from,
        string $to,
        string $subject,
        string $content
    ): void {
        $email = (new Email())
            ->from($from)
            ->to($to)
            ->subject($subject)
            ->html($content);

        $mailer->send($email);
    }

    /**
     * Erstellt die E-Mail-Inhalte und versendet sie an Zieladresse und Führungskraft.
     *
     * @param Submission $submission Die eingereichte Bestellung
     *
     * @return string Der an die Zieladresse gesendete HTML-Inhalt
     */
    public function generateAndSendEmail(Submission $submission): string
    {
        $template = $submission->getChecklist()?->getEmailTemplate() ?? $this->getDefaultTemplate();
        $emailContent = $this->replacePlaceholders($template, $submission);

        $settings = $this->getEmailSettings();
        $from = $settings?->getSenderEmail() ?? 'noreply@besteller.local';
        $mailer = $this->getMailer();

        $targetEmailAddress = $submission->getChecklist()?->getTargetEmail() ?? '';
        $targetSubject = sprintf(
            'Neue Stückliste eingegangen: %s - %s',
            $submission->getChecklist()?->getTitle() ?? '',
            $submission->getName()
        );

        $this->sendEmail($mailer, $from, $targetEmailAddress, $targetSubject, $emailContent);

        $confirmationTemplate = $submission->getChecklist()?->getConfirmationEmailTemplate() ?? $this->getConfirmationTemplate();
        $confirmationContent = $this->replacePlaceholders($confirmationTemplate, $submission);

        $this->sendEmail(
            $mailer,
            $from,
            $submission->getEmail() ?? '',
            'Bestätigung: Die Bestellung wurde erfolgreich übermittelt',
            $confirmationContent
        );

        return $emailContent;
    }
    
    /**
     * Liefert das Standardtemplate für die Anzeige im Administrationsbereich.
     */
    public function getDefaultTemplateForAdmin(): string
    {
        return $this->getDefaultTemplate();
    }
    
    /**
     * Ersetzt Platzhalter im Template durch Werte der Übermittlung.
     *
     * @param string     $template   HTML-Template mit Platzhaltern
     * @param Submission $submission Die zugehörige Übermittlung
     *
     * @return string Fertiges HTML
     */
    private function replacePlaceholders(string $template, Submission $submission): string
    {
        $auswahl = $this->submissionService->formatSubmissionForEmail($submission->getData());

        /** @var array<string, string> $placeholders */
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
     * Liefert das Standardtemplate für die E-Mail an die Zieladresse.
     */
    public function getDefaultTemplate(): string
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
     * Liefert das Standardtemplate für die Bestätigungs-E-Mail an die Führungskraft.
     */
    public function getConfirmationTemplate(): string
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
     * Versendet eine E-Mail mit einem personalisierten Link zur Stückliste.
     *
     * @param Checklist   $checklist      Die Ziel-Stückliste
     * @param string      $recipientName  Name des Empfängers
     * @param string      $recipientEmail E-Mail-Adresse des Empfängers
     * @param string      $mitarbeiterId  ID der betroffenen Person
     * @param string|null $personName     Name der betroffenen Person
     * @param string      $intro          Einführungstext der Nachricht
     * @param string      $link           Vollständiger Link zum Formular
     */
    public function sendLinkEmail(
        Checklist $checklist,
        string $recipientName,
        string $recipientEmail,
        string $mitarbeiterId,
        ?string $personName,
        string $intro,
        string $link
    ): void {
        $template = $checklist->getLinkEmailTemplate() ?? $this->getDefaultLinkTemplate();

        /** @var array<string, string> $placeholders */
        $placeholders = [
            '{{empfaenger_name}}' => $recipientName,
            '{{person_name}}' => $personName ?? '',
            '{{mitarbeiter_id}}' => $mitarbeiterId,
            '{{intro}}' => nl2br($intro),
            '{{link}}' => $link,
            '{{stückliste}}' => $checklist->getTitle(),
        ];

        $content = str_replace(array_keys($placeholders), array_values($placeholders), $template);

        $settings = $this->getEmailSettings();
        $from = $settings?->getSenderEmail() ?? 'noreply@besteller.local';

        $this->sendEmail(
            $this->getMailer(),
            $from,
            $recipientEmail,
            'Link zur Bestelliste ' . $checklist->getTitle(),
            $content
        );
    }

    /**
     * Liefert das Standardtemplate für Link-E-Mails.
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
}

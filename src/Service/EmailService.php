<?php

namespace App\Service;

use App\Entity\Submission;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

class EmailService
{
    public function __construct(
        private MailerInterface $mailer,
        private SubmissionService $submissionService
    ) {}

    public function generateAndSendEmail(Submission $submission): string
    {
        $template = $submission->getChecklist()->getEmailTemplate() ?? $this->getDefaultTemplate();
        
        // Platzhalter ersetzen
        $emailContent = $this->replacePlaceholders($template, $submission);
        
        // E-Mail an Zieladresse (interne Bearbeitung)
        $targetEmail = (new Email())
            ->from('noreply@besteller.local')
            ->to($submission->getChecklist()->getTargetEmail())
            ->subject('Neue Stückliste eingegangen: ' . $submission->getChecklist()->getTitle() . ' - ' . $submission->getName())
            ->html($emailContent);
            
        $this->mailer->send($targetEmail);
        
        // E-Mail an Führungskraft (Bestätigung)
        $confirmationTemplate = $this->getConfirmationTemplate();
        $confirmationContent = $this->replacePlaceholders($confirmationTemplate, $submission);
        
        $managerEmail = (new Email())
            ->from('noreply@besteller.local')
            ->to($submission->getEmail())
            ->subject('Bestätigung: Ihre Stückliste wurde erfolgreich übermittelt')
            ->html($confirmationContent);
            
        $this->mailer->send($managerEmail);
        
        return $emailContent;
    }
    
    private function replacePlaceholders(string $template, Submission $submission): string
    {
        $auswahl = $this->submissionService->formatSubmissionForEmail($submission->getData());
        
        $placeholders = [
            '{{name}}' => $submission->getName(),
            '{{mitarbeiter_id}}' => $submission->getMitarbeiterId(),
            '{{stückliste}}' => $submission->getChecklist()->getTitle(),
            '{{auswahl}}' => $auswahl
        ];
        
        return str_replace(array_keys($placeholders), array_values($placeholders), $template);
    }
    
    private function getDefaultTemplate(): string
    {
        return '
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stückliste {{stückliste}}</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 800px; margin: 0 auto; padding: 20px; }
        .header { background-color: #f8f9fa; padding: 20px; border-radius: 5px; margin-bottom: 20px; }
        .content { background-color: #ffffff; padding: 20px; border: 1px solid #dee2e6; border-radius: 5px; }
        h1 { color: #007bff; margin-top: 0; }
        h2 { color: #6c757d; border-bottom: 2px solid #007bff; padding-bottom: 5px; }
        h3 { color: #495057; margin-top: 25px; margin-bottom: 10px; }
        ul { background-color: #f8f9fa; padding: 15px; border-radius: 5px; }
        li { margin-bottom: 8px; }
        .footer { margin-top: 30px; padding-top: 20px; border-top: 1px solid #dee2e6; color: #6c757d; font-size: 12px; }
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
    </div>
    
    <div class="footer">
        <p>Diese E-Mail wurde automatisch generiert vom Besteller-System.</p>
        <p>Eingereicht am: ' . date('d.m.Y H:i') . ' Uhr</p>
    </div>
</body>
</html>';
    }
    
    private function getConfirmationTemplate(): string
    {
        return '
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
        <p>Vielen Dank für Ihre Eingabe, <strong>{{name}}</strong>!</p>
    </div>
    
    <div class="content">
        <div class="success-message">
            <strong>Ihre Stückliste wurde erfolgreich übermittelt.</strong><br>
            Die Bearbeitung erfolgt zeitnah durch unser Team.
        </div>
        
        <h2>Ihre Angaben im Überblick</h2>
        <ul>
            <li><strong>Stückliste:</strong> {{stückliste}}</li>
            <li><strong>Mitarbeiter-ID:</strong> {{mitarbeiter_id}}</li>
        </ul>
        
        <h2>Ihre Auswahl</h2>
        {{auswahl}}
        
        <p><strong>Nächste Schritte:</strong><br>
        Ihre Angaben werden nun bearbeitet und die entsprechende Ausstattung wird vorbereitet. 
        Bei Rückfragen werden wir uns bei Ihnen melden.</p>
    </div>
    
    <div class="footer">
        <p>Diese Bestätigungs-E-Mail wurde automatisch generiert.</p>
        <p>Übermittelt am: ' . date('d.m.Y H:i') . ' Uhr</p>
    </div>
</body>
</html>';
    }
}

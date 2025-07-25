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
        
        // E-Mail an Zieladresse
        $targetEmail = (new Email())
            ->from('noreply@besteller.local')
            ->to($submission->getChecklist()->getTargetEmail())
            ->subject('Neue Stückliste eingegangen: ' . $submission->getChecklist()->getTitle())
            ->html($emailContent);
            
        $this->mailer->send($targetEmail);
        
        // E-Mail an Führungskraft
        $managerEmail = (new Email())
            ->from('noreply@besteller.local')
            ->to($submission->getEmail())
            ->subject('Bestätigung Ihrer Stückliste: ' . $submission->getChecklist()->getTitle())
            ->html($emailContent);
            
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
<html>
<head>
    <meta charset="UTF-8">
    <title>Stückliste {{stückliste}}</title>
</head>
<body>
    <h1>Stückliste eingegangen</h1>
    <p><strong>Name:</strong> {{name}}</p>
    <p><strong>Mitarbeiter-ID:</strong> {{mitarbeiter_id}}</p>
    <p><strong>Stückliste:</strong> {{stückliste}}</p>
    
    <h2>Auswahl:</h2>
    {{auswahl}}
</body>
</html>';
    }
}

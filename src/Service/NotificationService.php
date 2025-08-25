<?php

namespace App\Service;

use App\Entity\Checklist;
use App\Entity\Submission;
use App\Exception\EmailDeliveryException;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

class NotificationService
{
    public function __construct(
        private MailerConfigService $mailerConfigService,
        private EmailTemplateService $templateService
    ) {}

    /**
     * Sends submission notification emails to target and submitter
     */
    public function sendSubmissionNotifications(Submission $submission): string
    {
        $checklist = $submission->getChecklist();
        if (!$checklist) {
            throw new \InvalidArgumentException('Submission must have a checklist');
        }

        $mailer = $this->mailerConfigService->getConfiguredMailer();
        $senderEmail = $this->mailerConfigService->getSenderEmail();

        // Send to target email
        $targetTemplate = $checklist->getEmailTemplate() ?? $this->templateService->getDefaultSubmissionTemplate();
        $targetContent = $this->templateService->renderEmailTemplate($targetTemplate, $submission);
        
        $targetSubject = sprintf(
            'Neue Stückliste eingegangen: %s - %s',
            $checklist->getTitle(),
            $submission->getName()
        );

        $this->sendEmail(
            $mailer,
            $senderEmail,
            $checklist->getTargetEmail(),
            $targetSubject,
            $targetContent
        );

        // Send confirmation to submitter
        $confirmationTemplate = $checklist->getConfirmationEmailTemplate() ?? $this->templateService->getDefaultConfirmationTemplate();
        $confirmationContent = $this->templateService->renderEmailTemplate($confirmationTemplate, $submission);

        $this->sendEmail(
            $mailer,
            $senderEmail,
            $submission->getEmail(),
            'Bestätigung: Die Bestellung wurde erfolgreich übermittelt',
            $confirmationContent
        );

        return $targetContent;
    }

    /**
     * Sends link email to recipient
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
        $template = $checklist->getLinkEmailTemplate() ?? $this->templateService->getDefaultLinkTemplate();
        $content = $this->templateService->renderLinkTemplate(
            $template,
            $checklist,
            $recipientName,
            $mitarbeiterId,
            $personName,
            $intro,
            $link
        );

        $mailer = $this->mailerConfigService->getConfiguredMailer();
        $senderEmail = $this->mailerConfigService->getSenderEmail();

        $this->sendEmail(
            $mailer,
            $senderEmail,
            $recipientEmail,
            'Link zur Bestelliste ' . $checklist->getTitle(),
            $content
        );
    }

    /**
     * Sends a single email with error handling
     */
    private function sendEmail(
        MailerInterface $mailer,
        string $from,
        string $to,
        string $subject,
        string $content
    ): void {
        try {
            $email = (new Email())
                ->from($from)
                ->to($to)
                ->subject($subject)
                ->html($content);

            $mailer->send($email);
        } catch (TransportExceptionInterface $e) {
            throw new EmailDeliveryException($to, $e->getMessage(), $e);
        }
    }
}
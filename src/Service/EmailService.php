<?php

namespace App\Service;

use App\Entity\Submission;
use App\Entity\Checklist;

/**
 * EmailService - Facade for email-related operations
 * 
 * This service acts as a facade to maintain backward compatibility
 * while delegating actual work to specialized services.
 */
class EmailService
{
    public function __construct(
        private NotificationService $notificationService,
        private EmailTemplateService $templateService
    ) {}

    /**
     * Generates and sends email notifications for a submission
     * 
     * @param Submission $submission The submitted checklist
     * @return string The HTML content sent to the target email
     */
    public function generateAndSendEmail(Submission $submission): string
    {
        return $this->notificationService->sendSubmissionNotifications($submission);
    }

    /**
     * Sends a personalized link email to a recipient
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
        $this->notificationService->sendLinkEmail(
            $checklist,
            $recipientName,
            $recipientEmail,
            $mitarbeiterId,
            $personName,
            $intro,
            $link
        );
    }

    /**
     * Gets the default template for admin display
     */
    public function getDefaultTemplateForAdmin(): string
    {
        return $this->templateService->getDefaultSubmissionTemplate();
    }

    /**
     * Gets the default submission template
     */
    public function getDefaultTemplate(): string
    {
        return $this->templateService->getDefaultSubmissionTemplate();
    }

    /**
     * Gets the default confirmation template
     */
    public function getConfirmationTemplate(): string
    {
        return $this->templateService->getDefaultConfirmationTemplate();
    }

    /**
     * Gets the default link template
     */
    public function getDefaultLinkTemplate(): string
    {
        return $this->templateService->getDefaultLinkTemplate();
    }
}
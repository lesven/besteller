<?php

namespace App\Tests\ErrorBoundary;

use App\Entity\Checklist;
use App\Entity\Submission;
use App\Exception\EmailDeliveryException;
use App\Service\EmailService;
use App\Service\NotificationService;
use App\Service\EmailTemplateService;
use PHPUnit\Framework\TestCase;

/**
 * Tests für EmailService System-Fehlerfälle.
 * Verhalten bei SMTP-Ausfällen, Netzwerkproblemen und anderen System-Fehlern.
 */
class EmailServiceSystemErrorTest extends TestCase
{
    public function testGenerateAndSendEmailHandlesSmtpConnectionFailure(): void
    {
        // SMTP-Server nicht erreichbar simulieren
        $notificationService = $this->createMock(NotificationService::class);
        $notificationService->expects($this->once())
            ->method('sendSubmissionNotifications')
            ->willThrowException(new EmailDeliveryException('target@test.com', 'SMTP connection failed'));

        $templateService = $this->createMock(EmailTemplateService::class);
        $service = new EmailService($notificationService, $templateService);

        $checklist = (new Checklist())
            ->setTitle('Test Checklist')
            ->setTargetEmail('target@test.com')
            ->setReplyEmail('reply@test.com');

        $submission = (new Submission())
            ->setChecklist($checklist)
            ->setName('Max Mustermann')
            ->setMitarbeiterId('EMP123')
            ->setEmail('max@test.com')
            ->setData(['item1' => 'selected']);

        $this->expectException(EmailDeliveryException::class);
        $this->expectExceptionMessage('Failed to send email to target@test.com: SMTP connection failed');

        $service->generateAndSendEmail($submission);
    }

    public function testGenerateAndSendEmailHandlesNetworkTimeout(): void
    {
        $notificationService = $this->createMock(NotificationService::class);
        $notificationService->expects($this->once())
            ->method('sendSubmissionNotifications')
            ->willThrowException(new EmailDeliveryException('target@test.com', 'Network timeout'));

        $templateService = $this->createMock(EmailTemplateService::class);
        $service = new EmailService($notificationService, $templateService);

        $checklist = (new Checklist())
            ->setTitle('Performance Checklist')
            ->setTargetEmail('target@test.com');

        $submission = (new Submission())
            ->setChecklist($checklist)
            ->setName('Anna Schmidt')
            ->setMitarbeiterId('EMP456')
            ->setEmail('anna@test.com')
            ->setData(['performance' => 'excellent']);

        $this->expectException(EmailDeliveryException::class);
        $this->expectExceptionMessage('Failed to send email to target@test.com: Network timeout');

        $service->generateAndSendEmail($submission);
    }

    public function testGenerateAndSendEmailHandlesAuthenticationFailure(): void
    {
        $notificationService = $this->createMock(NotificationService::class);
        $notificationService->expects($this->once())
            ->method('sendSubmissionNotifications')
            ->willThrowException(new EmailDeliveryException('it@company.com', 'SMTP authentication failed'));

        $templateService = $this->createMock(EmailTemplateService::class);
        $service = new EmailService($notificationService, $templateService);

        $checklist = (new Checklist())
            ->setTitle('IT Equipment Request')
            ->setTargetEmail('it@company.com');

        $submission = (new Submission())
            ->setChecklist($checklist)
            ->setName('Peter Mueller')
            ->setMitarbeiterId('EMP789')
            ->setEmail('peter@company.com')
            ->setData(['equipment' => 'laptop']);

        $this->expectException(EmailDeliveryException::class);
        $this->expectExceptionMessage('Failed to send email to it@company.com: SMTP authentication failed');

        $service->generateAndSendEmail($submission);
    }

    public function testSendLinkEmailHandlesMailerFailure(): void
    {
        $notificationService = $this->createMock(NotificationService::class);
        $notificationService->expects($this->once())
            ->method('sendLinkEmail')
            ->willThrowException(new EmailDeliveryException('test@example.com', 'Mail server temporarily unavailable'));

        $templateService = $this->createMock(EmailTemplateService::class);
        $service = new EmailService($notificationService, $templateService);

        $checklist = (new Checklist())->setTitle('Employee Onboarding');

        $this->expectException(EmailDeliveryException::class);
        $this->expectExceptionMessage('Failed to send email to test@example.com: Mail server temporarily unavailable');

        $service->sendLinkEmail(
            $checklist,
            'HR Manager',
            'test@example.com',
            'NEW001',
            'New Employee',
            'Please complete the onboarding checklist.',
            'https://company.com/onboarding/NEW001'
        );
    }

    public function testGenerateAndSendEmailHandlesMemoryExhaustion(): void
    {
        $notificationService = $this->createMock(NotificationService::class);
        $notificationService->expects($this->once())
            ->method('sendSubmissionNotifications')
            ->willThrowException(new EmailDeliveryException('bulk@test.com', 'memory size exhausted'));

        $templateService = $this->createMock(EmailTemplateService::class);
        $service = new EmailService($notificationService, $templateService);

        $checklist = (new Checklist())
            ->setTitle('Bulk Processing Test')
            ->setTargetEmail('bulk@test.com');

        $submission = (new Submission())
            ->setChecklist($checklist)
            ->setName('Bulk Test User')
            ->setMitarbeiterId('BULK001')
            ->setEmail('bulkuser@test.com')
            ->setData(['data' => 'large dataset']);

        $this->expectException(EmailDeliveryException::class);
        $this->expectExceptionMessage('Failed to send email to bulk@test.com: memory size exhausted');

        $service->generateAndSendEmail($submission);
    }

    public function testGenerateAndSendEmailHandlesDatabaseConnectionLoss(): void
    {
        $notificationService = $this->createMock(NotificationService::class);
        $notificationService->expects($this->once())
            ->method('sendSubmissionNotifications')
            ->willThrowException(new EmailDeliveryException('db@test.com', 'Database connection lost'));

        $templateService = $this->createMock(EmailTemplateService::class);
        $service = new EmailService($notificationService, $templateService);

        $checklist = (new Checklist())
            ->setTitle('Database Dependent Process')
            ->setTargetEmail('db@test.com');

        $submission = (new Submission())
            ->setChecklist($checklist)
            ->setName('DB Test User')
            ->setMitarbeiterId('DB001')
            ->setEmail('dbuser@test.com')
            ->setData(['requires' => 'database_lookup']);

        $this->expectException(EmailDeliveryException::class);
        $this->expectExceptionMessage('Failed to send email to db@test.com: Database connection lost');

        $service->generateAndSendEmail($submission);
    }
}
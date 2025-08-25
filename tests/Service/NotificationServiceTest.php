<?php

namespace App\Tests\Service;

use App\Entity\Checklist;
use App\Entity\Submission;
use App\Exception\EmailDeliveryException;
use App\Service\MailerConfigService;
use App\Service\EmailTemplateService;
use App\Service\NotificationService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mailer\Exception\TransportException;

/**
 * Tests for NotificationService
 */
class NotificationServiceTest extends TestCase
{
    public function testSendSubmissionNotificationsHandlesSmtpFailure(): void
    {
        // Mock services
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects($this->once())
               ->method('send')
               ->willThrowException(new TransportException('SMTP connection failed'));

        $mailerConfigService = $this->createMock(MailerConfigService::class);
        $mailerConfigService->method('getConfiguredMailer')->willReturn($mailer);
        $mailerConfigService->method('getSenderEmail')->willReturn('noreply@test.com');

        $templateService = $this->createMock(EmailTemplateService::class);
        $templateService->method('renderEmailTemplate')->willReturn('<p>Test content</p>');

        $service = new NotificationService($mailerConfigService, $templateService);

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

        $service->sendSubmissionNotifications($submission);
    }

    public function testSendSubmissionNotificationsReturnsContent(): void
    {
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects($this->exactly(2))->method('send'); // target + confirmation

        $mailerConfigService = $this->createMock(MailerConfigService::class);
        $mailerConfigService->method('getConfiguredMailer')->willReturn($mailer);
        $mailerConfigService->method('getSenderEmail')->willReturn('noreply@test.com');

        $expectedContent = '<p>Target email content</p>';
        $templateService = $this->createMock(EmailTemplateService::class);
        $templateService->method('renderEmailTemplate')->willReturn($expectedContent);

        $service = new NotificationService($mailerConfigService, $templateService);

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

        $result = $service->sendSubmissionNotifications($submission);
        $this->assertEquals($expectedContent, $result);
    }

    public function testSendLinkEmailCallsMailer(): void
    {
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects($this->once())->method('send');

        $mailerConfigService = $this->createMock(MailerConfigService::class);
        $mailerConfigService->method('getConfiguredMailer')->willReturn($mailer);
        $mailerConfigService->method('getSenderEmail')->willReturn('noreply@test.com');

        $templateService = $this->createMock(EmailTemplateService::class);
        $templateService->method('renderLinkTemplate')->willReturn('<p>Link email content</p>');

        $service = new NotificationService($mailerConfigService, $templateService);

        $checklist = (new Checklist())->setTitle('Test List');
        $service->sendLinkEmail(
            $checklist,
            'Manager Name',
            'm@example.com',
            '123',
            'Employee Name',
            'Please fill this out',
            'http://example.com/form'
        );
    }
}
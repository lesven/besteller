<?php
namespace App\Tests\Service;

use App\Entity\Checklist;
use App\Entity\Submission;
use App\Service\EmailService;
use App\Service\NotificationService;
use App\Service\EmailTemplateService;
use PHPUnit\Framework\TestCase;

class EmailServiceTest extends TestCase
{
    public function testGenerateAndSendEmail(): void
    {
        $notificationService = $this->createMock(NotificationService::class);
        $templateService = $this->createMock(EmailTemplateService::class);
        
        $expectedHtml = '<p>Test content for Alice with reply@test</p>';
        $notificationService->expects($this->once())
            ->method('sendSubmissionNotifications')
            ->willReturn($expectedHtml);
            
        $service = new EmailService($notificationService, $templateService);
        $checklist = (new Checklist())->setTitle("List")->setTargetEmail("target@test")->setReplyEmail("reply@test");
        $submission = (new Submission())->setChecklist($checklist)->setName("Alice")->setMitarbeiterId("123")->setEmail("alice@test")->setData([]);
        
        $html = $service->generateAndSendEmail($submission);
        $this->assertEquals($expectedHtml, $html);
    }

    public function testGetDefaultTemplateForAdmin(): void
    {
        $notificationService = $this->createMock(NotificationService::class);
        $templateService = $this->createMock(EmailTemplateService::class);
        
        $expectedTemplate = '<html>Default template</html>';
        $templateService->expects($this->exactly(2))
            ->method('getDefaultSubmissionTemplate')
            ->willReturn($expectedTemplate);
            
        $service = new EmailService($notificationService, $templateService);
        $this->assertSame($service->getDefaultTemplate(), $service->getDefaultTemplateForAdmin());
    }

    public function testSendLinkEmailUsesNotificationService(): void
    {
        $notificationService = $this->createMock(NotificationService::class);
        $templateService = $this->createMock(EmailTemplateService::class);
        
        $checklist = (new Checklist())->setTitle("List");
        
        $notificationService->expects($this->once())
            ->method('sendLinkEmail')
            ->with(
                $checklist,
                "Manager",
                "m@example.com",
                "123",
                "Alice",
                "Intro",
                "http://example.com"
            );
            
        $service = new EmailService($notificationService, $templateService);
        $service->sendLinkEmail($checklist, "Manager", "m@example.com", "123", "Alice", "Intro", "http://example.com");
    }
}

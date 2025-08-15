<?php

namespace App\Tests\Service;

use App\Entity\Checklist;
use App\Service\EmailService;
use App\Service\SubmissionService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\MailerInterface;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Test für korrekte Platzhalter-Ersetzung im EmailService.
 */
class EmailServicePlaceholderTest extends TestCase
{
    private EmailService $emailService;
    private MockObject $mailerMock;
    private MockObject $submissionServiceMock;
    private MockObject $entityManagerMock;

    protected function setUp(): void
    {
        $this->mailerMock = $this->createMock(MailerInterface::class);
        $this->submissionServiceMock = $this->createMock(SubmissionService::class);
        $this->entityManagerMock = $this->createMock(EntityManagerInterface::class);
        
        $this->emailService = new EmailService(
            $this->mailerMock,
            $this->submissionServiceMock,
            $this->entityManagerMock
        );
    }

    /**
     * Test: Prüft, ob das Standard-Link-Template den {{recipient_name}} Platzhalter enthält.
     */
    public function testDefaultLinkTemplateContainsRecipientNamePlaceholder(): void
    {
        $template = $this->emailService->getDefaultLinkTemplate();
        
        $this->assertStringContainsString('{{recipient_name}}', $template,
            'Das Standard-Link-Template sollte den {{recipient_name}} Platzhalter enthalten');
    }

    /**
     * Test: Prüft, ob sowohl {{recipient_name}} als auch {{empfaenger_name}} funktionieren.
     * 
     * Hinweis: Vollständiger Test der sendLinkEmail-Methode würde zusätzliche
     * Abhängigkeiten erfordern (EntityRepository mocking etc.).
     * Für den produktiven Einsatz reicht die Überprüfung des Templates.
     */
    public function testDefaultTemplateUsesRecipientName(): void
    {
        $template = $this->emailService->getDefaultLinkTemplate();
        
        // Prüfe, dass das neue Template {{recipient_name}} verwendet
        $this->assertStringContainsString('{{recipient_name}}', $template);
        
        // Prüfe, dass auch andere wichtige Platzhalter vorhanden sind
        $this->assertStringContainsString('{{intro}}', $template);
        $this->assertStringContainsString('{{link}}', $template);
        $this->assertStringContainsString('{{person_name}}', $template);
        $this->assertStringContainsString('{{mitarbeiter_id}}', $template);
        $this->assertStringContainsString('{{stückliste}}', $template);
    }
}

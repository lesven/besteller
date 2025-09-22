<?php

namespace App\Tests\Factory;

use App\Entity\Checklist;
use App\Factory\ChecklistFactory;
use App\Factory\EmailTemplateFactory;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

/**
 * Tests für ChecklistFactory.
 */
class ChecklistFactoryTest extends TestCase
{
    private ChecklistFactory $factory;
    private EntityManagerInterface $entityManager;
    private EmailTemplateFactory $emailTemplateFactory;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->emailTemplateFactory = $this->createMock(EmailTemplateFactory::class);
        
        // Mock-Rückgabewerte für E-Mail-Templates
        $this->emailTemplateFactory->method('getDefaultEmailTemplate')->willReturn('<html>default</html>');
        $this->emailTemplateFactory->method('getDefaultLinkEmailTemplate')->willReturn('<html>link</html>');
        $this->emailTemplateFactory->method('getDefaultConfirmationEmailTemplate')->willReturn('<html>confirmation</html>');
        
        $this->factory = new ChecklistFactory($this->entityManager, $this->emailTemplateFactory);
    }

    public function testCreateChecklistWithPersistTrue(): void
    {
        // EntityManager erwartet persist() Aufruf
        $this->entityManager->expects($this->once())->method('persist');
        
        $checklist = $this->factory->createChecklist(
            'Test Checklist',
            'test@example.com',
            'reply@example.com',
            true
        );
        
        $this->assertInstanceOf(Checklist::class, $checklist);
        $this->assertSame('Test Checklist', $checklist->getTitle());
        $this->assertSame('test@example.com', $checklist->getTargetEmail());
        $this->assertSame('reply@example.com', $checklist->getReplyEmail());
        $this->assertSame('<html>default</html>', $checklist->getEmailTemplate());
        $this->assertSame('<html>link</html>', $checklist->getLinkEmailTemplate());
        $this->assertSame('<html>confirmation</html>', $checklist->getConfirmationEmailTemplate());
    }

    public function testCreateChecklistWithPersistFalse(): void
    {
        // EntityManager erwartet KEINEN persist() Aufruf
        $this->entityManager->expects($this->never())->method('persist');
        
        $checklist = $this->factory->createChecklist(
            'Test Checklist',
            'test@example.com',
            'reply@example.com',
            false
        );
        
        $this->assertInstanceOf(Checklist::class, $checklist);
        $this->assertSame('Test Checklist', $checklist->getTitle());
    }

    public function testCreateChecklistCallsEmailTemplateFactory(): void
    {
        $this->emailTemplateFactory->expects($this->once())->method('getDefaultEmailTemplate');
        $this->emailTemplateFactory->expects($this->once())->method('getDefaultLinkEmailTemplate');
        $this->emailTemplateFactory->expects($this->once())->method('getDefaultConfirmationEmailTemplate');
        
        $this->factory->createChecklist(
            'Test Checklist',
            'test@example.com',
            'reply@example.com'
        );
    }
}
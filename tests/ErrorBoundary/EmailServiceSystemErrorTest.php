<?php

namespace App\Tests\ErrorBoundary;

use App\Entity\Checklist;
use App\Entity\Submission;
use App\Service\EmailService;
use App\Service\SubmissionService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mime\Email;

/**
 * Tests für EmailService System-Fehlerfälle.
 * Verhalten bei SMTP-Ausfällen, Netzwerkproblemen und anderen System-Fehlern.
 */
class EmailServiceSystemErrorTest extends TestCase
{
    public function testGenerateAndSendEmailHandlesSmtpConnectionFailure(): void
    {
        // SMTP-Server nicht erreichbar simulieren
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects($this->once())
               ->method('send')
               ->willThrowException(new TransportException('SMTP connection failed'));

        $submissionService = $this->createMock(SubmissionService::class);
        $submissionService->method('formatSubmissionForEmail')->willReturn('<ul><li>Test Item</li></ul>');

        $repo = $this->createMock(ObjectRepository::class);
        $repo->method('find')->willReturn(null);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($repo);

        $service = new EmailService($mailer, $submissionService, $em);

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

        // Erwartet: TransportException wird weitergegeben, da System-Fehler nicht gefangen werden können
        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('SMTP connection failed');

        $service->generateAndSendEmail($submission);
    }

    public function testGenerateAndSendEmailHandlesNetworkTimeout(): void
    {
        // Netzwerk-Timeout simulieren
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects($this->once())
               ->method('send')
               ->willThrowException(new TransportException('Network timeout'));

        $submissionService = $this->createMock(SubmissionService::class);
        $submissionService->method('formatSubmissionForEmail')->willReturn('<ul><li>Test Item</li></ul>');

        $repo = $this->createMock(ObjectRepository::class);
        $repo->method('find')->willReturn(null);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($repo);

        $service = new EmailService($mailer, $submissionService, $em);

        $checklist = (new Checklist())
            ->setTitle('Test Checklist')
            ->setTargetEmail('target@test.com');

        $submission = (new Submission())
            ->setChecklist($checklist)
            ->setName('Anna Schmidt')
            ->setMitarbeiterId('EMP456')
            ->setEmail('anna@test.com')
            ->setData([]);

        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('Network timeout');

        $service->generateAndSendEmail($submission);
    }

    public function testGenerateAndSendEmailHandlesAuthenticationFailure(): void
    {
        // SMTP-Authentifizierung fehlgeschlagen simulieren
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects($this->once())
               ->method('send')
               ->willThrowException(new TransportException('SMTP authentication failed'));

        $submissionService = $this->createMock(SubmissionService::class);
        $submissionService->method('formatSubmissionForEmail')->willReturn('<ul><li>Hardware</li></ul>');

        $repo = $this->createMock(ObjectRepository::class);
        $repo->method('find')->willReturn(null);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($repo);

        $service = new EmailService($mailer, $submissionService, $em);

        $checklist = (new Checklist())
            ->setTitle('Hardware Checklist')
            ->setTargetEmail('it@company.com');

        $submission = (new Submission())
            ->setChecklist($checklist)
            ->setName('Peter Weber')
            ->setMitarbeiterId('EMP789')
            ->setEmail('peter@company.com')
            ->setData(['laptop' => 'MacBook Pro']);

        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('SMTP authentication failed');

        $service->generateAndSendEmail($submission);
    }

    public function testSendLinkEmailHandlesMailerFailure(): void
    {
        // E-Mail-Versand-Fehler beim Link-Versand simulieren
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects($this->once())
               ->method('send')
               ->willThrowException(new TransportException('Mail server temporarily unavailable'));

        $submissionService = $this->createMock(SubmissionService::class);
        $repo = $this->createMock(ObjectRepository::class);
        $repo->method('find')->willReturn(null);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($repo);

        $service = new EmailService($mailer, $submissionService, $em);

        $checklist = (new Checklist())
            ->setTitle('Test Link Checklist')
            ->setTargetEmail('target@test.com');

        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('Mail server temporarily unavailable');

        $service->sendLinkEmail(
            $checklist,
            'Test User',
            'test@example.com',
            'EMP123',
            'Max Mustermann',
            'Test-Einladung zur Bestellung',
            'https://example.com/link'
        );
    }

    public function testGenerateAndSendEmailHandlesMemoryExhaustion(): void
    {
        // Speicher-Erschöpfung simulieren (große E-Mail-Inhalte)
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects($this->once())
               ->method('send')
               ->willThrowException(new \Error('Allowed memory size exhausted'));

        $submissionService = $this->createMock(SubmissionService::class);
        // Sehr große Daten simulieren
        $submissionService->method('formatSubmissionForEmail')
                         ->willReturn(str_repeat('<li>Very large content item</li>', 10000));

        $repo = $this->createMock(ObjectRepository::class);
        $repo->method('find')->willReturn(null);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($repo);

        $service = new EmailService($mailer, $submissionService, $em);

        $checklist = (new Checklist())
            ->setTitle('Large Data Checklist')
            ->setTargetEmail('test@memory.com');

        $submission = (new Submission())
            ->setChecklist($checklist)
            ->setName('Memory Test User')
            ->setMitarbeiterId('MEM001')
            ->setEmail('memtest@test.com')
            ->setData(array_fill(0, 1000, 'large_data_item'));

        $this->expectException(\Error::class);
        $this->expectExceptionMessage('Allowed memory size exhausted');

        $service->generateAndSendEmail($submission);
    }

    public function testGenerateAndSendEmailHandlesDatabaseConnectionLoss(): void
    {
        // Datenbankverbindung verloren simulieren
        $mailer = $this->createMock(MailerInterface::class);
        
        $submissionService = $this->createMock(SubmissionService::class);
        $submissionService->method('formatSubmissionForEmail')->willReturn('<ul><li>Test</li></ul>');

        $repo = $this->createMock(ObjectRepository::class);
        $repo->method('find')
             ->willThrowException(new \RuntimeException('Database connection lost'));

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($repo);

        $service = new EmailService($mailer, $submissionService, $em);

        $checklist = (new Checklist())
            ->setTitle('DB Test Checklist')
            ->setTargetEmail('db@test.com');

        $submission = (new Submission())
            ->setChecklist($checklist)
            ->setName('DB Test User')
            ->setMitarbeiterId('DB001')
            ->setEmail('dbtest@test.com')
            ->setData([]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Database connection lost');

        $service->generateAndSendEmail($submission);
    }
}
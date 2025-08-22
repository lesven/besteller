<?php

namespace App\Tests\ErrorBoundary;

use App\Entity\Checklist;
use App\Entity\Submission;
use App\Service\LinkSenderService;
use App\Service\EmailService;
use App\Service\EmployeeIdValidatorService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Doctrine\DBAL\Exception\ConnectionException;
use Doctrine\DBAL\Exception\DeadlockException;
use Doctrine\DBAL\Exception\LockWaitTimeoutException;

/**
 * Tests für LinkSenderService System-Fehlerfälle.
 * Verhalten bei Datenbankausfällen, Deadlocks und anderen System-Fehlern.
 */
class LinkSenderServiceSystemErrorTest extends TestCase
{
    public function testSendChecklistLinkHandlesDatabaseConnectionFailure(): void
    {
        // Datenbankverbindung fehlgeschlagen simulieren
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getRepository')
                     ->willThrowException(new ConnectionException('Connection to database failed'));

        $emailService = $this->createMock(EmailService::class);
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $employeeIdValidator = $this->createMock(EmployeeIdValidatorService::class);
        $employeeIdValidator->method('isValid')->willReturn(true);

        $service = new LinkSenderService($entityManager, $emailService, $urlGenerator, $employeeIdValidator);

        $checklist = new Checklist();
        $checklist->setTitle('Test Checklist');

        $this->expectException(ConnectionException::class);
        $this->expectExceptionMessage('Connection to database failed');

        $service->sendChecklistLink(
            $checklist,
            'Max Mustermann',
            'max@test.com',
            'EMP123',
            'Max Test',
            'Test Intro'
        );
    }

    public function testSendChecklistLinkHandlesDatabaseDeadlock(): void
    {
        // Datenbank-Deadlock simulieren
        $repo = $this->createMock(ObjectRepository::class);
        $repo->method('findOneByChecklistAndMitarbeiterId')
             ->willThrowException(new DeadlockException('Deadlock found when trying to get lock'));

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getRepository')->willReturn($repo);

        $emailService = $this->createMock(EmailService::class);
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $employeeIdValidator = $this->createMock(EmployeeIdValidatorService::class);
        $employeeIdValidator->method('isValid')->willReturn(true);

        $service = new LinkSenderService($entityManager, $emailService, $urlGenerator, $employeeIdValidator);

        $checklist = new Checklist();
        $checklist->setTitle('Deadlock Test Checklist');

        $this->expectException(DeadlockException::class);
        $this->expectExceptionMessage('Deadlock found when trying to get lock');

        $service->sendChecklistLink(
            $checklist,
            'Anna Schmidt',
            'anna@company.com',
            'EMP456',
            'Anna Test',
            'Deadlock Test'
        );
    }

    public function testSendChecklistLinkHandlesLockWaitTimeout(): void
    {
        // Lock-Wait-Timeout simulieren
        $repo = $this->createMock(ObjectRepository::class);
        $repo->method('findOneByChecklistAndMitarbeiterId')
             ->willThrowException(new LockWaitTimeoutException('Lock wait timeout exceeded'));

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getRepository')->willReturn($repo);

        $emailService = $this->createMock(EmailService::class);
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $employeeIdValidator = $this->createMock(EmployeeIdValidatorService::class);
        $employeeIdValidator->method('isValid')->willReturn(true);

        $service = new LinkSenderService($entityManager, $emailService, $urlGenerator, $employeeIdValidator);

        $checklist = new Checklist();
        $checklist->setTitle('Lock Timeout Test');

        $this->expectException(LockWaitTimeoutException::class);
        $this->expectExceptionMessage('Lock wait timeout exceeded');

        $service->sendChecklistLink(
            $checklist,
            'Peter Weber',
            'peter@timeout.com',
            'EMP789',
            'Peter Test',
            'Lock Test'
        );
    }

    public function testSendChecklistLinkHandlesUrlGeneratorFailure(): void
    {
        // URL-Generator Fehler simulieren
        $submission = new Submission();
        $repo = $this->createMock(ObjectRepository::class);
        $repo->method('findOneByChecklistAndMitarbeiterId')->willReturn(null);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getRepository')->willReturn($repo);

        $emailService = $this->createMock(EmailService::class);
        
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')
                    ->willThrowException(new \Symfony\Component\Routing\Exception\RouteNotFoundException('Route not found'));

        $employeeIdValidator = $this->createMock(EmployeeIdValidatorService::class);
        $employeeIdValidator->method('isValid')->willReturn(true);

        $service = new LinkSenderService($entityManager, $emailService, $urlGenerator, $employeeIdValidator);

        $checklist = new Checklist();
        $checklist->setTitle('URL Generation Test');

        $this->expectException(\Symfony\Component\Routing\Exception\RouteNotFoundException::class);
        $this->expectExceptionMessage('Route not found');

        $service->sendChecklistLink(
            $checklist,
            'URL Test User',
            'url@test.com',
            'URL123',
            'URL Test',
            'URL Generation Test'
        );
    }

    public function testSendChecklistLinkHandlesEmailServiceFailure(): void
    {
        // E-Mail-Service Fehler simulieren
        $repo = $this->createMock(ObjectRepository::class);
        $repo->method('findOneByChecklistAndMitarbeiterId')->willReturn(null);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getRepository')->willReturn($repo);

        $emailService = $this->createMock(EmailService::class);
        $emailService->method('sendLinkEmail')
                    ->willThrowException(new \RuntimeException('Email service temporarily unavailable'));

        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturn('https://test.com/link');

        $employeeIdValidator = $this->createMock(EmployeeIdValidatorService::class);
        $employeeIdValidator->method('isValid')->willReturn(true);

        $service = new LinkSenderService($entityManager, $emailService, $urlGenerator, $employeeIdValidator);

        $checklist = new Checklist();
        $checklist->setTitle('Email Service Test');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Email service temporarily unavailable');

        $service->sendChecklistLink(
            $checklist,
            'Email Test User',
            'email@test.com',
            'EMAIL123',
            'Email Test',
            'Email Service Test'
        );
    }

    public function testSendChecklistLinkHandlesEmployeeValidatorOutage(): void
    {
        // Employee-ID-Validator-Service ausgefallen simulieren
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $emailService = $this->createMock(EmailService::class);
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        
        $employeeIdValidator = $this->createMock(EmployeeIdValidatorService::class);
        $employeeIdValidator->method('isValid')
                           ->willThrowException(new \RuntimeException('Employee validation service unavailable'));

        $service = new LinkSenderService($entityManager, $emailService, $urlGenerator, $employeeIdValidator);

        $checklist = new Checklist();
        $checklist->setTitle('Validator Test');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Employee validation service unavailable');

        $service->sendChecklistLink(
            $checklist,
            'Validator Test User',
            'validator@test.com',
            'VAL123',
            'Validator Test',
            'Validator Service Test'
        );
    }

    public function testSendChecklistLinkHandlesOutOfMemoryError(): void
    {
        // Speicher-Erschöpfung simulieren bei großen Datenmengen
        $repo = $this->createMock(ObjectRepository::class);
        $repo->method('findOneByChecklistAndMitarbeiterId')
             ->willThrowException(new \Error('Allowed memory size of 128MB exhausted'));

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getRepository')->willReturn($repo);

        $emailService = $this->createMock(EmailService::class);
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $employeeIdValidator = $this->createMock(EmployeeIdValidatorService::class);
        $employeeIdValidator->method('isValid')->willReturn(true);

        $service = new LinkSenderService($entityManager, $emailService, $urlGenerator, $employeeIdValidator);

        $checklist = new Checklist();
        $checklist->setTitle('Memory Test');

        $this->expectException(\Error::class);
        $this->expectExceptionMessage('Allowed memory size of 128MB exhausted');

        $service->sendChecklistLink(
            $checklist,
            'Memory Test User',
            'memory@test.com',
            'MEM123',
            'Memory Test',
            'Memory Exhaustion Test'
        );
    }
}
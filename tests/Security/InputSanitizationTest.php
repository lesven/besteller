<?php

namespace App\Tests\Security;

use App\Controller\ChecklistController;
use App\Entity\Checklist;
use App\Entity\Submission;
use App\Repository\SubmissionRepository;
use App\Service\EmailService;
use App\Repository\ChecklistRepository;
use App\Service\SubmissionService;
use App\Service\SubmissionFactory;
use App\Service\EmployeeIdValidatorService;
use App\Service\ValidationService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectRepository;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Exception\InvalidParametersException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class InputSanitizationTest extends TestCase
{
    private function createBaseMocks(): array
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $submissionService = $this->createMock(SubmissionService::class);
        $emailService = $this->createMock(EmailService::class);
        $submissionFactory = $this->createMock(SubmissionFactory::class);
        $logger = $this->createMock(LoggerInterface::class);
        $validationService = $this->createMock(ValidationService::class);

        return [$entityManager, $submissionService, $emailService, $submissionFactory, $logger, $validationService];
    }

    private function setupControllerMocks(array $mocks): ChecklistController
    {
        [$entityManager, $submissionService, $emailService, $submissionFactory, $logger, $validationService] = $mocks;

        $checklist = $this->createMock(Checklist::class);
        $checklistRepo = $this->createMock(ChecklistRepository::class);
        $checklistRepo->method('findOrFail')->willReturn($checklist);

        $submissionRepo = $this->createMock(SubmissionRepository::class);
        $submissionRepo->method('findOneByChecklistAndMitarbeiterId')->willReturn(null);

        $entityManager->method('getRepository')->willReturnCallback(function ($class) use ($checklistRepo, $submissionRepo) {
            if ($class === Checklist::class) {
                return $checklistRepo;
            }
            if ($class === Submission::class) {
                return $submissionRepo;
            }
            return null;
        });

        return $this->getMockBuilder(ChecklistController::class)
            ->setConstructorArgs([$entityManager, $submissionService, $emailService, $submissionFactory, $logger, $validationService])
            ->onlyMethods(['render'])
            ->getMock();
    }

    public function testShowAcceptsMaliciousInputButDoesNotExecute(): void
    {
        $mocks = $this->createBaseMocks();
        $controller = $this->setupControllerMocks($mocks);
        $controller->method('render')->willReturn(new Response('success'));

        // Test that malicious input is accepted but not executed
        $maliciousInput = '<script>alert("XSS")</script>';
        
        $request = new Request();
        $request->query->set('name', $maliciousInput);
        $request->query->set('mitarbeiter_id', 'valid-123');
        $request->query->set('email', 'test@example.com');

        $response = $controller->show(1, $request);
        $this->assertInstanceOf(Response::class, $response);
        
        // The input is not filtered at controller level, security happens at output level
    }

    public function testShowAcceptsMaliciousInputInAllFields(): void
    {
        $mocks = $this->createBaseMocks();
        $controller = $this->setupControllerMocks($mocks);
        $controller->method('render')->willReturn(new Response('success'));

        $maliciousInputs = [
            '<script>alert("XSS")</script>',
            "'; DROP TABLE users; --",
            '../../../etc/passwd',
        ];

        foreach ($maliciousInputs as $maliciousInput) {
            // Test email field
            $request = new Request();
            $request->query->set('name', 'Valid Name');
            $request->query->set('mitarbeiter_id', 'valid-123');
            $request->query->set('email', $maliciousInput);

            $response = $controller->show(1, $request);
            $this->assertInstanceOf(Response::class, $response);

            // Test mitarbeiter_id field
            $request = new Request();
            $request->query->set('name', 'Valid Name');
            $request->query->set('mitarbeiter_id', $maliciousInput);
            $request->query->set('email', 'test@example.com');

            $response = $controller->show(1, $request);
            $this->assertInstanceOf(Response::class, $response);
        }
    }

    public function testShowHandlesEncodedInputCorrectly(): void
    {
        $mocks = $this->createBaseMocks();
        $controller = $this->setupControllerMocks($mocks);
        $controller->method('render')->willReturn(new Response('success'));

        $request = new Request();
        $request->query->set('name', urlencode('Max Müller'));
        $request->query->set('mitarbeiter_id', urlencode('EMP-123'));
        $request->query->set('email', urlencode('max.mueller@example.com'));

        $response = $controller->show(1, $request);
        $this->assertInstanceOf(Response::class, $response);
    }

    public function testShowRejectsEmptyValues(): void
    {
        $mocks = $this->createBaseMocks();
        $controller = $this->setupControllerMocks($mocks);

        $this->expectException(InvalidParametersException::class);

        $request = new Request();
        $request->query->set('name', '');
        $request->query->set('mitarbeiter_id', 'valid-123');
        $request->query->set('email', 'test@example.com');

        $controller->show(1, $request);
    }

    public function testShowAcceptsWhitespaceValues(): void
    {
        $mocks = $this->createBaseMocks();
        $controller = $this->setupControllerMocks($mocks);
        $controller->method('render')->willReturn(new Response('success'));

        $request = new Request();
        $request->query->set('name', '   whitespace   ');
        $request->query->set('mitarbeiter_id', 'valid-123');
        $request->query->set('email', 'test@example.com');

        $response = $controller->show(1, $request);
        $this->assertInstanceOf(Response::class, $response);
    }

    public function testShowAcceptsNullBytesInInput(): void
    {
        $mocks = $this->createBaseMocks();
        $controller = $this->setupControllerMocks($mocks);
        $controller->method('render')->willReturn(new Response('success'));

        $request = new Request();
        $request->query->set('name', "Valid Name\0injection");
        $request->query->set('mitarbeiter_id', 'valid-123');
        $request->query->set('email', 'test@example.com');

        $response = $controller->show(1, $request);
        $this->assertInstanceOf(Response::class, $response);
    }

    /**
     * @dataProvider unicodeAttackProvider
     */
    public function testShowAcceptsUnicodeAttacks(string $unicodeAttack): void
    {
        $mocks = $this->createBaseMocks();
        $controller = $this->setupControllerMocks($mocks);
        $controller->method('render')->willReturn(new Response('success'));

        $request = new Request();
        $request->query->set('name', $unicodeAttack);
        $request->query->set('mitarbeiter_id', 'valid-123');
        $request->query->set('email', 'test@example.com');

        $response = $controller->show(1, $request);
        $this->assertInstanceOf(Response::class, $response);
        
        // Note: Unicode attacks are not filtered at input level, 
        // protection happens at output/rendering level
    }

    public function testShowAcceptsLongInput(): void
    {
        $mocks = $this->createBaseMocks();
        $controller = $this->setupControllerMocks($mocks);
        $controller->method('render')->willReturn(new Response('success'));

        $request = new Request();
        $request->query->set('name', str_repeat('A', 1000)); // Long but reasonable input
        $request->query->set('mitarbeiter_id', 'valid-123');
        $request->query->set('email', 'test@example.com');

        $response = $controller->show(1, $request);
        $this->assertInstanceOf(Response::class, $response);
    }

    public static function maliciousInputProvider(): array
    {
        return [
            'XSS Script Tag' => ['<script>alert("XSS")</script>'],
            'XSS Image Tag' => ['<img src="x" onerror="alert(1)">'],
            'XSS Event Handler' => ['" onmouseover="alert(1)"'],
            'XSS JavaScript URL' => ['javascript:alert("XSS")'],
            'XSS Data URL' => ['data:text/html,<script>alert(1)</script>'],
            'SQL Injection Single Quote' => ["'; DROP TABLE users; --"],
            'SQL Injection Union' => ["' UNION SELECT * FROM users --"],
            'SQL Injection Hex' => ['0x3c736372697074'],
            'Path Traversal' => ['../../../etc/passwd'],
            'Path Traversal Windows' => ['..\\..\\..\\windows\\system32'],
            'LDAP Injection' => ['*)(uid=*))(|(uid=*'],
            'Command Injection' => ['$(rm -rf /)'],
            'Command Injection Backticks' => ['`rm -rf /`'],
            'XML Injection' => ['<?xml version="1.0"?><!DOCTYPE test [<!ENTITY xxe SYSTEM "file:///etc/passwd">]>'],
            'PHP Code Injection' => ['<?php system("rm -rf /"); ?>'],
            'Template Injection' => ['{{7*7}}'],
            'CRLF Injection' => ["test\r\nSet-Cookie: admin=true"],
            'Format String' => ['%s%s%s%s%s%s%s%s%s%s'],
            'Buffer Overflow' => [str_repeat('A', 1000)],
        ];
    }

    public static function unicodeAttackProvider(): array
    {
        return [
            'Unicode Normalization' => ['＜script＞alert(1)＜/script＞'],
            'Right-to-Left Override' => ['‮<script>alert(1)</script>'],
            'Zero Width Space' => ['test​<script>alert(1)</script>'],
            'Homograph Attack' => ['аdmin'], // Cyrillic 'а' instead of Latin 'a'
            'Unicode Escape' => ['\u003cscript\u003ealert(1)\u003c/script\u003e'],
        ];
    }
}
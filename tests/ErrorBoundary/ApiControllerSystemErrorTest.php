<?php

namespace App\Tests\ErrorBoundary;

use App\Controller\ApiController;
use App\Service\LinkSenderService;
use App\Service\ApiValidationService;
use App\Service\EmployeeIdValidatorService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Doctrine\DBAL\Exception\ConnectionException;
use Symfony\Component\Mailer\Exception\TransportException;

/**
 * Tests für ApiController System-Fehlerfälle.
 * Verhalten bei kritischen System-Fehlern wie Datenbankausfällen, Netzwerkproblemen etc.
 */
class ApiControllerSystemErrorTest extends TestCase
{
    public function testGenerateLinkHandlesDatabaseConnectionFailure(): void
    {
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        
        $parameterBag = $this->createMock(ParameterBagInterface::class);
        $parameterBag->method('get')->willReturn('test-token');

        $employeeIdValidator = $this->createMock(EmployeeIdValidatorService::class);
        $employeeIdValidator->method('isValid')->willReturn(true);

        $linkSenderService = $this->createMock(LinkSenderService::class);
        
        $apiValidationService = $this->createMock(ApiValidationService::class);
        $apiValidationService->method('validateJson')->willReturn([
            'stückliste_id' => 1,
            'mitarbeiter_name' => 'Max Mustermann',
            'mitarbeiter_id' => 'EMP123',
            'email_empfänger' => 'max@test.com'
        ]);

        $controller = new ApiController(
            $urlGenerator,
            $parameterBag,
            $employeeIdValidator,
            $linkSenderService,
            $apiValidationService
        );

        // Datenbankverbindung fehlgeschlagen simulieren
        $request = new Request(
            [],
            [],
            [],
            [],
            [],
            ['HTTP_AUTHORIZATION' => 'Bearer test-token'],
            json_encode([
                'stückliste_id' => 1,
                'mitarbeiter_name' => 'Max Mustermann',
                'mitarbeiter_id' => 'EMP123',
                'email_empfänger' => 'max@test.com'
            ])
        );

        // API soll bei System-Fehlern eine 500-Antwort liefern, da diese nicht vorhersagbar sind
        $this->expectException(ConnectionException::class);

        $controller->generateLink($request);
    }

    public function testSendLinkHandlesEmailServiceFailure(): void
    {
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        
        $parameterBag = $this->createMock(ParameterBagInterface::class);
        $parameterBag->method('get')->willReturn('test-token');

        $employeeIdValidator = $this->createMock(EmployeeIdValidatorService::class);
        $employeeIdValidator->method('isValid')->willReturn(true);

        $linkSenderService = $this->createMock(LinkSenderService::class);
        $linkSenderService->method('sendChecklistLink')
                         ->willThrowException(new TransportException('SMTP server unreachable'));

        $apiValidationService = $this->createMock(ApiValidationService::class);
        $apiValidationService->method('validateJson')->willReturn([
            'checklist_id' => 1,
            'recipient_name' => 'Anna Schmidt',
            'recipient_email' => 'anna@test.com',
            'mitarbeiter_id' => 'EMP456'
        ]);

        $controller = new ApiController(
            $urlGenerator,
            $parameterBag,
            $employeeIdValidator,
            $linkSenderService,
            $apiValidationService
        );

        $checklistRepo = $this->createMock(\App\Repository\ChecklistRepository::class);
        $checklist = new \App\Entity\Checklist();
        $checklistRepo->method('find')->willReturn($checklist);

        $request = new Request(
            [],
            [],
            [],
            [],
            [],
            ['HTTP_AUTHORIZATION' => 'Bearer test-token'],
            json_encode([
                'checklist_id' => 1,
                'recipient_name' => 'Anna Schmidt',
                'recipient_email' => 'anna@test.com',
                'mitarbeiter_id' => 'EMP456'
            ])
        );

        // Bei E-Mail-Fehlern soll Exception weitergegeben werden
        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('SMTP server unreachable');

        $controller->sendLink($request, $checklistRepo);
    }

    public function testGenerateLinkHandlesOutOfMemoryError(): void
    {
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        
        $parameterBag = $this->createMock(ParameterBagInterface::class);
        $parameterBag->method('get')->willReturn('test-token');

        $employeeIdValidator = $this->createMock(EmployeeIdValidatorService::class);
        $employeeIdValidator->method('isValid')
                           ->willThrowException(new \Error('Allowed memory size exhausted'));

        $linkSenderService = $this->createMock(LinkSenderService::class);
        $apiValidationService = $this->createMock(ApiValidationService::class);
        $apiValidationService->method('validateJson')->willReturn([
            'stückliste_id' => 1,
            'mitarbeiter_name' => 'Memory Test',
            'mitarbeiter_id' => 'MEM123',
            'email_empfänger' => 'memory@test.com'
        ]);

        $controller = new ApiController(
            $urlGenerator,
            $parameterBag,
            $employeeIdValidator,
            $linkSenderService,
            $apiValidationService
        );

        $request = new Request(
            [],
            [],
            [],
            [],
            [],
            ['HTTP_AUTHORIZATION' => 'Bearer test-token'],
            json_encode([
                'stückliste_id' => 1,
                'mitarbeiter_name' => 'Memory Test',
                'mitarbeiter_id' => 'MEM123',
                'email_empfänger' => 'memory@test.com'
            ])
        );

        // Memory-Errors können nicht gefangen werden und führen zum Script-Abbruch
        $this->expectException(\Error::class);
        $this->expectExceptionMessage('Allowed memory size exhausted');

        $controller->generateLink($request);
    }

    public function testGenerateLinkHandlesCorruptedRequestData(): void
    {
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        
        $parameterBag = $this->createMock(ParameterBagInterface::class);
        $parameterBag->method('get')->willReturn('test-token');

        $employeeIdValidator = $this->createMock(EmployeeIdValidatorService::class);
        $linkSenderService = $this->createMock(LinkSenderService::class);
        
        $apiValidationService = $this->createMock(ApiValidationService::class);
        $apiValidationService->method('validateJson')
                            ->willThrowException(new \App\Exception\JsonValidationException('Corrupted request data'));

        $controller = new ApiController(
            $urlGenerator,
            $parameterBag,
            $employeeIdValidator,
            $linkSenderService,
            $apiValidationService
        );

        // Korrupte Daten simulieren (ungültiges JSON)
        $request = new Request(
            [],
            [],
            [],
            [],
            [],
            ['HTTP_AUTHORIZATION' => 'Bearer test-token'],
            'corrupted-non-json-data'
        );

        $response = $controller->generateLink($request);

        // Erwartet: API gibt BAD_REQUEST zurück bei korrupten Daten
        $this->assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        $responseData = json_decode($response->getContent(), true);
        $this->assertSame('Corrupted request data', $responseData['error']);
    }

    public function testSendLinkHandlesNetworkPartition(): void
    {
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')
                    ->willThrowException(new \RuntimeException('Network partition detected'));
        
        $parameterBag = $this->createMock(ParameterBagInterface::class);
        $parameterBag->method('get')->willReturn('test-token');

        $employeeIdValidator = $this->createMock(EmployeeIdValidatorService::class);
        $employeeIdValidator->method('isValid')->willReturn(true);

        $linkSenderService = $this->createMock(LinkSenderService::class);

        $apiValidationService = $this->createMock(ApiValidationService::class);
        $apiValidationService->method('validateJson')->willReturn([
            'checklist_id' => 1,
            'recipient_name' => 'Network Test',
            'recipient_email' => 'network@test.com',
            'mitarbeiter_id' => 'NET123'
        ]);

        $controller = new ApiController(
            $urlGenerator,
            $parameterBag,
            $employeeIdValidator,
            $linkSenderService,
            $apiValidationService
        );

        $checklistRepo = $this->createMock(\App\Repository\ChecklistRepository::class);
        $checklist = new \App\Entity\Checklist();
        $checklistRepo->method('find')->willReturn($checklist);

        $request = new Request(
            [],
            [],
            [],
            [],
            [],
            ['HTTP_AUTHORIZATION' => 'Bearer test-token'],
            json_encode([
                'checklist_id' => 1,
                'recipient_name' => 'Network Test',
                'recipient_email' => 'network@test.com',
                'mitarbeiter_id' => 'NET123'
            ])
        );

        // Bei Netzwerk-Partitionierung soll Exception weitergegeben werden
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Network partition detected');

        $controller->sendLink($request, $checklistRepo);
    }

    public function testSendLinkHandlesDiskSpaceExhaustion(): void
    {
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        
        $parameterBag = $this->createMock(ParameterBagInterface::class);
        $parameterBag->method('get')->willReturn('test-token');

        $employeeIdValidator = $this->createMock(EmployeeIdValidatorService::class);
        $employeeIdValidator->method('isValid')->willReturn(true);

        $linkSenderService = $this->createMock(LinkSenderService::class);
        $linkSenderService->method('sendChecklistLink')
                         ->willThrowException(new \RuntimeException('No space left on device'));

        $apiValidationService = $this->createMock(ApiValidationService::class);
        $apiValidationService->method('validateJson')->willReturn([
            'checklist_id' => 1,
            'recipient_name' => 'Disk Test',
            'recipient_email' => 'disk@test.com',
            'mitarbeiter_id' => 'DISK123'
        ]);

        $controller = new ApiController(
            $urlGenerator,
            $parameterBag,
            $employeeIdValidator,
            $linkSenderService,
            $apiValidationService
        );

        $checklistRepo = $this->createMock(\App\Repository\ChecklistRepository::class);
        $checklist = new \App\Entity\Checklist();
        $checklistRepo->method('find')->willReturn($checklist);

        $request = new Request(
            [],
            [],
            [],
            [],
            [],
            ['HTTP_AUTHORIZATION' => 'Bearer test-token'],
            json_encode([
                'checklist_id' => 1,
                'recipient_name' => 'Disk Test',
                'recipient_email' => 'disk@test.com',
                'mitarbeiter_id' => 'DISK123'
            ])
        );

        // Bei Festplatte-voll Fehlern soll Exception weitergegeben werden
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No space left on device');

        $controller->sendLink($request, $checklistRepo);
    }
}
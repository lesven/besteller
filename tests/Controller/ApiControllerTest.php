<?php
namespace App\Tests\Controller;

use App\Controller\ApiController;
use App\Exception\JsonValidationException;
use App\Service\EmployeeIdValidatorService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class ApiControllerTest extends TestCase
{
    public function testGenerateLinkReturnsLink(): void
    {
        $url = 'https://example.com/auswahl?list=123&name=Max%20Muster&id=abc-123&email=chef@example.com';
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->expects($this->once())
            ->method('generate')
            ->with(
                'checklist_selection',
                [
                    'list' => 123,
                    'name' => 'Max Muster',
                    'id' => 'abc-123',
                    'email' => 'chef@example.com',
                ],
                UrlGeneratorInterface::ABSOLUTE_URL
            )
            ->willReturn($url);

        $parameterBag = $this->createMock(ParameterBagInterface::class);
        $parameterBag->expects($this->once())
            ->method('get')
            ->willReturnCallback(function($key) {
                return $key === 'API_TOKEN' ? '' : null;
            });

        $employeeIdValidator = $this->createMock(EmployeeIdValidatorService::class);
        $employeeIdValidator->method('isValid')->with('abc-123')->willReturn(true);

        $request = new Request([], [], [], [], [], [], json_encode([
            'stückliste_id' => 123,
            'mitarbeiter_name' => 'Max Muster',
            'mitarbeiter_id' => 'abc-123',
            'email_empfänger' => 'chef@example.com',
        ]));
        $linkSenderService = $this->createMock(\App\Service\LinkSenderService::class);
        $apiValidationService = $this->createMock(\App\Service\ApiValidationService::class);
        $apiValidationService->method('validateJson')->willReturn([
            'stückliste_id' => 123,
            'mitarbeiter_name' => 'Max Muster',
            'mitarbeiter_id' => 'abc-123',
            'email_empfänger' => 'chef@example.com',
        ]);

        $controller = new ApiController($urlGenerator, $parameterBag, $employeeIdValidator, $linkSenderService, $apiValidationService);
        $response = $controller->generateLink($request);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertSame($url, $data['link']);
    }

    public function testGenerateLinkRequiresParameters(): void
    {
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $parameterBag = $this->createMock(ParameterBagInterface::class);
        $parameterBag->expects($this->once())
            ->method('get')
            ->willReturnCallback(function($key) {
                return $key === 'API_TOKEN' ? '' : null;
            });

        $employeeIdValidator = $this->createMock(EmployeeIdValidatorService::class);
        $linkSenderService = $this->createMock(\App\Service\LinkSenderService::class);
        $apiValidationService = $this->createMock(\App\Service\ApiValidationService::class);

        $controller = new ApiController($urlGenerator, $parameterBag, $employeeIdValidator, $linkSenderService, $apiValidationService);
        $request = new Request([], [], [], [], [], [], json_encode(['foo' => 'bar']));

        $response = $controller->generateLink($request);
        $this->assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    public function testGenerateLinkChecksBearerToken(): void
    {
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $parameterBag = $this->createMock(ParameterBagInterface::class);
        $parameterBag->expects($this->once())
            ->method('get')
            ->willReturnCallback(function($key) {
                return $key === 'API_TOKEN' ? 'secret' : null;
            });

        $employeeIdValidator = $this->createMock(EmployeeIdValidatorService::class);
        $linkSenderService = $this->createMock(\App\Service\LinkSenderService::class);
        $apiValidationService = $this->createMock(\App\Service\ApiValidationService::class);
            
        $controller = new ApiController($urlGenerator, $parameterBag, $employeeIdValidator,$linkSenderService,$apiValidationService);
        $request = new Request([], [], [], [], [], [], json_encode([
            'stückliste_id' => 1,
            'mitarbeiter_name' => 'A',
            'mitarbeiter_id' => 'B',
            'email_empfänger' => 'a@example.com',
        ]));
        $request->headers->set('Authorization', 'Bearer wrong');

        $response = $controller->generateLink($request);
        $this->assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
    }

    public function testSendLinkCallsServiceAndReturnsStatus(): void
    {
        $url = 'https://example.com/form?checklist_id=1&name=Alice&id=123&email=b@example.com';
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->expects($this->once())
            ->method('generate')
            ->with(
                'checklist_form',
                [
                    'checklist_id' => null,
                    'name' => 'Alice',
                    'mitarbeiter_id' => '123',
                    'email' => 'b@example.com',
                ],
                UrlGeneratorInterface::ABSOLUTE_URL
            )
            ->willReturn($url);

        $parameterBag = $this->createMock(ParameterBagInterface::class);
        $parameterBag->expects($this->once())
            ->method('get')
            ->willReturnCallback(fn($k) => $k === 'API_TOKEN' ? '' : null);

        $employeeIdValidator = $this->createMock(EmployeeIdValidatorService::class);
        $employeeIdValidator->method('isValid')->with('123')->willReturn(true);

        $checklist = new \App\Entity\Checklist();
        $repo = $this->createMock(\App\Repository\ChecklistRepository::class);
        $repo->expects($this->once())
            ->method('find')
            ->with(1)
            ->willReturn($checklist);

        $linkSenderService = $this->createMock(\App\Service\LinkSenderService::class);
        $linkSenderService->expects($this->once())
            ->method('sendChecklistLink')
            ->with($checklist, 'Bob', 'b@example.com', '123', 'Alice', 'Intro');

        $request = new Request([], [], [], [], [], [], json_encode([
            'checklist_id' => 1,
            'recipient_name' => 'Bob',
            'recipient_email' => 'b@example.com',
            'mitarbeiter_id' => '123',
            'person_name' => 'Alice',
            'intro' => 'Intro',
        ]));

    $apiValidationService = $this->createMock(\App\Service\ApiValidationService::class);
        $apiValidationService->method('validateJson')->willReturn([
            'checklist_id' => 1,
            'recipient_name' => 'Bob',
            'recipient_email' => 'b@example.com',
            'mitarbeiter_id' => '123',
            'person_name' => 'Alice',
            'intro' => 'Intro',
        ]);

        $controller = new ApiController($urlGenerator, $parameterBag, $employeeIdValidator, $linkSenderService, $apiValidationService);
    $response = $controller->sendLink($request, $repo);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertSame('sent', $data['status']);
    }

    public function testSendLinkReturnsConflictOnDuplicate(): void
    {
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $parameterBag = $this->createMock(ParameterBagInterface::class);
        $parameterBag->expects($this->once())
            ->method('get')
            ->willReturnCallback(fn($k) => $k === 'API_TOKEN' ? '' : null);

        $employeeIdValidator = $this->createMock(EmployeeIdValidatorService::class);
        $employeeIdValidator->method('isValid')->with('123')->willReturn(true);

        $checklist = new \App\Entity\Checklist();
        $repo = $this->createMock(\App\Repository\ChecklistRepository::class);
        $repo->expects($this->once())
            ->method('find')
            ->with(1)
            ->willReturn($checklist);

            $linkSenderService = $this->createMock(\App\Service\LinkSenderService::class);
            $linkSenderService->expects($this->once())
                ->method('sendChecklistLink')
                ->willThrowException(new \RuntimeException('Für diese Personen-ID/Listen Kombination wurde bereits eine Bestellung übermittelt.'));

        $request = new Request([], [], [], [], [], [], json_encode([
            'checklist_id' => 1,
            'recipient_name' => 'Bob',
            'recipient_email' => 'b@example.com',
            'mitarbeiter_id' => '123',
        ]));
    $apiValidationService = $this->createMock(\App\Service\ApiValidationService::class);
        $apiValidationService->method('validateJson')->willReturn([
            'checklist_id' => 1,
            'recipient_name' => 'Bob',
            'recipient_email' => 'b@example.com',
            'mitarbeiter_id' => '123',
        ]);

        $controller = new ApiController($urlGenerator, $parameterBag, $employeeIdValidator, $linkSenderService, $apiValidationService);
    $response = $controller->sendLink($request, $repo);

        $this->assertSame(Response::HTTP_CONFLICT, $response->getStatusCode());
    }

    public function testSendLinkValidatesParameters(): void
    {
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $parameterBag = $this->createMock(ParameterBagInterface::class);
        $parameterBag->expects($this->once())
            ->method('get')
            ->willReturnCallback(fn($k) => $k === 'API_TOKEN' ? '' : null);

        $employeeIdValidator = $this->createMock(EmployeeIdValidatorService::class);

        $repo = $this->createMock(\App\Repository\ChecklistRepository::class);
        $emailService = $this->createMock(\App\Service\EmailService::class);
        $submissionRepo = $this->createMock(\App\Repository\SubmissionRepository::class);
        $linkSenderService = $this->createMock(\App\Service\LinkSenderService::class);
        $apiValidationService = $this->createMock(\App\Service\ApiValidationService::class);
        $apiValidationService->method('validateJson')->willThrowException(
            new \App\Exception\JsonValidationException('Fehlende Parameter')
        );

        $controller = new ApiController($urlGenerator, $parameterBag, $employeeIdValidator, $linkSenderService, $apiValidationService);
        $request = new Request([], [], [], [], [], [], json_encode(['foo' => 'bar']));
    $response = $controller->sendLink($request, $repo);

        $this->assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    public function testJsonValidationExceptionHandling(): void
    {
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $parameterBag = $this->createMock(ParameterBagInterface::class);
        $parameterBag->method('get')->willReturnCallback(fn($k) => $k === 'API_TOKEN' ? '' : null);
        $employeeIdValidator = $this->createMock(EmployeeIdValidatorService::class);
        $linkSenderService = $this->createMock(\App\Service\LinkSenderService::class);
        $apiValidationService = $this->createMock(\App\Service\ApiValidationService::class);
        $apiValidationService->method('validateJson')
            ->will($this->returnCallback(function($request, $required) {
                $json = $request->getContent();
                if ($json === '{invalid json') {
                    throw new \App\Exception\JsonValidationException('Ungültiges JSON');
                }
                throw new \App\Exception\JsonValidationException('Fehlende Parameter');
            }));

        $controller = new ApiController($urlGenerator, $parameterBag, $employeeIdValidator, $linkSenderService, $apiValidationService);

        // Test 1: Invalid JSON syntax should return BAD_REQUEST
        $invalidJsonRequest = new Request([], [], [], [], [], [], '{invalid json');
        $response = $controller->generateLink($invalidJsonRequest);
        $this->assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        $responseData = json_decode($response->getContent(), true);
        $this->assertSame('Ungültiges JSON', $responseData['error']);

        // Test 2: Missing required fields should return BAD_REQUEST
        $missingFieldsRequest = new Request([], [], [], [], [], [], json_encode(['incomplete' => 'data']));
        $response = $controller->generateLink($missingFieldsRequest);
        $this->assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        $responseData = json_decode($response->getContent(), true);
        $this->assertSame('Fehlende Parameter', $responseData['error']);
    }
}

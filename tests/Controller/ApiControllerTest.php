<?php
namespace App\Tests\Controller;

use App\Controller\ApiController;
use App\Exception\JsonValidationException;
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

        $controller = new ApiController($urlGenerator, $parameterBag, $linkSenderService, $apiValidationService);
        $response = $controller->generateLink($request);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('link', $data);
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

        $linkSenderService = $this->createMock(\App\Service\LinkSenderService::class);
        $apiValidationService = $this->createMock(\App\Service\ApiValidationService::class);

        $controller = new ApiController($urlGenerator, $parameterBag, $linkSenderService, $apiValidationService);
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

        $linkSenderService = $this->createMock(\App\Service\LinkSenderService::class);
        $apiValidationService = $this->createMock(\App\Service\ApiValidationService::class);
            
        $controller = new ApiController($urlGenerator, $parameterBag, $linkSenderService, $apiValidationService);
        $request = new Request([], [], [], [], [], [], json_encode([
            'stückliste_id' => 1,
            'mitarbeiter_name' => 'A',
            'mitarbeiter_id' => 'B',
            'email_empfänger' => 'C',
        ]));

        $response = $controller->generateLink($request);
        $this->assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
    }

    public function testSendLinkCallsServiceAndReturnsStatus(): void
    {
        $url = 'https://example.com/form?checklist_id=1&name=Recipient&mitarbeiter_id=123&email=rec@example.com';
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->expects($this->once())
            ->method('generate')
            ->with(
                'checklist_form',
                [
                    'checklist_id' => 1,
                    'name' => 'Recipient',
                    'mitarbeiter_id' => '123',
                    'email' => 'rec@example.com',
                ]
            )
            ->willReturn($url);

        $parameterBag = $this->createMock(ParameterBagInterface::class);
        $parameterBag->expects($this->once())
            ->method('get')
            ->willReturnCallback(fn($k) => $k === 'API_TOKEN' ? '' : null);

        $checklist = new \App\Entity\Checklist();
        // Use reflection to set the private ID field
        $reflection = new \ReflectionClass($checklist);
        $idProperty = $reflection->getProperty('id');
        $idProperty->setAccessible(true);
        $idProperty->setValue($checklist, 1);
        $repo = $this->createMock(\App\Repository\ChecklistRepository::class);
        $repo->expects($this->once())
            ->method('find')
            ->with(1)
            ->willReturn($checklist);

        $linkSenderService = $this->createMock(\App\Service\LinkSenderService::class);
        $linkSenderService->expects($this->once())
            ->method('sendChecklistLink')
            ->with($checklist, 'Recipient', 'rec@example.com', '123', null, '');

        $apiValidationService = $this->createMock(\App\Service\ApiValidationService::class);
        $apiValidationService->method('validateJson')->willReturn([
            'checklist_id' => 1,
            'recipient_name' => 'Recipient',
            'recipient_email' => 'rec@example.com',
            'mitarbeiter_id' => '123',
        ]);

        $controller = new ApiController($urlGenerator, $parameterBag, $linkSenderService, $apiValidationService);
        $request = new Request([], [], [], [], [], [], json_encode([
            'checklist_id' => 1,
            'recipient_name' => 'Recipient',
            'recipient_email' => 'rec@example.com',
            'mitarbeiter_id' => '123',
        ]));

        $response = $controller->sendLink($request, $repo);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('status', $data);
        $this->assertSame('sent', $data['status']);
        $this->assertArrayHasKey('link', $data);
        $this->assertSame($url, $data['link']);
    }

    public function testSendLinkReturnsConflictOnDuplicate(): void
    {
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $parameterBag = $this->createMock(ParameterBagInterface::class);
        $parameterBag->expects($this->once())
            ->method('get')
            ->willReturnCallback(fn($k) => $k === 'API_TOKEN' ? '' : null);

        $checklist = new \App\Entity\Checklist();
        // Use reflection to set the private ID field
        $reflection = new \ReflectionClass($checklist);
        $idProperty = $reflection->getProperty('id');
        $idProperty->setAccessible(true);
        $idProperty->setValue($checklist, 1);
        $repo = $this->createMock(\App\Repository\ChecklistRepository::class);
        $repo->expects($this->once())
            ->method('find')
            ->with(1)
            ->willReturn($checklist);

        $linkSenderService = $this->createMock(\App\Service\LinkSenderService::class);
        $linkSenderService->expects($this->once())
            ->method('sendChecklistLink')
            ->willThrowException(new \RuntimeException('Already exists'));

        $apiValidationService = $this->createMock(\App\Service\ApiValidationService::class);
        $apiValidationService->method('validateJson')->willReturn([
            'checklist_id' => 1,
            'recipient_name' => 'Recipient',
            'recipient_email' => 'rec@example.com',
            'mitarbeiter_id' => '123',
        ]);

        $controller = new ApiController($urlGenerator, $parameterBag, $linkSenderService, $apiValidationService);
        $request = new Request([], [], [], [], [], [], json_encode([
            'checklist_id' => 1,
            'recipient_name' => 'Recipient',
            'recipient_email' => 'rec@example.com',
            'mitarbeiter_id' => '123',
        ]));

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

        $repo = $this->createMock(\App\Repository\ChecklistRepository::class);
        $linkSenderService = $this->createMock(\App\Service\LinkSenderService::class);
        $apiValidationService = $this->createMock(\App\Service\ApiValidationService::class);
        $apiValidationService->method('validateJson')->willThrowException(
            new \App\Exception\JsonValidationException('Fehlende Parameter')
        );

        $controller = new ApiController($urlGenerator, $parameterBag, $linkSenderService, $apiValidationService);
        $request = new Request([], [], [], [], [], [], json_encode([]));

        $response = $controller->sendLink($request, $repo);
        $this->assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    public function testJsonValidationExceptionHandling(): void
    {
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $parameterBag = $this->createMock(ParameterBagInterface::class);
        $parameterBag->method('get')->willReturnCallback(fn($k) => $k === 'API_TOKEN' ? '' : null);
        $linkSenderService = $this->createMock(\App\Service\LinkSenderService::class);
        $apiValidationService = $this->createMock(\App\Service\ApiValidationService::class);
        $apiValidationService->method('validateJson')
            ->will($this->returnCallback(function($request, $required) {
                $json = $request->getContent();
                if ($json === '{invalid json') {
                    throw new \App\Exception\JsonValidationException('Ungültiges JSON');
                }
                return [
                    'stückliste_id' => 1,
                    'mitarbeiter_name' => 'Test',
                    'mitarbeiter_id' => 'test-123',
                    'email_empfänger' => 'test@example.com'
                ];
            }));

        $controller = new ApiController($urlGenerator, $parameterBag, $linkSenderService, $apiValidationService);
        $request = new Request([], [], [], [], [], [], '{invalid json');
        
        $response = $controller->generateLink($request);
        $this->assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }
}
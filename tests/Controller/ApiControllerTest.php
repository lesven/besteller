<?php
namespace App\Tests\Controller;

use App\Controller\ApiController;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

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

        $request = new Request([], [], [], [], [], [], json_encode([
            'stückliste_id' => 123,
            'mitarbeiter_name' => 'Max Muster',
            'mitarbeiter_id' => 'abc-123',
            'email_empfänger' => 'chef@example.com',
        ]));

        $controller = new ApiController($urlGenerator);
        $response = $controller->generateLink($request);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertSame($url, $data['link']);
    }

    public function testGenerateLinkRequiresParameters(): void
    {
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $controller = new ApiController($urlGenerator);
        $request = new Request([], [], [], [], [], [], json_encode(['foo' => 'bar']));

        $response = $controller->generateLink($request);
        $this->assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    public function testGenerateLinkChecksBearerToken(): void
    {
        $_ENV['API_TOKEN'] = 'secret';
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $controller = new ApiController($urlGenerator);
        $request = new Request([], [], [], [], [], [], json_encode([
            'stückliste_id' => 1,
            'mitarbeiter_name' => 'A',
            'mitarbeiter_id' => 'B',
            'email_empfänger' => 'a@example.com',
        ]));
        $request->headers->set('Authorization', 'Bearer wrong');

        $response = $controller->generateLink($request);
        $this->assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
        unset($_ENV['API_TOKEN']);
    }
}

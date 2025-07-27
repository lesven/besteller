<?php
namespace App\Tests\Controller;

use App\Controller\SecurityController;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class SecurityControllerTest extends TestCase
{
    public function testLoginRedirectsWhenUserLoggedIn(): void
    {
        $authUtils = $this->createMock(AuthenticationUtils::class);
        $request = new Request();

        $controller = $this->getMockBuilder(SecurityController::class)
            ->onlyMethods(['getUser', 'redirectToRoute'])
            ->disableOriginalConstructor()
            ->getMock();

        $controller->expects($this->once())
            ->method('getUser')
            ->willReturn($this->createMock(UserInterface::class));

        $response = new \Symfony\Component\HttpFoundation\RedirectResponse('/admin');
        $controller->expects($this->once())
            ->method('redirectToRoute')
            ->with('admin_dashboard')
            ->willReturn($response);

        $result = $controller->login($authUtils, $request);
        $this->assertSame($response, $result);
    }

    public function testLoginRendersForm(): void
    {
        $authUtils = $this->createMock(AuthenticationUtils::class);
        $authUtils->method('getLastAuthenticationError')->willReturn(null);
        $authUtils->method('getLastUsername')->willReturn('alice@example.com');
        $request = new Request();

        $controller = $this->getMockBuilder(SecurityController::class)
            ->onlyMethods(['getUser', 'render', 'getParameter'])
            ->disableOriginalConstructor()
            ->getMock();

        $controller->method('getUser')->willReturn(null);
        $controller->method('getParameter')->with('kernel.environment')->willReturn('prod');

        $response = new Response('login');
        $controller->expects($this->once())
            ->method('render')
            ->with('security/login.html.twig', [
                'last_username' => 'alice@example.com',
                'error' => null,
            ])
            ->willReturn($response);

        $result = $controller->login($authUtils, $request);
        $this->assertSame($response, $result);
    }

    public function testLoginAddsFlashOnCsrfErrorInDev(): void
    {
        $error = new AuthenticationException('Invalid CSRF token.');
        $authUtils = $this->createMock(AuthenticationUtils::class);
        $authUtils->method('getLastAuthenticationError')->willReturn($error);
        $authUtils->method('getLastUsername')->willReturn('alice@example.com');
        $request = new Request();

        $controller = $this->getMockBuilder(SecurityController::class)
            ->onlyMethods(['getUser', 'render', 'getParameter', 'addFlash'])
            ->disableOriginalConstructor()
            ->getMock();

        $controller->method('getUser')->willReturn(null);
        $controller->method('getParameter')->with('kernel.environment')->willReturn('dev');
        $controller->expects($this->once())
            ->method('addFlash')
            ->with('info', 'CSRF-Token-Fehler erkannt. Bitte versuchen Sie es erneut.');

        $response = new Response('login');
        $controller->expects($this->once())
            ->method('render')
            ->with('security/login.html.twig', [
                'last_username' => 'alice@example.com',
                'error' => $error,
            ])
            ->willReturn($response);

        $result = $controller->login($authUtils, $request);
        $this->assertSame($response, $result);
    }
}

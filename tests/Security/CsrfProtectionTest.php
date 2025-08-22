<?php

namespace App\Tests\Security;

use App\Controller\Admin\ChecklistLinkController;
use App\Controller\Admin\ChecklistController as AdminChecklistController;
use App\Controller\Admin\UserController;
use App\Controller\Admin\GroupController;
use App\Controller\Admin\EmailSettingsController;
use App\Entity\Checklist;
use App\Entity\User;
use App\Entity\ChecklistGroup;
use App\Entity\EmailSettings;
use App\Service\LinkSenderService;
use App\Service\ChecklistDuplicationService;
use App\Service\CsrfDeletionHelper;
use App\Service\EmailService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

class CsrfProtectionTest extends TestCase
{
    public function testChecklistLinkControllerValidatesCsrfToken(): void
    {
        $linkSender = $this->createMock(LinkSenderService::class);
        
        $controller = $this->getMockBuilder(ChecklistLinkController::class)
            ->setConstructorArgs([$linkSender])
            ->onlyMethods(['isCsrfTokenValid', 'addFlash', 'redirectToRoute'])
            ->getMock();

        $checklist = $this->createMock(Checklist::class);
        $checklist->method('getId')->willReturn(1);

        // Test with invalid CSRF token
        $controller->expects($this->once())
            ->method('isCsrfTokenValid')
            ->with('send-link1', 'invalid_token')
            ->willReturn(false);

        $controller->expects($this->once())
            ->method('addFlash')
            ->with('error', $this->stringContains('Ungültiges'));

        $redirect = $this->createMock(RedirectResponse::class);
        $controller->expects($this->once())
            ->method('redirectToRoute')
            ->with('admin_checklists')
            ->willReturn($redirect);

        $request = Request::create('/admin/checklists/1/send-link', 'POST', [
            '_token' => 'invalid_token',
            'recipient_name' => 'Test',
            'recipient_email' => 'test@example.com',
            'mitarbeiter_id' => 'EMP-123',
            'intro' => 'Test intro'
        ]);

        $result = $controller->sendLink($request, $checklist);
        $this->assertSame($redirect, $result);
    }

    public function testChecklistLinkControllerAcceptsValidCsrfToken(): void
    {
        $linkSender = $this->createMock(LinkSenderService::class);
        
        $controller = $this->getMockBuilder(ChecklistLinkController::class)
            ->setConstructorArgs([$linkSender])
            ->onlyMethods(['isCsrfTokenValid', 'addFlash', 'redirectToRoute'])
            ->getMock();

        $checklist = $this->createMock(Checklist::class);
        $checklist->method('getId')->willReturn(2);

        // Test with valid CSRF token
        $controller->expects($this->once())
            ->method('isCsrfTokenValid')
            ->with('send-link2', 'valid_token')
            ->willReturn(true);

        $linkSender->expects($this->once())
            ->method('sendChecklistLink')
            ->with($checklist, 'Test', 'test@example.com', 'EMP-123', null, 'Test intro');

        $controller->expects($this->once())
            ->method('addFlash')
            ->with('success', $this->stringContains('erfolgreich'));

        $redirect = $this->createMock(RedirectResponse::class);
        $controller->expects($this->once())
            ->method('redirectToRoute')
            ->with('admin_checklists')
            ->willReturn($redirect);

        $request = Request::create('/admin/checklists/2/send-link', 'POST', [
            '_token' => 'valid_token',
            'recipient_name' => 'Test',
            'recipient_email' => 'test@example.com',
            'mitarbeiter_id' => 'EMP-123',
            'intro' => 'Test intro'
        ]);

        $result = $controller->sendLink($request, $checklist);
        $this->assertSame($redirect, $result);
    }

    public function testChecklistLinkControllerSkipsCsrfWhenNoTokenProvided(): void
    {
        $linkSender = $this->createMock(LinkSenderService::class);
        
        $controller = $this->getMockBuilder(ChecklistLinkController::class)
            ->setConstructorArgs([$linkSender])
            ->onlyMethods(['isCsrfTokenValid', 'addFlash', 'redirectToRoute'])
            ->getMock();

        $checklist = $this->createMock(Checklist::class);
        $checklist->method('getId')->willReturn(3);

        // When no token is provided, CSRF validation should be skipped
        $controller->expects($this->never())->method('isCsrfTokenValid');

        $linkSender->expects($this->once())
            ->method('sendChecklistLink')
            ->with($checklist, 'Test', 'test@example.com', 'EMP-123', null, '');

        $controller->expects($this->once())
            ->method('addFlash')
            ->with('success', $this->stringContains('erfolgreich'));

        $redirect = $this->createMock(RedirectResponse::class);
        $controller->expects($this->once())
            ->method('redirectToRoute')
            ->with('admin_checklists')
            ->willReturn($redirect);

        $request = Request::create('/admin/checklists/3/send-link', 'POST', [
            'recipient_name' => 'Test',
            'recipient_email' => 'test@example.com',
            'mitarbeiter_id' => 'EMP-123',
            'intro' => ''
        ]);

        $result = $controller->sendLink($request, $checklist);
        $this->assertSame($redirect, $result);
    }

    /**
     * @dataProvider csrfTokenVariationsProvider
     */
    public function testCsrfTokenHandlesVariousFormats(mixed $tokenValue, bool $shouldValidate): void
    {
        $linkSender = $this->createMock(LinkSenderService::class);
        
        $controller = $this->getMockBuilder(ChecklistLinkController::class)
            ->setConstructorArgs([$linkSender])
            ->onlyMethods(['isCsrfTokenValid', 'addFlash', 'redirectToRoute'])
            ->getMock();

        $checklist = $this->createMock(Checklist::class);
        $checklist->method('getId')->willReturn(4);

        if ($shouldValidate) {
            $controller->expects($this->once())
                ->method('isCsrfTokenValid')
                ->with('send-link4', $tokenValue)
                ->willReturn(true);

            $linkSender->expects($this->once())->method('sendChecklistLink');
            $controller->expects($this->once())
                ->method('addFlash')
                ->with('success', $this->anything());
            
            $redirect = $this->createMock(RedirectResponse::class);
            $controller->expects($this->once())
                ->method('redirectToRoute')
                ->willReturn($redirect);

            $request = Request::create('/admin/checklists/4/send-link', 'POST', [
                '_token' => $tokenValue,
                'recipient_name' => 'Test',
                'recipient_email' => 'test@example.com',
                'mitarbeiter_id' => 'EMP-123',
                'intro' => ''
            ]);

            $result = $controller->sendLink($request, $checklist);
            $this->assertSame($redirect, $result);
        } else {
            $controller->expects($this->never())->method('isCsrfTokenValid');
            
            // For non-scalar values, expect an exception from Symfony
            if (is_array($tokenValue) || is_object($tokenValue)) {
                $this->expectException(\Symfony\Component\HttpFoundation\Exception\BadRequestException::class);
                // No other expectations since exception will be thrown
            } else {
                $linkSender->expects($this->once())->method('sendChecklistLink');
                $redirect = $this->createMock(RedirectResponse::class);
                $controller->expects($this->once())
                    ->method('redirectToRoute')
                    ->willReturn($redirect);
            }

            $request = Request::create('/admin/checklists/4/send-link', 'POST', [
                '_token' => $tokenValue,
                'recipient_name' => 'Test',
                'recipient_email' => 'test@example.com',
                'mitarbeiter_id' => 'EMP-123',
                'intro' => ''
            ]);

            $result = $controller->sendLink($request, $checklist);
            if (!is_array($tokenValue) && !is_object($tokenValue)) {
                $this->assertSame($redirect, $result);
            }
        }
    }

    public function testCsrfDeletionHelperTraitExists(): void
    {
        // Test that the CsrfDeletionHelper trait exists and can be used
        $this->assertTrue(trait_exists(CsrfDeletionHelper::class));
        
        // Test that the trait has the expected method
        $reflection = new \ReflectionClass(CsrfDeletionHelper::class);
        $this->assertTrue($reflection->hasMethod('handleCsrfDeletion'));
    }

    public function testCsrfDeletionHelperTraitMethod(): void
    {
        // Test the trait method signature
        $reflection = new \ReflectionClass(CsrfDeletionHelper::class);
        $method = $reflection->getMethod('handleCsrfDeletion');
        
        $this->assertTrue($method->isPrivate());
        $this->assertCount(4, $method->getParameters());
    }

    /**
     * @dataProvider maliciousCsrfTokenProvider
     */
    public function testCsrfTokenRejectsMaliciousInput(string $maliciousToken): void
    {
        $linkSender = $this->createMock(LinkSenderService::class);
        
        $controller = $this->getMockBuilder(ChecklistLinkController::class)
            ->setConstructorArgs([$linkSender])
            ->onlyMethods(['isCsrfTokenValid', 'addFlash', 'redirectToRoute'])
            ->getMock();

        $checklist = $this->createMock(Checklist::class);
        $checklist->method('getId')->willReturn(5);

        $controller->expects($this->once())
            ->method('isCsrfTokenValid')
            ->with('send-link5', $maliciousToken)
            ->willReturn(false);

        $controller->expects($this->once())
            ->method('addFlash')
            ->with('error', $this->stringContains('Ungültiges'));

        $redirect = $this->createMock(RedirectResponse::class);
        $controller->expects($this->once())
            ->method('redirectToRoute')
            ->willReturn($redirect);

        $request = Request::create('/admin/checklists/5/send-link', 'POST', [
            '_token' => $maliciousToken,
            'recipient_name' => 'Test',
            'recipient_email' => 'test@example.com',
            'mitarbeiter_id' => 'EMP-123'
        ]);

        $result = $controller->sendLink($request, $checklist);
        $this->assertSame($redirect, $result);
    }

    public function testCsrfTokenWithSpecialCharacters(): void
    {
        $linkSender = $this->createMock(LinkSenderService::class);
        
        $controller = $this->getMockBuilder(ChecklistLinkController::class)
            ->setConstructorArgs([$linkSender])
            ->onlyMethods(['isCsrfTokenValid', 'addFlash', 'redirectToRoute'])
            ->getMock();

        $checklist = $this->createMock(Checklist::class);
        $checklist->method('getId')->willReturn(6);

        $tokenWithSpecialChars = 'abc-123_DEF.456+789=';
        
        $controller->expects($this->once())
            ->method('isCsrfTokenValid')
            ->with('send-link6', $tokenWithSpecialChars)
            ->willReturn(true);

        $linkSender->expects($this->once())->method('sendChecklistLink');
        $controller->expects($this->once())
            ->method('addFlash')
            ->with('success', $this->anything());

        $redirect = $this->createMock(RedirectResponse::class);
        $controller->expects($this->once())
            ->method('redirectToRoute')
            ->willReturn($redirect);

        $request = Request::create('/admin/checklists/6/send-link', 'POST', [
            '_token' => $tokenWithSpecialChars,
            'recipient_name' => 'Test',
            'recipient_email' => 'test@example.com',
            'mitarbeiter_id' => 'EMP-123'
        ]);

        $result = $controller->sendLink($request, $checklist);
        $this->assertSame($redirect, $result);
    }

    public static function csrfTokenVariationsProvider(): array
    {
        return [
            'String token' => ['valid_token_string', true],
            'Empty string' => ['', true],
            'Null token' => [null, false],
            'Integer token' => [123, false],
            'Array token' => [['token'], false],
            'Boolean token' => [true, false],
            'Object token' => [new \stdClass(), false],
        ];
    }

    public static function maliciousCsrfTokenProvider(): array
    {
        return [
            'XSS in token' => ['<script>alert("xss")</script>'],
            'SQL injection' => ["'; DROP TABLE csrf_tokens; --"],
            'Path traversal' => ['../../../etc/passwd'],
            'Command injection' => ['$(rm -rf /)'],
            'Unicode attack' => ['＜script＞alert(1)＜/script＞'],
            'Null byte injection' => ["valid_token\0malicious"],
            'CRLF injection' => ["token\r\nX-Forwarded-For: evil.com"],
            'Very long token' => [str_repeat('A', 10000)],
            'Binary data' => ["\x00\x01\x02\x03\x04\x05"],
            'Format string' => ['%s%s%s%s%s'],
        ];
    }
}
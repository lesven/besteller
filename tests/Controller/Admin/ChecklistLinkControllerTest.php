<?php

namespace App\Tests\Controller\Admin;

use App\Controller\Admin\ChecklistLinkController;
use App\Entity\Checklist;
use App\Service\LinkSenderService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class ChecklistLinkControllerTest extends TestCase
{
    public function testGetRendersForm(): void
    {
        $linkSender = $this->createMock(LinkSenderService::class);

        $controller = $this->getMockBuilder(ChecklistLinkController::class)
            ->setConstructorArgs([$linkSender])
            ->onlyMethods(['render'])
            ->getMock();

        $checklist = $this->createMock(Checklist::class);

        $expected = new Response('form');
        $controller->expects($this->once())
            ->method('render')
            ->with('admin/checklist/send_link.html.twig', ['checklist' => $checklist])
            ->willReturn($expected);

        $request = Request::create('/admin/checklists/1/send-link', 'GET');

        $result = $controller->sendLink($request, $checklist);

        $this->assertSame($expected, $result);
    }

    public function testPostSuccessfulSendsAndRedirects(): void
    {
        $linkSender = $this->createMock(LinkSenderService::class);

        $controller = $this->getMockBuilder(ChecklistLinkController::class)
            ->setConstructorArgs([$linkSender])
            ->onlyMethods(['addFlash', 'redirectToRoute', 'render'])
            ->getMock();

        $checklist = $this->createMock(Checklist::class);
        $checklist->method('getId')->willReturn(1);

        $linkSender->expects($this->once())
            ->method('sendChecklistLink')
            ->with($checklist, 'Empfänger', 'to@example.test', 'M-1', null, 'Intro');

        $controller->expects($this->once())->method('addFlash')->with('success', $this->stringContains('erfolgreich'));

    $redirect = $this->createMock(\Symfony\Component\HttpFoundation\RedirectResponse::class);
        $controller->expects($this->once())->method('redirectToRoute')->with('admin_checklists')->willReturn($redirect);

        $request = Request::create('/admin/checklists/1/send-link', 'POST', [
            'recipient_name' => 'Empfänger',
            'recipient_email' => 'to@example.test',
            'mitarbeiter_id' => 'M-1',
            'person_name' => '',
            'intro' => 'Intro',
        ]);

        $result = $controller->sendLink($request, $checklist);

        $this->assertSame($redirect, $result);
    }

    public function testPostInvalidCsrfRedirectsWithErrorFlash(): void
    {
        $linkSender = $this->createMock(LinkSenderService::class);

        $controller = $this->getMockBuilder(ChecklistLinkController::class)
            ->setConstructorArgs([$linkSender])
            ->onlyMethods(['isCsrfTokenValid', 'addFlash', 'redirectToRoute', 'render'])
            ->getMock();

        $checklist = $this->createMock(Checklist::class);
        $checklist->method('getId')->willReturn(7);

        $controller->expects($this->once())->method('isCsrfTokenValid')->with('send-link7', 'badtoken')->willReturn(false);
        $controller->expects($this->once())->method('addFlash')->with('error', $this->stringContains('Ungültiges'));
    $redirect = $this->createMock(\Symfony\Component\HttpFoundation\RedirectResponse::class);
        $controller->expects($this->once())->method('redirectToRoute')->with('admin_checklists')->willReturn($redirect);

        $request = Request::create('/admin/checklists/7/send-link', 'POST', ['_token' => 'badtoken']);

        $result = $controller->sendLink($request, $checklist);

        $this->assertSame($redirect, $result);
    }

    public function testPostServiceInvalidArgumentExceptionAddsFlashAndRenders(): void
    {
        $linkSender = $this->createMock(LinkSenderService::class);

        $controller = $this->getMockBuilder(ChecklistLinkController::class)
            ->setConstructorArgs([$linkSender])
            ->onlyMethods(['addFlash', 'redirectToRoute', 'render'])
            ->getMock();

        $checklist = $this->createMock(Checklist::class);
        $checklist->method('getId')->willReturn(2);

        $linkSender->method('sendChecklistLink')->willThrowException(new \InvalidArgumentException('Fehler')); 

        $controller->expects($this->once())->method('addFlash')->with('error', 'Fehler');

        $expected = new Response('form');
        $controller->expects($this->once())->method('render')->with('admin/checklist/send_link.html.twig', ['checklist' => $checklist])->willReturn($expected);

        $request = Request::create('/admin/checklists/2/send-link', 'POST', [
            'recipient_name' => 'A',
            'recipient_email' => 'b@c',
            'mitarbeiter_id' => 'X',
            'person_name' => '',
            'intro' => '',
        ]);

        $result = $controller->sendLink($request, $checklist);

        $this->assertSame($expected, $result);
    }

    public function testPostServiceRuntimeExceptionAddsFlashAndRenders(): void
    {
        $linkSender = $this->createMock(LinkSenderService::class);

        $controller = $this->getMockBuilder(ChecklistLinkController::class)
            ->setConstructorArgs([$linkSender])
            ->onlyMethods(['addFlash', 'redirectToRoute', 'render'])
            ->getMock();

        $checklist = $this->createMock(Checklist::class);
        $checklist->method('getId')->willReturn(3);

        $linkSender->method('sendChecklistLink')->willThrowException(new \RuntimeException('Service down'));

        $controller->expects($this->once())->method('addFlash')->with('error', 'Service down');

        $expected = new Response('form');
        $controller->expects($this->once())->method('render')->with('admin/checklist/send_link.html.twig', ['checklist' => $checklist])->willReturn($expected);

        $request = Request::create('/admin/checklists/3/send-link', 'POST', [
            'recipient_name' => 'A',
            'recipient_email' => 'b@c',
            'mitarbeiter_id' => 'X',
            'person_name' => '',
            'intro' => '',
        ]);

        $result = $controller->sendLink($request, $checklist);

        $this->assertSame($expected, $result);
    }
}

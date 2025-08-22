<?php

namespace App\Tests\Controller\Admin;

use App\Controller\Admin\ChecklistTemplateController;
use App\Entity\Checklist;
use App\Service\EmailService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;

class ChecklistTemplateControllerTest extends TestCase
{
    public function testDownloadEmailTemplateReturnsAttachment(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $emailService = $this->createMock(EmailService::class);

        $controller = new ChecklistTemplateController($entityManager, $emailService);

        $checklist = $this->createMock(Checklist::class);
        $checklist->method('getEmailTemplate')->willReturn(null);
        $checklist->method('getId')->willReturn(42);

        $emailService->expects($this->once())->method('getDefaultTemplate')->willReturn('<p>default</p>');

        $response = $controller->downloadEmailTemplate($checklist);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertStringContainsString('<p>default</p>', $response->getContent());
        $this->assertSame('text/html', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('email-template-42.html', $response->headers->get('Content-Disposition'));
    }

    public function testResetEmailTemplateWithValidCsrfFlushesAndRedirects(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $emailService = $this->createMock(EmailService::class);

        $controller = $this->getMockBuilder(ChecklistTemplateController::class)
            ->setConstructorArgs([$entityManager, $emailService])
            ->onlyMethods(['isCsrfTokenValid', 'addFlash', 'redirectToRoute'])
            ->getMock();

        $checklist = $this->createMock(Checklist::class);
        $checklist->method('getId')->willReturn(7);

        $entityManager->expects($this->once())->method('flush');

        $controller->expects($this->once())->method('isCsrfTokenValid')->with('reset_template7', 'tok')->willReturn(true);
        $controller->expects($this->once())->method('addFlash')->with('success', $this->stringContains('zurückgesetzt'));

        $redirect = $this->createMock(RedirectResponse::class);
        $controller->expects($this->once())->method('redirectToRoute')->with('admin_checklist_email_template', ['id' => 7])->willReturn($redirect);

        $request = Request::create('/admin/checklists/7/email-template/reset', 'POST', ['_token' => 'tok']);

        $result = $controller->resetEmailTemplate($request, $checklist);

        $this->assertSame($redirect, $result);
    }

    public function testResetEmailTemplateWithInvalidCsrfDoesNotFlushButRedirects(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $emailService = $this->createMock(EmailService::class);

        $controller = $this->getMockBuilder(ChecklistTemplateController::class)
            ->setConstructorArgs([$entityManager, $emailService])
            ->onlyMethods(['isCsrfTokenValid', 'addFlash', 'redirectToRoute'])
            ->getMock();

        $checklist = $this->createMock(Checklist::class);
        $checklist->method('getId')->willReturn(8);

        $entityManager->expects($this->never())->method('flush');
        $controller->expects($this->once())->method('isCsrfTokenValid')->with('reset_template8', 'bad')->willReturn(false);
        $controller->expects($this->never())->method('addFlash');

        $redirect = $this->createMock(RedirectResponse::class);
        $controller->expects($this->once())->method('redirectToRoute')->with('admin_checklist_email_template', ['id' => 8])->willReturn($redirect);

        $request = Request::create('/admin/checklists/8/email-template/reset', 'POST', ['_token' => 'bad']);

        $result = $controller->resetEmailTemplate($request, $checklist);

        $this->assertSame($redirect, $result);
    }
}

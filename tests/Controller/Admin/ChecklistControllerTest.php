<?php

namespace App\Tests\Controller\Admin;

use App\Controller\Admin\ChecklistController;
use App\Entity\Checklist;
use App\Repository\ChecklistRepository;
use App\Service\ChecklistDuplicationService;
use App\Service\EmailService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;

class ChecklistControllerTest extends TestCase
{
    public function testIndexRendersChecklistList(): void
    {
        $checklists = [new Checklist()];

        $checklistRepo = $this->createMock(ChecklistRepository::class);
        $checklistRepo->method('findAll')->willReturn($checklists);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $emailService = $this->createMock(EmailService::class);
        $duplicationService = $this->createMock(ChecklistDuplicationService::class);

        $controller = $this->getMockBuilder(ChecklistController::class)
            ->setConstructorArgs([$entityManager, $checklistRepo, $emailService, $duplicationService])
            ->onlyMethods(['render'])
            ->getMock();

        $expectedResponse = new Response('ok');

        $controller->expects($this->once())
            ->method('render')
            ->with(
                'admin/checklist/index.html.twig',
                $this->callback(function ($params) use ($checklists) {
                    return isset($params['checklists']) && $params['checklists'] === $checklists;
                })
            )
            ->willReturn($expectedResponse);

        $result = $controller->index();

        $this->assertSame($expectedResponse, $result);
    }

    public function testNewPostCreatesChecklistAndRedirects(): void
    {
        $checklistRepo = $this->createMock(ChecklistRepository::class);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $emailService = $this->createMock(EmailService::class);
        $duplicationService = $this->createMock(ChecklistDuplicationService::class);

        $request = Request::create('/admin/checklist/new', 'POST', [
            'title' => 'Meine Checkliste',
            'target_email' => 'target@example.com',
            'reply_email' => '',
            'email_template' => 'tpl',
        ]);

        $controller = $this->getMockBuilder(ChecklistController::class)
            ->setConstructorArgs([$entityManager, $checklistRepo, $emailService, $duplicationService])
            ->onlyMethods(['addFlash', 'redirectToRoute', 'render'])
            ->getMock();

        $entityManager->expects($this->once())->method('persist')->with($this->isInstanceOf(Checklist::class));
        $entityManager->expects($this->once())->method('flush');

        $controller->expects($this->once())->method('addFlash')->with('success', $this->stringContains('erfolgreich'));

    $redirectResponse = new RedirectResponse('/admin/checklists', 302);
        $controller->expects($this->once())->method('redirectToRoute')->with('admin_checklists')->willReturn($redirectResponse);

        $result = $controller->new($request);

        $this->assertSame($redirectResponse, $result);
    }

    public function testDuplicateCallsDuplicationServiceAndRedirects(): void
    {
        $checklistRepo = $this->createMock(ChecklistRepository::class);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $emailService = $this->createMock(EmailService::class);

        $duplicationService = $this->createMock(ChecklistDuplicationService::class);

        $controller = $this->getMockBuilder(ChecklistController::class)
            ->setConstructorArgs([$entityManager, $checklistRepo, $emailService, $duplicationService])
            ->onlyMethods(['addFlash', 'redirectToRoute'])
            ->getMock();

        $checklist = new Checklist();

        $duplicationService->expects($this->once())->method('duplicate')->with($checklist);
        $controller->expects($this->once())->method('addFlash')->with('success', $this->stringContains('dupliziert'));

    $redirectResponse = new RedirectResponse('/admin/checklists', 302);
        $controller->expects($this->once())->method('redirectToRoute')->with('admin_checklists')->willReturn($redirectResponse);

        $result = $controller->duplicate($checklist);

        $this->assertSame($redirectResponse, $result);
    }

    public function testDownloadConfirmationTemplateReturnsAttachment(): void
    {
        $checklistRepo = $this->createMock(ChecklistRepository::class);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $emailService = $this->createMock(EmailService::class);
        $duplicationService = $this->createMock(ChecklistDuplicationService::class);

        $controller = new ChecklistController($entityManager, $checklistRepo, $emailService, $duplicationService);

        $checklist = $this->createMock(Checklist::class);
        $checklist->method('getConfirmationEmailTemplate')->willReturn(null);
        $checklist->method('getId')->willReturn(42);

        $emailService->method('getConfirmationTemplate')->willReturn('<p>ok</p>');

        $response = $controller->downloadConfirmationTemplate($checklist);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertStringContainsString('<p>ok</p>', $response->getContent());
        $this->assertStringContainsString('confirmation-template-42.html', $response->headers->get('Content-Disposition'));
    }
}

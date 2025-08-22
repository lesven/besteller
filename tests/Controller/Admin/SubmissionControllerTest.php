<?php

namespace App\Tests\Controller\Admin;

use App\Controller\Admin\SubmissionController;
use App\Entity\Submission;
use App\Entity\Checklist;
use App\Repository\ChecklistRepository;
use App\Repository\SubmissionRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class SubmissionControllerTest extends TestCase
{
    public function testIndexRendersWithChecklists(): void
    {
        $checklists = [new Checklist(), new Checklist()];

        $em = $this->createMock(EntityManagerInterface::class);
        $checklistRepo = $this->createMock(ChecklistRepository::class);
        $submissionRepo = $this->createMock(SubmissionRepository::class);

        $checklistRepo->expects($this->once())
            ->method('findAll')
            ->willReturn($checklists);

        $controller = $this->getMockBuilder(SubmissionController::class)
            ->setConstructorArgs([$em, $checklistRepo, $submissionRepo])
            ->onlyMethods(['render'])
            ->getMock();

        $controller->expects($this->once())
            ->method('render')
            ->with('admin/submission/index.html.twig', $this->callback(function ($vars) use ($checklists) {
                return isset($vars['checklists']) && $vars['checklists'] === $checklists;
            }))
            ->willReturn(new Response('ok'));

        $response = $controller->index();

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals('ok', $response->getContent());
    }

    public function testByChecklistRendersWithSubmissions(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $checklistRepo = $this->createMock(ChecklistRepository::class);
        $submissionRepo = $this->createMock(SubmissionRepository::class);

        $checklist = $this->createMock(Checklist::class);
        $submissions = [ $this->createMock(Submission::class) ];

        $checklistRepo->expects($this->once())
            ->method('find')
            ->with(5)
            ->willReturn($checklist);

        $submissionRepo->expects($this->once())
            ->method('findByChecklist')
            ->with($checklist, 'term')
            ->willReturn($submissions);

        $controller = $this->getMockBuilder(SubmissionController::class)
            ->setConstructorArgs([$em, $checklistRepo, $submissionRepo])
            ->onlyMethods(['render'])
            ->getMock();

        $controller->expects($this->once())
            ->method('render')
            ->with('admin/submission/by_checklist.html.twig', $this->callback(function ($vars) use ($checklist, $submissions) {
                return $vars['checklist'] === $checklist && $vars['submissions'] === $submissions && $vars['search'] === 'term';
            }))
            ->willReturn(new Response('ok'));

        $request = Request::create('/admin/submissions/5', 'GET', ['q' => 'term']);

        $response = $controller->byChecklist($request, 5);

        $this->assertInstanceOf(Response::class, $response);
    }

    public function testByChecklistNotFoundThrows(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $checklistRepo = $this->createMock(ChecklistRepository::class);
        $submissionRepo = $this->createMock(SubmissionRepository::class);

        $checklistRepo->expects($this->once())
            ->method('find')
            ->with(123)
            ->willReturn(null);

        $controller = new SubmissionController($em, $checklistRepo, $submissionRepo);

        $this->expectException(NotFoundHttpException::class);

        $request = Request::create('/admin/submissions/123', 'GET');
        $controller->byChecklist($request, 123);
    }

    public function testViewHtmlReturnsHtmlResponse(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $checklistRepo = $this->createMock(ChecklistRepository::class);
        $submissionRepo = $this->createMock(SubmissionRepository::class);

        $controller = new SubmissionController($em, $checklistRepo, $submissionRepo);

        $submission = $this->createMock(Submission::class);
        $submission->method('getGeneratedEmail')->willReturn('<b>hello</b>');

        $response = $controller->viewHtml($submission);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals('<b>hello</b>', $response->getContent());
        $this->assertEquals('text/html', $response->headers->get('Content-Type'));
    }

    public function testDeleteRemovesSubmissionAndRedirects(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $checklistRepo = $this->createMock(ChecklistRepository::class);
        $submissionRepo = $this->createMock(SubmissionRepository::class);

        $submission = $this->createMock(Submission::class);
        $submission->method('getId')->willReturn(5);

        $checklist = $this->createMock(Checklist::class);
        $checklist->method('getId')->willReturn(99);
        $submission->method('getChecklist')->willReturn($checklist);

        $em->expects($this->once())
            ->method('remove')
            ->with($submission);
        $em->expects($this->once())
            ->method('flush');

        $controller = $this->getMockBuilder(SubmissionController::class)
            ->setConstructorArgs([$em, $checklistRepo, $submissionRepo])
            ->onlyMethods(['isCsrfTokenValid', 'addFlash', 'redirectToRoute'])
            ->getMock();

        $controller->method('isCsrfTokenValid')->willReturn(true);

        $controller->expects($this->once())
            ->method('addFlash')
            ->with('success', $this->stringContains('Einsendung'));

        $controller->expects($this->once())
            ->method('redirectToRoute')
            ->with('admin_submissions_checklist', ['checklistId' => 99])
            ->willReturn(new RedirectResponse('/admin/submissions/99'));

        $request = Request::create('/admin/submission/delete', 'POST', ['_token' => 'tok']);

        $response = $controller->delete($request, $submission);

        $this->assertInstanceOf(RedirectResponse::class, $response);
    }

    public function testDeleteMissingChecklistThrows(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $checklistRepo = $this->createMock(ChecklistRepository::class);
        $submissionRepo = $this->createMock(SubmissionRepository::class);

        $submission = $this->createMock(Submission::class);
        $submission->method('getId')->willReturn(77);
        $submission->method('getChecklist')->willReturn(null);

        $controller = new SubmissionController($em, $checklistRepo, $submissionRepo);

        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('Zugehörige Checkliste für Submission #77 nicht gefunden.');

        $request = Request::create('/admin/submission/delete', 'POST');
        $controller->delete($request, $submission);
    }
}

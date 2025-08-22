<?php

namespace App\Tests\E2E;

use App\Controller\Admin\ChecklistController;
use App\Controller\ChecklistController as PublicChecklistController;
use App\Entity\Checklist;
use App\Entity\User;
use App\Repository\ChecklistRepository;
use App\Repository\SubmissionRepository;
use App\Service\EmailService;
use App\Service\SubmissionService;
use App\Service\SubmissionFactory;
use App\Service\ChecklistDuplicationService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectRepository;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Doctrine\Common\Collections\ArrayCollection;

/**
 * Simplified End-to-End tests that work without WebTestCase
 * These tests focus on controller behavior and security
 */
class SimpleLoginAndChecklistTest extends TestCase
{
    private function createMockEntityManager(): EntityManagerInterface
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        
        $checklistRepo = $this->createMock(ChecklistRepository::class);
        $submissionRepo = $this->createMock(SubmissionRepository::class);
        
        $entityManager->method('getRepository')->willReturnCallback(function ($class) use ($checklistRepo, $submissionRepo) {
            if ($class === Checklist::class) {
                return $checklistRepo;
            }
            if ($class === \App\Entity\Submission::class) {
                return $submissionRepo;
            }
            return $this->createMock(ObjectRepository::class);
        });
        
        return $entityManager;
    }

    private function createMockChecklist(): Checklist
    {
        $checklist = $this->createMock(Checklist::class);
        $checklist->method('getId')->willReturn(1);
        $checklist->method('getTitle')->willReturn('Test Checklist');
        $checklist->method('getTargetEmail')->willReturn('target@test.com');
        $checklist->method('getReplyEmail')->willReturn('reply@test.com');
        return $checklist;
    }

    public function testChecklistControllerCreationWithValidData(): void
    {
        $entityManager = $this->createMockEntityManager();
        $checklistRepo = $this->createMock(ChecklistRepository::class);
        $emailService = $this->createMock(EmailService::class);
        $duplicationService = $this->createMock(ChecklistDuplicationService::class);

        $controller = $this->getMockBuilder(ChecklistController::class)
            ->setConstructorArgs([
                $entityManager,
                $checklistRepo,
                $emailService,
                $duplicationService
            ])
            ->onlyMethods(['render', 'addFlash', 'redirectToRoute'])
            ->getMock();

        $request = new Request();
        $request->setMethod('POST');
        $request->request->set('title', 'Test Stückliste');
        $request->request->set('target_email', 'target@test.com');
        $request->request->set('reply_email', 'reply@test.com');
        $request->request->set('email_template', 'Standard Template');

        $entityManager->expects($this->once())->method('persist');
        $entityManager->expects($this->once())->method('flush');
        
        $controller->expects($this->once())
            ->method('addFlash')
            ->with('success', 'Checkliste wurde erfolgreich erstellt.');

        $controller->expects($this->once())
            ->method('redirectToRoute')
            ->with('admin_checklists')
            ->willReturn(new RedirectResponse('/admin/checklists'));

        $response = $controller->new($request);
        $this->assertInstanceOf(Response::class, $response);
    }

    public function testChecklistCreationWithInvalidEmail(): void
    {
        $entityManager = $this->createMockEntityManager();
        $checklistRepo = $this->createMock(ChecklistRepository::class);
        $emailService = $this->createMock(EmailService::class);
        $duplicationService = $this->createMock(ChecklistDuplicationService::class);

        $controller = $this->getMockBuilder(ChecklistController::class)
            ->setConstructorArgs([
                $entityManager,
                $checklistRepo,
                $emailService,
                $duplicationService
            ])
            ->onlyMethods(['render', 'addFlash', 'redirectToRoute'])
            ->getMock();

        $request = new Request();
        $request->setMethod('POST');
        $request->request->set('title', 'Test Stückliste');
        $request->request->set('target_email', 'target@test.com');
        $request->request->set('reply_email', 'invalid-email-format');

        $controller->expects($this->once())
            ->method('addFlash')
            ->with('error', 'Bitte eine gültige Rückfragen-E-Mail eingeben.');

        $controller->expects($this->once())
            ->method('redirectToRoute')
            ->with('admin_checklist_new')
            ->willReturn(new RedirectResponse('/admin/checklists'));

        $entityManager->expects($this->never())->method('persist');

        $response = $controller->new($request);
        $this->assertInstanceOf(Response::class, $response);
    }

    public function testChecklistEditWithValidData(): void
    {
        $entityManager = $this->createMockEntityManager();
        $checklistRepo = $this->createMock(ChecklistRepository::class);
        $emailService = $this->createMock(EmailService::class);
        $duplicationService = $this->createMock(ChecklistDuplicationService::class);

        $controller = $this->getMockBuilder(ChecklistController::class)
            ->setConstructorArgs([
                $entityManager,
                $checklistRepo,
                $emailService,
                $duplicationService
            ])
            ->onlyMethods(['render', 'addFlash', 'redirectToRoute'])
            ->getMock();

        $checklist = $this->createMockChecklist();
        $checklist->expects($this->once())->method('setTitle')->with('Updated Title');
        $checklist->expects($this->once())->method('setTargetEmail')->with('updated@test.com');

        $request = new Request();
        $request->setMethod('POST');
        $request->request->set('title', 'Updated Title');
        $request->request->set('target_email', 'updated@test.com');
        $request->request->set('reply_email', 'reply@test.com');

        $entityManager->expects($this->once())->method('flush');
        
        $controller->expects($this->once())
            ->method('addFlash')
            ->with('success', 'Checkliste wurde erfolgreich aktualisiert.');

        $controller->expects($this->once())
            ->method('redirectToRoute')
            ->with('admin_checklists')
            ->willReturn(new RedirectResponse('/admin/checklists'));

        $response = $controller->edit($request, $checklist);
        $this->assertInstanceOf(Response::class, $response);
    }

    public function testChecklistDeletion(): void
    {
        $entityManager = $this->createMockEntityManager();
        $checklistRepo = $this->createMock(ChecklistRepository::class);
        $emailService = $this->createMock(EmailService::class);
        $duplicationService = $this->createMock(ChecklistDuplicationService::class);

        $controller = $this->getMockBuilder(ChecklistController::class)
            ->setConstructorArgs([
                $entityManager,
                $checklistRepo,
                $emailService,
                $duplicationService
            ])
            ->onlyMethods(['render', 'addFlash', 'redirectToRoute', 'isCsrfTokenValid'])
            ->getMock();

        $checklist = $this->createMockChecklist();
        $checklist->method('getSubmissions')->willReturn(new ArrayCollection([]));

        $request = new Request();
        $request->setMethod('POST');
        $request->request->set('_token', 'valid_token');

        $controller->method('isCsrfTokenValid')->willReturn(true);
        
        $entityManager->expects($this->once())->method('remove')->with($checklist);
        $entityManager->expects($this->once())->method('flush');

        $controller->expects($this->once())
            ->method('addFlash')
            ->with('success', 'Checkliste wurde erfolgreich gelöscht.');

        $controller->expects($this->once())
            ->method('redirectToRoute')
            ->with('admin_checklists')
            ->willReturn(new RedirectResponse('/admin/checklists'));

        $response = $controller->delete($request, $checklist);
        $this->assertInstanceOf(Response::class, $response);
    }

    public function testEmailTemplateUpdateWithValidContent(): void
    {
        $entityManager = $this->createMockEntityManager();
        $checklistRepo = $this->createMock(ChecklistRepository::class);
        $emailService = $this->createMock(EmailService::class);
        $duplicationService = $this->createMock(ChecklistDuplicationService::class);

        $controller = $this->getMockBuilder(ChecklistController::class)
            ->setConstructorArgs([
                $entityManager,
                $checklistRepo,
                $emailService,
                $duplicationService
            ])
            ->onlyMethods(['render', 'addFlash', 'redirectToRoute'])
            ->getMock();

        $checklist = $this->createMockChecklist();
        $checklist->expects($this->once())
            ->method('setEmailTemplate')
            ->with('New template content');

        $request = new Request();
        $request->setMethod('POST');
        $request->request->set('template_content', 'New template content');

        $entityManager->expects($this->once())->method('flush');

        $controller->expects($this->once())
            ->method('addFlash')
            ->with('success', 'E-Mail-Template wurde erfolgreich aktualisiert.');

        $controller->expects($this->once())
            ->method('redirectToRoute')
            ->willReturn(new RedirectResponse('/admin/checklists'));

        $response = $controller->emailTemplate($request, $checklist);
        $this->assertInstanceOf(Response::class, $response);
    }

    public function testChecklistDuplication(): void
    {
        $entityManager = $this->createMockEntityManager();
        $checklistRepo = $this->createMock(ChecklistRepository::class);
        $emailService = $this->createMock(EmailService::class);
        $duplicationService = $this->createMock(ChecklistDuplicationService::class);

        $controller = $this->getMockBuilder(ChecklistController::class)
            ->setConstructorArgs([
                $entityManager,
                $checklistRepo,
                $emailService,
                $duplicationService
            ])
            ->onlyMethods(['addFlash', 'redirectToRoute'])
            ->getMock();

        $checklist = $this->createMockChecklist();

        $duplicationService->expects($this->once())
            ->method('duplicate')
            ->with($checklist);

        $controller->expects($this->once())
            ->method('addFlash')
            ->with('success', 'Checkliste wurde erfolgreich dupliziert.');

        $controller->expects($this->once())
            ->method('redirectToRoute')
            ->with('admin_checklists')
            ->willReturn(new RedirectResponse('/admin/checklists'));

        $response = $controller->duplicate($checklist);
        $this->assertInstanceOf(Response::class, $response);
    }

    public function testXssPreventionInTemplateContent(): void
    {
        $entityManager = $this->createMockEntityManager();
        $checklistRepo = $this->createMock(ChecklistRepository::class);
        $emailService = $this->createMock(EmailService::class);
        $duplicationService = $this->createMock(ChecklistDuplicationService::class);

        $controller = $this->getMockBuilder(ChecklistController::class)
            ->setConstructorArgs([
                $entityManager,
                $checklistRepo,
                $emailService,
                $duplicationService
            ])
            ->onlyMethods(['render', 'addFlash', 'redirectToRoute'])
            ->getMock();

        $checklist = $this->createMockChecklist();

        // Create a mock uploaded file with malicious content
        $uploadedFile = $this->getMockBuilder(\Symfony\Component\HttpFoundation\File\UploadedFile::class)
            ->setConstructorArgs(['', '', 'text/html', 1000])
            ->getMock();
        $uploadedFile->method('getMimeType')->willReturn('text/html');
        $uploadedFile->method('getClientOriginalExtension')->willReturn('html');
        $uploadedFile->method('getSize')->willReturn(1000);
        
        // Mock file content with XSS
        $maliciousContent = '<script>alert("XSS")</script><p>Normal content</p>';
        $fileObject = $this->getMockBuilder(\SplFileObject::class)
            ->setConstructorArgs(['php://memory'])
            ->getMock();
        $fileObject->method('fread')->willReturn($maliciousContent);
        $uploadedFile->method('openFile')->willReturn($fileObject);

        $request = new Request();
        $request->setMethod('POST');
        $request->files->set('template_file', $uploadedFile);

        $controller->expects($this->once())
            ->method('addFlash')
            ->with('error', 'Die hochgeladene Datei enthält nicht erlaubte Inhalte.');

        $controller->expects($this->once())
            ->method('redirectToRoute')
            ->willReturn(new RedirectResponse('/admin/checklists'));

        $entityManager->expects($this->never())->method('flush');

        $response = $controller->emailTemplate($request, $checklist);
        $this->assertInstanceOf(Response::class, $response);
    }

    public function testEmptyTemplateContentRejection(): void
    {
        $entityManager = $this->createMockEntityManager();
        $checklistRepo = $this->createMock(ChecklistRepository::class);
        $emailService = $this->createMock(EmailService::class);
        $duplicationService = $this->createMock(ChecklistDuplicationService::class);

        $controller = $this->getMockBuilder(ChecklistController::class)
            ->setConstructorArgs([
                $entityManager,
                $checklistRepo,
                $emailService,
                $duplicationService
            ])
            ->onlyMethods(['render', 'addFlash', 'redirectToRoute'])
            ->getMock();

        $checklist = $this->createMockChecklist();

        $request = new Request();
        $request->setMethod('POST');
        $request->request->set('template_content', '');

        $controller->expects($this->once())
            ->method('addFlash')
            ->with('error', 'Bitte geben Sie Template-Inhalt ein oder laden eine Datei hoch.');

        $controller->expects($this->once())
            ->method('redirectToRoute')
            ->willReturn(new RedirectResponse('/admin/checklists'));

        $entityManager->expects($this->never())->method('flush');

        $response = $controller->emailTemplate($request, $checklist);
        $this->assertInstanceOf(Response::class, $response);
    }

    public function testFileSizeValidation(): void
    {
        $entityManager = $this->createMockEntityManager();
        $checklistRepo = $this->createMock(ChecklistRepository::class);
        $emailService = $this->createMock(EmailService::class);
        $duplicationService = $this->createMock(ChecklistDuplicationService::class);

        $controller = $this->getMockBuilder(ChecklistController::class)
            ->setConstructorArgs([
                $entityManager,
                $checklistRepo,
                $emailService,
                $duplicationService
            ])
            ->onlyMethods(['render', 'addFlash', 'redirectToRoute'])
            ->getMock();

        $checklist = $this->createMockChecklist();

        // Create a mock uploaded file that's too large
        $uploadedFile = $this->getMockBuilder(\Symfony\Component\HttpFoundation\File\UploadedFile::class)
            ->disableOriginalConstructor()
            ->getMock();
        $uploadedFile->method('getMimeType')->willReturn('text/html');
        $uploadedFile->method('getClientOriginalExtension')->willReturn('html');
        $uploadedFile->method('getSize')->willReturn(2 * 1024 * 1024); // 2MB

        $request = new Request();
        $request->setMethod('POST');
        $request->files->set('template_file', $uploadedFile);

        $controller->expects($this->once())
            ->method('addFlash')
            ->with('error', 'Die Datei ist zu groß. Maximale Größe: 1MB.');

        $controller->expects($this->once())
            ->method('redirectToRoute')
            ->willReturn(new RedirectResponse('/admin/checklists'));

        $entityManager->expects($this->never())->method('flush');

        $response = $controller->emailTemplate($request, $checklist);
        $this->assertInstanceOf(Response::class, $response);
    }

    public function testInvalidFileTypeRejection(): void
    {
        $entityManager = $this->createMockEntityManager();
        $checklistRepo = $this->createMock(ChecklistRepository::class);
        $emailService = $this->createMock(EmailService::class);
        $duplicationService = $this->createMock(ChecklistDuplicationService::class);

        $controller = $this->getMockBuilder(ChecklistController::class)
            ->setConstructorArgs([
                $entityManager,
                $checklistRepo,
                $emailService,
                $duplicationService
            ])
            ->onlyMethods(['render', 'addFlash', 'redirectToRoute'])
            ->getMock();

        $checklist = $this->createMockChecklist();

        // Create a mock uploaded file with invalid type
        $uploadedFile = $this->getMockBuilder(\Symfony\Component\HttpFoundation\File\UploadedFile::class)
            ->disableOriginalConstructor()
            ->getMock();
        $uploadedFile->method('getMimeType')->willReturn('application/pdf');
        $uploadedFile->method('getClientOriginalExtension')->willReturn('pdf');
        $uploadedFile->method('getSize')->willReturn(1000);

        $request = new Request();
        $request->setMethod('POST');
        $request->files->set('template_file', $uploadedFile);

        $controller->expects($this->once())
            ->method('addFlash')
            ->with('error', $this->stringContains('Bitte laden Sie nur HTML-Dateien hoch'));

        $controller->expects($this->once())
            ->method('redirectToRoute')
            ->willReturn(new RedirectResponse('/admin/checklists'));

        $entityManager->expects($this->never())->method('flush');

        $response = $controller->emailTemplate($request, $checklist);
        $this->assertInstanceOf(Response::class, $response);
    }
}
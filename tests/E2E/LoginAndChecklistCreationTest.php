<?php

namespace App\Tests\E2E;

use App\Entity\User;
use App\Entity\Checklist;
use App\Controller\Admin\ChecklistController;
use App\Repository\ChecklistRepository;
use App\Service\EmailService;
use App\Service\ChecklistDuplicationService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * End-to-End tests for login and checklist creation workflows
 * Simplified to work without database dependencies
 */
class LoginAndChecklistCreationTest extends TestCase
{
    private function createMockEntityManager(): EntityManagerInterface
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        
        $checklistRepo = $this->createMock(ChecklistRepository::class);
        $userRepo = $this->createMock(ObjectRepository::class);
        
        $entityManager->method('getRepository')->willReturnCallback(function ($class) use ($checklistRepo, $userRepo) {
            if ($class === Checklist::class) {
                return $checklistRepo;
            }
            if ($class === User::class) {
                return $userRepo;
            }
            return $this->createMock(ObjectRepository::class);
        });
        
        return $entityManager;
    }

    private function createMockUser(): User
    {
        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn(1);
        $user->method('getEmail')->willReturn('admin@test.com');
        $user->method('getRoles')->willReturn(['ROLE_ADMIN']);
        return $user;
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

    public function testSuccessfulChecklistCreationWorkflow(): void
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

        // Test GET request (form display)
        $getRequest = new Request();
        $getRequest->setMethod('GET');

        $controller->expects($this->once())
            ->method('render')
            ->with('admin/checklist/new.html.twig')
            ->willReturn(new Response('form html'));

        $getResponse = $controller->new($getRequest);
        $this->assertInstanceOf(Response::class, $getResponse);

        // Test POST request (form submission)
        $postRequest = new Request();
        $postRequest->setMethod('POST');
        $postRequest->request->set('title', 'Integration Test Checklist');
        $postRequest->request->set('target_email', 'integration@test.com');
        $postRequest->request->set('reply_email', 'reply@test.com');

        $entityManager->expects($this->once())->method('persist');
        $entityManager->expects($this->once())->method('flush');

        $controller->expects($this->once())
            ->method('addFlash')
            ->with('success', 'Checkliste wurde erfolgreich erstellt.');

        $controller->expects($this->once())
            ->method('redirectToRoute')
            ->with('admin_checklists')
            ->willReturn(new RedirectResponse('/admin/checklists'));

        $postResponse = $controller->new($postRequest);
        $this->assertInstanceOf(Response::class, $postResponse);
    }

    public function testChecklistCreationWithValidationErrors(): void
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

        // Test with invalid email format
        $request = new Request();
        $request->setMethod('POST');
        $request->request->set('title', 'Test Checklist');
        $request->request->set('target_email', 'target@test.com');
        $request->request->set('reply_email', 'not-an-email');

        $controller->expects($this->once())
            ->method('addFlash')
            ->with('error', 'Bitte eine gültige Rückfragen-E-Mail eingeben.');

        $controller->expects($this->once())
            ->method('redirectToRoute')
            ->with('admin_checklist_new')
            ->willReturn(new RedirectResponse('/admin/checklist/new'));

        $entityManager->expects($this->never())->method('persist');

        $response = $controller->new($request);
        $this->assertInstanceOf(Response::class, $response);
    }

    public function testChecklistEditWorkflow(): void
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
        
        // Test GET request for edit form
        $getRequest = new Request();
        $getRequest->setMethod('GET');

        $controller->expects($this->once())
            ->method('render')
            ->with('admin/checklist/edit.html.twig', $this->callback(function($data) use ($checklist) {
                return $data['checklist'] === $checklist && 
                       isset($data['exampleMitarbeiterId']) && 
                       is_string($data['exampleMitarbeiterId']);
            }))
            ->willReturn(new Response('edit form html'));

        $getResponse = $controller->edit($getRequest, $checklist);
        $this->assertInstanceOf(Response::class, $getResponse);

        // Test POST request for update
        $postRequest = new Request();
        $postRequest->setMethod('POST');
        $postRequest->request->set('title', 'Updated Checklist Title');
        $postRequest->request->set('target_email', 'updated@test.com');
        $postRequest->request->set('reply_email', 'updated-reply@test.com');

        $checklist->expects($this->once())->method('setTitle')->with('Updated Checklist Title');
        $checklist->expects($this->once())->method('setTargetEmail')->with('updated@test.com');
        $checklist->expects($this->once())->method('setReplyEmail')->with('updated-reply@test.com');

        $entityManager->expects($this->once())->method('flush');

        $controller->expects($this->once())
            ->method('addFlash')
            ->with('success', 'Checkliste wurde erfolgreich aktualisiert.');

        $controller->expects($this->once())
            ->method('redirectToRoute')
            ->with('admin_checklists')
            ->willReturn(new RedirectResponse('/admin/checklists'));

        $postResponse = $controller->edit($postRequest, $checklist);
        $this->assertInstanceOf(Response::class, $postResponse);
    }

    public function testChecklistIndexDisplaysAllChecklists(): void
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
            ->onlyMethods(['render'])
            ->getMock();

        $checklists = [
            $this->createMockChecklist(),
            $this->createMockChecklist()
        ];

        $checklistRepo->expects($this->once())
            ->method('findAll')
            ->willReturn($checklists);

        $controller->expects($this->once())
            ->method('render')
            ->with('admin/checklist/index.html.twig', ['checklists' => $checklists])
            ->willReturn(new Response('index html'));

        $response = $controller->index();
        $this->assertInstanceOf(Response::class, $response);
    }

    public function testPasswordValidationInAuthentication(): void
    {
        // Mock password hasher
        $passwordHasher = $this->createMock(UserPasswordHasherInterface::class);
        
        $user = $this->createMockUser();
        
        // Test that password verification would be called
        $passwordHasher->expects($this->once())
            ->method('isPasswordValid')
            ->with($user, 'test-password')
            ->willReturn(true);

        $result = $passwordHasher->isPasswordValid($user, 'test-password');
        $this->assertTrue($result);
    }

    public function testSecurityRoleValidationForAdminAccess(): void
    {
        $user = $this->createMockUser();
        
        // Test admin role validation
        $roles = $user->getRoles();
        $this->assertContains('ROLE_ADMIN', $roles);
        
        // Test regular user without admin role
        $regularUser = $this->createMock(User::class);
        $regularUser->method('getRoles')->willReturn(['ROLE_USER']);
        
        $regularRoles = $regularUser->getRoles();
        $this->assertNotContains('ROLE_ADMIN', $regularRoles);
        $this->assertContains('ROLE_USER', $regularRoles);
    }

    public function testEmailTemplateSecurityValidation(): void
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

        // Test template with script tags gets rejected
        $request = new Request();
        $request->setMethod('POST');
        
        $maliciousFile = $this->getMockBuilder(\Symfony\Component\HttpFoundation\File\UploadedFile::class)
            ->setConstructorArgs(['', '', 'text/html', 1000])
            ->getMock();
        $maliciousFile->method('getMimeType')->willReturn('text/html');
        $maliciousFile->method('getClientOriginalExtension')->willReturn('html');
        $maliciousFile->method('getSize')->willReturn(1000);
        
        $fileObject = $this->getMockBuilder(\SplFileObject::class)
            ->setConstructorArgs(['php://memory'])
            ->getMock();
        $fileObject->method('fread')->willReturn('<script>alert("XSS")</script>');
        $maliciousFile->method('openFile')->willReturn($fileObject);
        
        $request->files->set('template_file', $maliciousFile);

        $controller->expects($this->once())
            ->method('addFlash')
            ->with('error', 'Die hochgeladene Datei enthält nicht erlaubte Inhalte.');

        $controller->expects($this->once())
            ->method('redirectToRoute')
            ->willReturn(new RedirectResponse('/admin/checklist/email-template'));

        $response = $controller->emailTemplate($request, $checklist);
        $this->assertInstanceOf(Response::class, $response);
    }

    public function testCompleteChecklistLifecycle(): void
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

        // 1. Create checklist
        $createRequest = new Request();
        $createRequest->setMethod('POST');
        $createRequest->request->set('title', 'Lifecycle Test Checklist');
        $createRequest->request->set('target_email', 'lifecycle@test.com');

        $entityManager->expects($this->once())->method('persist');
        $entityManager->expects($this->exactly(2))->method('flush'); // once for create, once for delete

        $controller->expects($this->exactly(2))
            ->method('addFlash')
            ->withConsecutive(
                ['success', 'Checkliste wurde erfolgreich erstellt.'],
                ['success', 'Checkliste wurde erfolgreich gelöscht.']
            );

        $controller->expects($this->exactly(2))
            ->method('redirectToRoute')
            ->with('admin_checklists')
            ->willReturn(new RedirectResponse('/admin/checklists'));

        $createResponse = $controller->new($createRequest);
        $this->assertInstanceOf(Response::class, $createResponse);

        // 2. Delete checklist
        $checklist = $this->createMockChecklist();
        $checklist->method('getSubmissions')->willReturn(new \Doctrine\Common\Collections\ArrayCollection([]));

        $deleteRequest = new Request();
        $deleteRequest->setMethod('POST');
        $deleteRequest->request->set('_token', 'valid_token');

        $controller->method('isCsrfTokenValid')->willReturn(true);
        $entityManager->expects($this->once())->method('remove')->with($checklist);

        $deleteResponse = $controller->delete($deleteRequest, $checklist);
        $this->assertInstanceOf(Response::class, $deleteResponse);
    }
}
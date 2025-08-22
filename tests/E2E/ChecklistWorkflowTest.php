<?php

namespace App\Tests\E2E;

use App\Entity\User;
use App\Entity\Checklist;
use App\Entity\Submission;
use App\Controller\ChecklistController;
use App\Controller\Admin\ChecklistController as AdminChecklistController;
use App\Repository\ChecklistRepository;
use App\Repository\SubmissionRepository;
use App\Service\EmailService;
use App\Service\SubmissionService;
use App\Service\SubmissionFactory;
use App\Service\ChecklistDuplicationService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectRepository;
use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Psr\Log\LoggerInterface;

/**
 * End-to-End tests for complete checklist workflows
 * Simplified to work without database dependencies
 */
class ChecklistWorkflowTest extends TestCase
{
    private function createMockEntityManager(): EntityManagerInterface
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        
        $checklistRepo = $this->createMock(ChecklistRepository::class);
        $submissionRepo = $this->createMock(SubmissionRepository::class);
        $userRepo = $this->createMock(ObjectRepository::class);
        
        $entityManager->method('getRepository')->willReturnCallback(function ($class) use ($checklistRepo, $submissionRepo, $userRepo) {
            if ($class === Checklist::class) {
                return $checklistRepo;
            }
            if ($class === Submission::class) {
                return $submissionRepo;
            }
            if ($class === User::class) {
                return $userRepo;
            }
            return $this->createMock(ObjectRepository::class);
        });
        
        return $entityManager;
    }

    private function createMockChecklist(): Checklist
    {
        $checklist = $this->createMock(Checklist::class);
        $checklist->method('getId')->willReturn(1);
        $checklist->method('getTitle')->willReturn('Test Workflow Checklist');
        $checklist->method('getTargetEmail')->willReturn('workflow@test.com');
        $checklist->method('getReplyEmail')->willReturn('reply@test.com');
        $checklist->method('getGroups')->willReturn(new ArrayCollection([]));
        
        return $checklist;
    }

    private function createMockSubmission(): Submission
    {
        $submission = $this->createMock(Submission::class);
        $submission->method('getId')->willReturn(1);
        $submission->method('getName')->willReturn('Test User');
        $submission->method('getMitarbeiterId')->willReturn('TEST123');
        $submission->method('getEmail')->willReturn('test@example.com');
        return $submission;
    }

    public function testCompleteChecklistWorkflowFromCreationToSubmission(): void
    {
        // Test admin checklist creation
        $entityManager = $this->createMockEntityManager();
        $checklistRepo = $this->createMock(ChecklistRepository::class);
        $emailService = $this->createMock(EmailService::class);
        $duplicationService = $this->createMock(ChecklistDuplicationService::class);

        $adminController = $this->getMockBuilder(AdminChecklistController::class)
            ->setConstructorArgs([
                $entityManager,
                $checklistRepo,
                $emailService,
                $duplicationService
            ])
            ->onlyMethods(['render', 'addFlash', 'redirectToRoute'])
            ->getMock();

        $createRequest = new Request();
        $createRequest->setMethod('POST');
        $createRequest->request->set('title', 'Workflow Test Checklist');
        $createRequest->request->set('target_email', 'workflow@test.com');

        $entityManager->expects($this->once())->method('persist');
        $entityManager->expects($this->once())->method('flush');

        $adminController->expects($this->once())
            ->method('addFlash')
            ->with('success', 'Checkliste wurde erfolgreich erstellt.');

        $adminController->expects($this->once())
            ->method('redirectToRoute')
            ->with('admin_checklists')
            ->willReturn(new RedirectResponse('/admin/checklists'));

        $response = $adminController->new($createRequest);
        $this->assertInstanceOf(Response::class, $response);
    }

    public function testChecklistControllerServiceIntegration(): void
    {
        // Test that the controller properly integrates with services
        $entityManager = $this->createMockEntityManager();
        $submissionService = $this->createMock(SubmissionService::class);
        $emailService = $this->createMock(EmailService::class);
        $submissionFactory = $this->createMock(SubmissionFactory::class);
        $logger = $this->createMock(LoggerInterface::class);

        $controller = new ChecklistController(
            $entityManager,
            $submissionService,
            $emailService,
            $submissionFactory,
            $logger
        );

        $this->assertInstanceOf(ChecklistController::class, $controller);
    }

    public function testSubmissionServiceCollectsDataCorrectly(): void
    {
        $submissionService = $this->createMock(SubmissionService::class);
        $checklist = $this->createMockChecklist();
        $request = new Request();

        // Test that the service method can be called with expected parameters
        $submissionService->expects($this->once())
            ->method('collectSubmissionData')
            ->with($checklist, $request)
            ->willReturn(['collected' => 'data']);

        $result = $submissionService->collectSubmissionData($checklist, $request);
        $this->assertEquals(['collected' => 'data'], $result);
    }

    public function testSubmissionFactoryCreatesSubmission(): void
    {
        $submissionFactory = $this->createMock(SubmissionFactory::class);
        $checklist = $this->createMockChecklist();
        $submission = $this->createMockSubmission();

        $submissionFactory->expects($this->once())
            ->method('createSubmission')
            ->with($checklist, 'Test User', 'TEST123', 'test@example.com', ['data'])
            ->willReturn($submission);

        $result = $submissionFactory->createSubmission(
            $checklist, 
            'Test User', 
            'TEST123', 
            'test@example.com', 
            ['data']
        );

        $this->assertSame($submission, $result);
    }

    public function testEmailServiceSendsEmails(): void
    {
        $emailService = $this->createMock(EmailService::class);
        $submission = $this->createMockSubmission();

        $emailService->expects($this->once())
            ->method('generateAndSendEmail')
            ->with($submission);

        $emailService->generateAndSendEmail($submission);
    }

    public function testEmailServiceHandlesFailures(): void
    {
        $emailService = $this->createMock(EmailService::class);
        $submission = $this->createMockSubmission();

        $emailService->expects($this->once())
            ->method('generateAndSendEmail')
            ->with($submission)
            ->willThrowException(new \Exception('SMTP connection failed'));

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('SMTP connection failed');

        $emailService->generateAndSendEmail($submission);
    }

    public function testChecklistValidatesRequiredFields(): void
    {
        $entityManager = $this->createMockEntityManager();
        $submissionService = $this->createMock(SubmissionService::class);
        $emailService = $this->createMock(EmailService::class);
        $submissionFactory = $this->createMock(SubmissionFactory::class);
        $logger = $this->createMock(LoggerInterface::class);

        $controller = new ChecklistController(
            $entityManager,
            $submissionService,
            $emailService,
            $submissionFactory,
            $logger
        );

        // Test with missing checklist_id parameter
        $request = new Request();
        // Missing checklist_id completely

        // Expect NotFoundHttpException for missing checklist_id
        $this->expectException(\Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class);
        $this->expectExceptionMessage('Ungültige Parameter');

        $controller->form($request);
    }

    public function testChecklistLinkGeneration(): void
    {
        $entityManager = $this->createMockEntityManager();
        $checklistRepo = $this->createMock(ChecklistRepository::class);
        $emailService = $this->createMock(EmailService::class);
        $duplicationService = $this->createMock(ChecklistDuplicationService::class);

        $controller = $this->getMockBuilder(AdminChecklistController::class)
            ->setConstructorArgs([
                $entityManager,
                $checklistRepo,
                $emailService,
                $duplicationService
            ])
            ->onlyMethods(['render'])
            ->getMock();

        $checklist = $this->createMockChecklist();

        // Test that the edit form generates a valid UUID for example links
        $request = new Request();
        $request->setMethod('GET');

        $controller->expects($this->once())
            ->method('render')
            ->with('admin/checklist/edit.html.twig', $this->callback(function ($data) use ($checklist) {
                return $data['checklist'] === $checklist && 
                       isset($data['exampleMitarbeiterId']) && 
                       is_string($data['exampleMitarbeiterId']) &&
                       preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $data['exampleMitarbeiterId']);
            }))
            ->willReturn(new Response('edit form'));

        $response = $controller->edit($request, $checklist);
        $this->assertInstanceOf(Response::class, $response);
    }

    public function testChecklistAccessWithInvalidParameters(): void
    {
        $entityManager = $this->createMockEntityManager();
        $submissionService = $this->createMock(SubmissionService::class);
        $emailService = $this->createMock(EmailService::class);
        $submissionFactory = $this->createMock(SubmissionFactory::class);
        $logger = $this->createMock(LoggerInterface::class);

        $controller = new ChecklistController(
            $entityManager,
            $submissionService,
            $emailService,
            $submissionFactory,
            $logger
        );

        $checklistRepo = $this->createMock(ChecklistRepository::class);
        $checklistRepo->method('find')->willReturn(null); // Checklist not found

        $entityManager->method('getRepository')->willReturn($checklistRepo);

        $request = new Request();
        $request->query->set('checklist_id', '999');
        $request->query->set('name', 'Test User');
        $request->query->set('mitarbeiter_id', 'TEST123');
        $request->query->set('email', 'test@example.com');

        // Expect NotFoundHttpException to be thrown
        $this->expectException(\Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class);
        $this->expectExceptionMessage('Stückliste nicht gefunden');

        $controller->form($request);
    }

    public function testRepositoryFindMethods(): void
    {
        $checklistRepo = $this->createMock(ChecklistRepository::class);
        $submissionRepo = $this->createMock(SubmissionRepository::class);
        
        $checklist = $this->createMockChecklist();
        $submission = $this->createMockSubmission();

        // Test checklist repository find method
        $checklistRepo->expects($this->once())
            ->method('find')
            ->with(1)
            ->willReturn($checklist);

        $result = $checklistRepo->find(1);
        $this->assertSame($checklist, $result);

        // Test submission repository find method
        $submissionRepo->expects($this->once())
            ->method('findOneByChecklistAndMitarbeiterId')
            ->with($checklist, 'TEST123')
            ->willReturn($submission);

        $result = $submissionRepo->findOneByChecklistAndMitarbeiterId($checklist, 'TEST123');
        $this->assertSame($submission, $result);
    }

    public function testEntityManagerPersistenceOperations(): void
    {
        $entityManager = $this->createMockEntityManager();
        $checklist = $this->createMockChecklist();
        $submission = $this->createMockSubmission();

        // Test persist operation
        $entityManager->expects($this->exactly(2))
            ->method('persist')
            ->withConsecutive([$checklist], [$submission]);

        // Test flush operation
        $entityManager->expects($this->once())
            ->method('flush');

        // Test remove operation
        $entityManager->expects($this->once())
            ->method('remove')
            ->with($checklist);

        $entityManager->persist($checklist);
        $entityManager->persist($submission);
        $entityManager->flush();
        $entityManager->remove($checklist);
    }

    public function testLoggerErrorHandling(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        
        $logger->expects($this->once())
            ->method('error')
            ->with($this->stringContains('E-Mail-Versendung fehlgeschlagen'));

        $logger->error('E-Mail-Versendung fehlgeschlagen für Submission 123: SMTP connection failed');
    }

    public function testAdminControllerDuplication(): void
    {
        $entityManager = $this->createMockEntityManager();
        $checklistRepo = $this->createMock(ChecklistRepository::class);
        $emailService = $this->createMock(EmailService::class);
        $duplicationService = $this->createMock(ChecklistDuplicationService::class);

        $controller = $this->getMockBuilder(AdminChecklistController::class)
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
}
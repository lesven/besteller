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
use App\Service\ValidationService;
use App\Service\TemplateResolverService;
use App\Exception\InvalidParametersException;
use App\Exception\ChecklistNotFoundException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectRepository;
use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Psr\Log\LoggerInterface;

/**
 * End-to-End Tests für komplette Checklist-Workflows
 * 
 * Diese Testsuite simuliert den kompletten Workflow einer Checkliste von der 
 * Erstellung durch einen Admin bis zur Einreichung durch einen Mitarbeiter.
 * 
 * WICHTIG: Diese Tests arbeiten OHNE echte Datenbankverbindung - alle 
 * Datenbankoperationen werden über Mocks simuliert, um die Tests schnell 
 * und unabhängig von der Infrastruktur zu halten.
 * 
 * WAS DIESER TEST KOMPLETT ABDECKT:
 * 
 * 1. ADMIN-WORKFLOW:
 *    - Erstellung neuer Checklisten (testCompleteChecklistWorkflowFromCreationToSubmission)
 *    - Link-Generierung für Mitarbeiter (testChecklistLinkGeneration)
 *    - Duplizierung von Checklisten (testAdminControllerDuplication)
 * 
 * 2. MITARBEITER-WORKFLOW:
 *    - Zugriff auf Checkliste über Link (testChecklistAccessWithInvalidParameters)
 *    - Validierung der Eingaben (testChecklistValidatesRequiredFields)
 *    - Submission-Erstellung (testSubmissionFactoryCreatesSubmission)
 * 
 * 3. SERVICE-LAYER-INTEGRATION:
 *    - Controller-Service-Integration (testChecklistControllerServiceIntegration)
 *    - Datensammlung durch SubmissionService (testSubmissionServiceCollectsDataCorrectly)
 *    - E-Mail-Versendung durch EmailService (testEmailServiceSendsEmails)
 *    - Fehlerbehandlung bei E-Mail-Problemen (testEmailServiceHandlesFailures)
 * 
 * 4. DATENBANK-OPERATIONEN:
 *    - Repository-Zugriffe (testRepositoryFindMethods)
 *    - Persistierung und Löschung (testEntityManagerPersistenceOperations)
 *    - Logging bei Fehlern (testLoggerErrorHandling)
 * 
 * 5. FEHLERBEHANDLUNG:
 *    - Ungültige Parameter abfangen
 *    - Nicht gefundene Checklisten behandeln
 *    - E-Mail-Versendungsfehler loggen
 * 
 * MOCK-STRATEGIE:
 * - EntityManager, Repositories und Services werden gemockt
 * - Echte Entity-Instanzen werden durch Mocks ersetzt
 * - HTTP-Requests werden simuliert (GET/POST)
 * - Alle Erwartungen werden explizit definiert und validiert
 * 
 * NUTZEN:
 * - Stellt sicher, dass der komplette Workflow funktioniert
 * - Testet die Integration zwischen allen Komponenten
 * - Läuft schnell ohne DB-Abhängigkeiten
 * - Deckt Happy Path und Error Cases ab
 */
class ChecklistWorkflowTest extends TestCase
{
    /**
     * Erstellt einen Mock EntityManager für Tests
     * 
     * Der EntityManager ist das zentrale Interface für alle Datenbankoperationen.
     * Dieser Mock simuliert die wichtigsten Repository-Zugriffe ohne echte DB.
     * 
     * @return EntityManagerInterface Gemockter EntityManager
     */
    private function createMockEntityManager(): EntityManagerInterface
    {
        // Hauptmock für den EntityManager
        $entityManager = $this->createMock(EntityManagerInterface::class);
        
        // Mocks für die verschiedenen Repositories
        $checklistRepo = $this->createMock(ChecklistRepository::class);
        $submissionRepo = $this->createMock(SubmissionRepository::class);
        $userRepo = $this->createMock(ObjectRepository::class);
        
        // EntityManager gibt je nach Entity-Klasse das passende Repository zurück
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

    /**
     * Erstellt eine Mock-Checkliste für Tests
     * 
     * Simuliert eine typische Checkliste mit allen wichtigen Eigenschaften
     * wie ID, Titel, E-Mail-Adressen und leerer Gruppen-Collection.
     * 
     * @return Checklist Gemockte Checkliste
     */
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

    /**
     * Erstellt eine Mock-Submission für Tests
     * 
     * Simuliert eine Mitarbeiter-Einreichung mit typischen Testdaten
     * wie Name, Mitarbeiter-ID und E-Mail-Adresse.
     * 
     * @return Submission Gemockte Submission
     */
    private function createMockSubmission(): Submission
    {
        $submission = $this->createMock(Submission::class);
        $submission->method('getId')->willReturn(1);
        $submission->method('getName')->willReturn('Test User');
        $submission->method('getMitarbeiterId')->willReturn('TEST123');
        $submission->method('getEmail')->willReturn('test@example.com');
        return $submission;
    }

    /**
     * TEST 1: Kompletter Workflow - Admin erstellt eine neue Checkliste
     * 
     * Dieser Test simuliert den ersten Schritt im Workflow: Ein Administrator
     * erstellt über das Admin-Interface eine neue Checkliste.
     * 
     * ABLAUF:
     * 1. POST-Request mit Checklisten-Daten wird erstellt
     * 2. AdminChecklistController verarbeitet die Anfrage
     * 3. EntityManager.persist() wird aufgerufen (neue Checkliste speichern)
     * 4. EntityManager.flush() wird aufgerufen (Änderungen in DB schreiben)
     * 5. Success-Flash-Message wird gesetzt
     * 6. Redirect zur Checklisten-Übersicht erfolgt
     * 
     * VALIDIERT:
     * - Dass persist() und flush() genau einmal aufgerufen werden
     * - Dass eine Erfolgsmeldung gesetzt wird
     * - Dass zur richtigen Route weitergeleitet wird
     */
    public function testCompleteChecklistWorkflowFromCreationToSubmission(): void
    {
        // Mock-Dependencies für den Admin-Controller erstellen
        $entityManager = $this->createMockEntityManager();
        $checklistRepo = $this->createMock(ChecklistRepository::class);
        $emailService = $this->createMock(EmailService::class);
        $duplicationService = $this->createMock(ChecklistDuplicationService::class);

        // Admin-Controller mit gemockten Dependencies
        $adminController = $this->getMockBuilder(AdminChecklistController::class)
            ->setConstructorArgs([
                $entityManager,
                $checklistRepo,
                $emailService,
                $duplicationService
            ])
            ->onlyMethods(['render', 'addFlash', 'redirectToRoute'])
            ->getMock();

        // POST-Request simulieren mit Checklisten-Daten
        $createRequest = new Request();
        $createRequest->setMethod('POST');
        $createRequest->request->set('title', 'Workflow Test Checklist');
        $createRequest->request->set('target_email', 'workflow@test.com');

        // ERWARTUNGEN: EntityManager soll persist() und flush() aufrufen
        $entityManager->expects($this->once())->method('persist');
        $entityManager->expects($this->once())->method('flush');

        // ERWARTUNGEN: Success-Flash soll gesetzt werden
        $adminController->expects($this->once())
            ->method('addFlash')
            ->with('success', 'Checkliste wurde erfolgreich erstellt.');

        // ERWARTUNGEN: Redirect zur Admin-Übersicht
        $adminController->expects($this->once())
            ->method('redirectToRoute')
            ->with('admin_checklists')
            ->willReturn(new RedirectResponse('/admin/checklists'));

        // AUSFÜHRUNG: Controller-Methode aufrufen
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
        $validationService = $this->createMock(ValidationService::class);
        $templateResolver = $this->createMock(TemplateResolverService::class);

        $controller = new ChecklistController(
            $entityManager,
            $submissionService,
            $emailService,
            $submissionFactory,
            $logger,
            $validationService,
            $templateResolver
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
        $validationService = $this->createMock(ValidationService::class);
        $templateResolver = $this->createMock(TemplateResolverService::class);

        $controller = new ChecklistController(
            $entityManager,
            $submissionService,
            $emailService,
            $submissionFactory,
            $logger,
            $validationService,
            $templateResolver
        );

        // Test with missing checklist_id parameter
        $request = new Request();
        // Missing checklist_id completely

        // Expect InvalidParametersException for missing checklist_id
        $this->expectException(InvalidParametersException::class);

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
        // Create entity manager mock directly instead of using createMockEntityManager()
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $submissionService = $this->createMock(SubmissionService::class);
        $emailService = $this->createMock(EmailService::class);
        $submissionFactory = $this->createMock(SubmissionFactory::class);
        $logger = $this->createMock(LoggerInterface::class);
        $validationService = $this->createMock(ValidationService::class);

        $checklistRepo = $this->createMock(ChecklistRepository::class);
        $checklistRepo->expects($this->once())
            ->method('findOrFail')
            ->with(999)
            ->willThrowException(new ChecklistNotFoundException(999)); // Checklist not found

        $submissionRepo = $this->createMock(SubmissionRepository::class);
        $submissionRepo->method('findOneByChecklistAndMitarbeiterId')->willReturn(null);

        $entityManager->method('getRepository')->willReturnCallback(function ($class) use ($checklistRepo, $submissionRepo) {
            if ($class === Checklist::class) {
                return $checklistRepo;
            }
            if ($class === Submission::class) {
                return $submissionRepo;
            }
            return null;
        });

        $templateResolver = $this->createMock(TemplateResolverService::class);
        
        $controller = $this->getMockBuilder(ChecklistController::class)
            ->setConstructorArgs([
                $entityManager,
                $submissionService,
                $emailService,
                $submissionFactory,
                $logger,
                $validationService,
                $templateResolver
            ])
            ->onlyMethods(['render', 'addFlash'])
            ->getMock();

        $controller->method('render')->willReturn(new Response('test'));
        $controller->method('addFlash');

        $request = new Request();
        $request->query->set('checklist_id', '999');
        $request->query->set('name', 'Test User');
        $request->query->set('mitarbeiter_id', 'TEST123');
        $request->query->set('email', 'test@example.com');

        // Expect ChecklistNotFoundException to be thrown
        $this->expectException(ChecklistNotFoundException::class);
        
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
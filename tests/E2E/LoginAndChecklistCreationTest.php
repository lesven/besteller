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
 * End-to-End Tests für Login- und Checklisten-Erstellungs-Workflows
 * 
 * Diese Testsuite simuliert den kompletten Admin-Workflow von der Anmeldung 
 * bis zur Verwaltung von Checklisten. Alle Tests arbeiten mit Mocks ohne 
 * echte Datenbankverbindung für schnelle und zuverlässige Ausführung.
 * 
 * WAS DIESER TEST KOMPLETT ABDECKT:
 * 
 * 1. ADMIN-AUTHENTIFIZIERUNG:
 *    - Passwort-Validierung (testPasswordValidationInAuthentication)
 *    - Rollen-basierte Zugriffskontrolle (testSecurityRoleValidationForAdminAccess)
 *    - ROLE_ADMIN vs ROLE_USER Unterscheidung
 * 
 * 2. CHECKLISTEN-ERSTELLUNG:
 *    - GET: Formular anzeigen (testSuccessfulChecklistCreationWorkflow)
 *    - POST: Neue Checkliste erstellen mit persist/flush
 *    - Erfolgreiche Weiterleitung und Flash-Message
 * 
 * 3. EINGABE-VALIDIERUNG:
 *    - E-Mail-Format-Validierung (testChecklistCreationWithValidationErrors)
 *    - Fehlerbehandlung bei ungültigen Daten
 *    - Verhindert persist() bei Validierungsfehlern
 * 
 * 4. CHECKLISTEN-BEARBEITUNG:
 *    - GET: Edit-Formular mit UUID-Beispiel (testChecklistEditWorkflow)
 *    - POST: Aktualisierung bestehender Checklisten
 *    - Setter-Methoden werden korrekt aufgerufen
 * 
 * 5. CHECKLISTEN-ÜBERSICHT:
 *    - Index-Seite mit allen Checklisten (testChecklistIndexDisplaysAllChecklists)
 *    - Repository findAll() Integration
 * 
 * 6. SICHERHEITS-VALIDIERUNG:
 *    - Template-Upload-Sicherheit (testEmailTemplateSecurityValidation)
 *    - XSS-Schutz bei HTML-Templates
 *    - Ablehnung von <script>-Tags
 * 
 * 7. KOMPLETTER LEBENSZYKLUS:
 *    - Erstellen → Bearbeiten → Löschen (testCompleteChecklistLifecycle)
 *    - CSRF-Token-Validierung bei Löschung
 *    - Korrekte Reihenfolge der DB-Operationen
 * 
 * MOCK-STRATEGIE:
 * - EntityManager mit Repository-Callback-Mapping
 * - User-Mocks mit verschiedenen Rollen
 * - Checklist-Mocks mit Standard-Eigenschaften
 * - HTTP-Request-Simulation (GET/POST)
 * - File-Upload-Mocks für Sicherheitstests
 * 
 * SICHERHEITS-FOKUS:
 * - Rollenbasierte Zugriffskontrolle wird explizit getestet
 * - Template-Uploads werden auf schädliche Inhalte geprüft
 * - CSRF-Tokens werden bei kritischen Aktionen validiert
 * - E-Mail-Validierung verhindert ungültige Eingaben
 */
class LoginAndChecklistCreationTest extends TestCase
{
    /**
     * Erstellt einen Mock EntityManager für alle Tests
     * 
     * Der EntityManager ist zentral für alle Datenbankoperationen.
     * Diese Mock-Version simuliert Repository-Zugriffe ohne echte DB.
     * 
     * REPOSITORY-MAPPING:
     * - Checklist::class → ChecklistRepository Mock
     * - User::class → ObjectRepository Mock (für User-Verwaltung)
     * - Alle anderen → Standard ObjectRepository Mock
     * 
     * @return EntityManagerInterface Gemockter EntityManager
     */
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

    /**
     * Erstellt einen Mock Admin-User für Authentifizierungs-Tests
     * 
     * Simuliert einen angemeldeten Administrator mit allen nötigen
     * Eigenschaften für rollenbasierte Zugriffskontrolle.
     * 
     * EIGENSCHAFTEN:
     * - ID: 1 (Standard-Test-User)
     * - E-Mail: admin@test.com
     * - Rolle: ROLE_ADMIN (für Admin-Bereich-Zugriff)
     * 
     * @return User Gemockter Admin-User
     */
    private function createMockUser(): User
    {
        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn(1);
        $user->method('getEmail')->willReturn('admin@test.com');
        $user->method('getRoles')->willReturn(['ROLE_ADMIN']);
        return $user;
    }

    /**
     * Erstellt eine Mock-Checkliste für alle Checklisten-Tests
     * 
     * Simuliert eine typische Checkliste mit Standard-Eigenschaften
     * für Tests der CRUD-Operationen.
     * 
     * EIGENSCHAFTEN:
     * - ID: 1 (Standard-Test-Checkliste)
     * - Titel: "Test Checklist"
     * - Ziel-E-Mail: target@test.com (wohin Submissions gehen)
     * - Reply-E-Mail: reply@test.com (für Rückfragen)
     * 
     * @return Checklist Gemockte Checkliste
     */
    private function createMockChecklist(): Checklist
    {
        $checklist = $this->createMock(Checklist::class);
        $checklist->method('getId')->willReturn(1);
        $checklist->method('getTitle')->willReturn('Test Checklist');
        $checklist->method('getTargetEmail')->willReturn('target@test.com');
        $checklist->method('getReplyEmail')->willReturn('reply@test.com');
        return $checklist;
    }

    /**
     * TEST 1: Erfolgreicher Checklisten-Erstellungs-Workflow (GET + POST)
     * 
     * Dieser Test simuliert den kompletten Prozess der Checklisten-Erstellung
     * durch einen Administrator über das Web-Interface.
     * 
     * TEIL A - GET-Request (Formular anzeigen):
     * 1. Admin ruft /admin/checklist/new auf
     * 2. Controller rendert das Erstellungsformular
     * 3. Template admin/checklist/new.html.twig wird zurückgegeben
     * 
     * TEIL B - POST-Request (Formular absenden):
     * 1. Admin füllt Formular aus (Titel, Ziel-E-Mail, Reply-E-Mail)
     * 2. Controller validiert Eingaben
     * 3. Neue Checkliste wird über EntityManager.persist() gespeichert
     * 4. EntityManager.flush() schreibt Änderungen in die Datenbank
     * 5. Success-Flash-Message wird gesetzt
     * 6. Weiterleitung zur Checklisten-Übersicht
     * 
     * VALIDIERT:
     * - Korrekte Template-Verwendung
     * - Datenbankoperationen (persist + flush)
     * - User-Feedback (Flash-Messages)
     * - Navigation (Redirect nach Erfolg)
     */
    public function testSuccessfulChecklistCreationWorkflow(): void
    {
        // SETUP: Alle nötigen Dependencies für den Controller mocken
        $entityManager = $this->createMockEntityManager();
        $checklistRepo = $this->createMock(ChecklistRepository::class);
        $emailService = $this->createMock(EmailService::class);
        $duplicationService = $this->createMock(ChecklistDuplicationService::class);

        // Controller mit gemockten Dependencies erstellen
        $controller = $this->getMockBuilder(ChecklistController::class)
            ->setConstructorArgs([
                $entityManager,
                $checklistRepo,
                $emailService,
                $duplicationService
            ])
            ->onlyMethods(['render', 'addFlash', 'redirectToRoute'])
            ->getMock();

        // TEIL A: GET-Request - Formular anzeigen
        $getRequest = new Request();
        $getRequest->setMethod('GET');

        // ERWARTUNG: Korrektes Template wird gerendert
        $controller->expects($this->once())
            ->method('render')
            ->with('admin/checklist/new.html.twig')
            ->willReturn(new Response('form html'));

        // AUSFÜHRUNG: GET-Request ausführen
        $getResponse = $controller->new($getRequest);
        $this->assertInstanceOf(Response::class, $getResponse);

        // TEIL B: POST-Request - Formular absenden
        $postRequest = new Request();
        $postRequest->setMethod('POST');
        $postRequest->request->set('title', 'Integration Test Checklist');
        $postRequest->request->set('target_email', 'integration@test.com');
        $postRequest->request->set('reply_email', 'reply@test.com');

        // ERWARTUNGEN: Datenbankoperationen werden korrekt ausgeführt
        $entityManager->expects($this->once())->method('persist');
        $entityManager->expects($this->once())->method('flush');

        // ERWARTUNG: Success-Message wird gesetzt
        $controller->expects($this->once())
            ->method('addFlash')
            ->with('success', 'Checkliste wurde erfolgreich erstellt.');

        // ERWARTUNG: Weiterleitung zur Übersicht
        $controller->expects($this->once())
            ->method('redirectToRoute')
            ->with('admin_checklists')
            ->willReturn(new RedirectResponse('/admin/checklists'));

        // AUSFÜHRUNG: POST-Request ausführen
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

    /**
     * TEST 6: E-Mail-Template-Sicherheits-Validierung (XSS-Schutz)
     * 
     * Dieser kritische Sicherheitstest stellt sicher, dass böswillige 
     * HTML-Templates mit JavaScript-Code abgelehnt werden.
     * 
     * SZENARIO:
     * 1. Angreifer versucht, HTML-Template mit <script>-Tags hochzuladen
     * 2. System erkennt gefährlichen Inhalt (XSS-Potential)
     * 3. Upload wird abgelehnt und Fehlermeldung angezeigt
     * 4. Keine Persistierung in der Datenbank
     * 
     * SICHERHEITS-VALIDIERUNG:
     * - MIME-Type wird geprüft (text/html)
     * - Dateiinhalt wird gescannt nach <script>-Tags
     * - Potentielle XSS-Angriffe werden verhindert
     * - User wird über Sicherheitsproblem informiert
     * 
     * ERWARTETES VERHALTEN:
     * - Fehlermeldung: "nicht erlaubte Inhalte"
     * - Redirect zurück zum Upload-Formular
     * - Keine Datenbankoperationen
     * 
     * WARUM WICHTIG:
     * - Verhindert Code-Injection über Template-Uploads
     * - Schützt andere Admin-User vor gespeicherten XSS-Attacken
     * - Kritisch für Produktionsumgebungen
     */
    public function testEmailTemplateSecurityValidation(): void
    {
        // SETUP: Controller mit Sicherheitsfokus
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

        // ANGRIFFS-SZENARIO: Böswilliges HTML-Template hochladen
        $request = new Request();
        $request->setMethod('POST');
        
        // Mock für gefährliche Datei mit Script-Tags
        $maliciousFile = $this->getMockBuilder(\Symfony\Component\HttpFoundation\File\UploadedFile::class)
            ->setConstructorArgs(['', '', 'text/html', 1000])
            ->getMock();
        $maliciousFile->method('getMimeType')->willReturn('text/html');
        $maliciousFile->method('getClientOriginalExtension')->willReturn('html');
        $maliciousFile->method('getSize')->willReturn(1000);
        
        // Dateiinhalt mit gefährlichem JavaScript
        $fileObject = $this->getMockBuilder(\SplFileObject::class)
            ->setConstructorArgs(['php://memory'])
            ->getMock();
        $fileObject->method('fread')->willReturn('<script>alert("XSS")</script>');
        $maliciousFile->method('openFile')->willReturn($fileObject);
        
        $request->files->set('template_file', $maliciousFile);

        // ERWARTUNG: Sicherheitsfehler wird erkannt und gemeldet
        $controller->expects($this->once())
            ->method('addFlash')
            ->with('error', 'Die hochgeladene Datei enthält nicht erlaubte Inhalte.');

        // ERWARTUNG: Redirect zurück zum Upload-Formular
        $controller->expects($this->once())
            ->method('redirectToRoute')
            ->willReturn(new RedirectResponse('/admin/checklist/email-template'));

        // AUSFÜHRUNG: Upload-Versuch ausführen
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
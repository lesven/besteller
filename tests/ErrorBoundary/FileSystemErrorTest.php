<?php

namespace App\Tests\ErrorBoundary;

use App\Controller\Admin\ChecklistController;
use App\Entity\Checklist;
use App\Service\EmailService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Tests für Dateisystem-Fehlerfälle.
 * Verhalten bei Dateisystem-Problemen, Upload-Fehlern und Speicherplatz-Erschöpfung.
 */
class FileSystemErrorTest extends TestCase
{
    public function testTemplateUploadHandlesDiskSpaceExhaustion(): void
    {
        // Mocks erstellen
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $emailService = $this->createMock(EmailService::class);

        // Mock Controller mit überschriebenen Methoden
        $controller = $this->getMockBuilder(ChecklistController::class)
            ->setConstructorArgs([$entityManager, $emailService])
            ->onlyMethods(['addFlash', 'redirectToRoute', 'isCsrfTokenValid'])
            ->getMock();

        $controller->method('isCsrfTokenValid')->willReturn(true);
        
        // Erwarte Fehler-Flash-Message
        $controller->expects($this->once())
                  ->method('addFlash')
                  ->with('error', $this->stringContains('konnte nicht gelesen werden'));

        // Erwarte Redirect zurück zur Bearbeitung
        $controller->expects($this->once())
                  ->method('redirectToRoute')
                  ->willReturn(new \Symfony\Component\HttpFoundation\RedirectResponse('/admin/checklist/1'));

        // Temporäre Datei erstellen, die "Disk Full" simuliert
        $tempFile = tempnam(sys_get_temp_dir(), 'test_upload');
        file_put_contents($tempFile, 'test content');

        // UploadedFile Mock, der beim Lesen fehlschlägt
        $uploadedFile = $this->getMockBuilder(UploadedFile::class)
            ->setConstructorArgs([$tempFile, 'test.html', 'text/html', null, true])
            ->onlyMethods(['openFile'])
            ->getMock();

        // Simuliere Dateisystem-Fehler beim Öffnen
        $fileObject = $this->createMock(\SplFileObject::class);
        $fileObject->method('fread')->willReturn(false); // Simuliert Read-Fehler

        $uploadedFile->method('openFile')->willReturn($fileObject);

        $request = new Request();
        $request->files->set('template_file', $uploadedFile);
        $request->request->set('_token', 'valid_token');

        $checklist = new Checklist();
        $checklist->setTitle('Test Checklist');

        $response = $controller->editEmailTemplate($request, $checklist);

        $this->assertInstanceOf(\Symfony\Component\HttpFoundation\RedirectResponse::class, $response);

        // Aufräumen
        unlink($tempFile);
    }

    public function testTemplateUploadHandlesFileSizeTooLarge(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $emailService = $this->createMock(EmailService::class);

        $controller = $this->getMockBuilder(ChecklistController::class)
            ->setConstructorArgs([$entityManager, $emailService])
            ->onlyMethods(['addFlash', 'redirectToRoute', 'isCsrfTokenValid'])
            ->getMock();

        $controller->method('isCsrfTokenValid')->willReturn(true);
        
        // Erwarte spezifische Größen-Fehler-Message
        $controller->expects($this->once())
                  ->method('addFlash')
                  ->with('error', 'Die Datei ist zu groß. Maximale Größe: 1MB.');

        $controller->expects($this->once())
                  ->method('redirectToRoute')
                  ->willReturn(new \Symfony\Component\HttpFoundation\RedirectResponse('/admin/checklist/1'));

        // Große Datei simulieren (> 1MB)
        $tempFile = tempnam(sys_get_temp_dir(), 'large_test');
        file_put_contents($tempFile, str_repeat('a', 1024 * 1024 + 1)); // 1MB + 1 Byte

        $uploadedFile = $this->getMockBuilder(UploadedFile::class)
            ->setConstructorArgs([$tempFile, 'large.html', 'text/html', null, true])
            ->onlyMethods(['getSize'])
            ->getMock();

        $uploadedFile->method('getSize')->willReturn(1024 * 1024 + 1); // Größer als 1MB

        $request = new Request();
        $request->files->set('template_file', $uploadedFile);
        $request->request->set('_token', 'valid_token');

        $checklist = new Checklist();
        $checklist->setTitle('Large File Test');

        $response = $controller->editEmailTemplate($request, $checklist);

        $this->assertInstanceOf(\Symfony\Component\HttpFoundation\RedirectResponse::class, $response);

        unlink($tempFile);
    }

    public function testTemplateUploadHandlesInvalidMimeType(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $emailService = $this->createMock(EmailService::class);

        $controller = $this->getMockBuilder(ChecklistController::class)
            ->setConstructorArgs([$entityManager, $emailService])
            ->onlyMethods(['addFlash', 'redirectToRoute', 'isCsrfTokenValid'])
            ->getMock();

        $controller->method('isCsrfTokenValid')->willReturn(true);
        
        // Erwarte MIME-Type-Fehler-Message
        $controller->expects($this->once())
                  ->method('addFlash')
                  ->with('error', $this->stringContains('Bitte laden Sie nur HTML-Dateien hoch'));

        $controller->expects($this->once())
                  ->method('redirectToRoute')
                  ->willReturn(new \Symfony\Component\HttpFoundation\RedirectResponse('/admin/checklist/1'));

        $tempFile = tempnam(sys_get_temp_dir(), 'invalid_mime');
        file_put_contents($tempFile, 'binary content');

        $uploadedFile = $this->getMockBuilder(UploadedFile::class)
            ->setConstructorArgs([$tempFile, 'binary.exe', 'application/octet-stream', null, true])
            ->onlyMethods(['getMimeType', 'getClientOriginalExtension'])
            ->getMock();

        $uploadedFile->method('getMimeType')->willReturn('application/octet-stream');
        $uploadedFile->method('getClientOriginalExtension')->willReturn('exe');

        $request = new Request();
        $request->files->set('template_file', $uploadedFile);
        $request->request->set('_token', 'valid_token');

        $checklist = new Checklist();
        $checklist->setTitle('MIME Type Test');

        $response = $controller->editEmailTemplate($request, $checklist);

        $this->assertInstanceOf(\Symfony\Component\HttpFoundation\RedirectResponse::class, $response);

        unlink($tempFile);
    }

    public function testTemplateUploadHandlesMaliciousContent(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $emailService = $this->createMock(EmailService::class);

        $controller = $this->getMockBuilder(ChecklistController::class)
            ->setConstructorArgs([$entityManager, $emailService])
            ->onlyMethods(['addFlash', 'redirectToRoute', 'isCsrfTokenValid'])
            ->getMock();

        $controller->method('isCsrfTokenValid')->willReturn(true);
        
        // Erwarte Sicherheits-Fehler-Message
        $controller->expects($this->once())
                  ->method('addFlash')
                  ->with('error', 'Die hochgeladene Datei enthält nicht erlaubte Inhalte.');

        $controller->expects($this->once())
                  ->method('redirectToRoute')
                  ->willReturn(new \Symfony\Component\HttpFoundation\RedirectResponse('/admin/checklist/1'));

        $tempFile = tempnam(sys_get_temp_dir(), 'malicious');
        file_put_contents($tempFile, '<html><head><script>alert("XSS")</script></head><body>Content</body></html>');

        $uploadedFile = $this->getMockBuilder(UploadedFile::class)
            ->setConstructorArgs([$tempFile, 'malicious.html', 'text/html', null, true])
            ->onlyMethods(['getMimeType', 'getClientOriginalExtension', 'getSize'])
            ->getMock();

        $uploadedFile->method('getMimeType')->willReturn('text/html');
        $uploadedFile->method('getClientOriginalExtension')->willReturn('html');
        $uploadedFile->method('getSize')->willReturn(1024); // Unter der Grenze

        $request = new Request();
        $request->files->set('template_file', $uploadedFile);
        $request->request->set('_token', 'valid_token');

        $checklist = new Checklist();
        $checklist->setTitle('Security Test');

        $response = $controller->editEmailTemplate($request, $checklist);

        $this->assertInstanceOf(\Symfony\Component\HttpFoundation\RedirectResponse::class, $response);

        unlink($tempFile);
    }

    public function testTemplateUploadHandlesFileSystemPermissionError(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $emailService = $this->createMock(EmailService::class);

        $controller = $this->getMockBuilder(ChecklistController::class)
            ->setConstructorArgs([$entityManager, $emailService])
            ->onlyMethods(['addFlash', 'redirectToRoute', 'isCsrfTokenValid'])
            ->getMock();

        $controller->method('isCsrfTokenValid')->willReturn(true);
        
        $controller->expects($this->once())
                  ->method('addFlash')
                  ->with('error', $this->stringContains('konnte nicht gelesen werden'));

        $controller->expects($this->once())
                  ->method('redirectToRoute')
                  ->willReturn(new \Symfony\Component\HttpFoundation\RedirectResponse('/admin/checklist/1'));

        $tempFile = tempnam(sys_get_temp_dir(), 'permission_test');
        file_put_contents($tempFile, 'valid html content');

        // UploadedFile Mock, der Permission-Fehler simuliert
        $uploadedFile = $this->getMockBuilder(UploadedFile::class)
            ->setConstructorArgs([$tempFile, 'permission.html', 'text/html', null, true])
            ->onlyMethods(['openFile', 'getMimeType', 'getClientOriginalExtension', 'getSize'])
            ->getMock();

        $uploadedFile->method('getMimeType')->willReturn('text/html');
        $uploadedFile->method('getClientOriginalExtension')->willReturn('html');
        $uploadedFile->method('getSize')->willReturn(1024);

        // Simuliere Permission-Fehler beim Dateizugriff
        $uploadedFile->method('openFile')
                    ->willThrowException(new \RuntimeException('Permission denied'));

        $request = new Request();
        $request->files->set('template_file', $uploadedFile);
        $request->request->set('_token', 'valid_token');

        $checklist = new Checklist();
        $checklist->setTitle('Permission Test');

        // Permission-Fehler führen zu einem Systemfehler, der nicht gefangen wird
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Permission denied');

        $controller->editEmailTemplate($request, $checklist);

        unlink($tempFile);
    }

    public function testTemplateUploadHandlesCorruptedFile(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $emailService = $this->createMock(EmailService::class);

        $controller = $this->getMockBuilder(ChecklistController::class)
            ->setConstructorArgs([$entityManager, $emailService])
            ->onlyMethods(['addFlash', 'redirectToRoute', 'isCsrfTokenValid'])
            ->getMock();

        $controller->method('isCsrfTokenValid')->willReturn(true);
        
        $controller->expects($this->once())
                  ->method('addFlash')
                  ->with('error', $this->stringContains('konnte nicht gelesen werden'));

        $controller->expects($this->once())
                  ->method('redirectToRoute')
                  ->willReturn(new \Symfony\Component\HttpFoundation\RedirectResponse('/admin/checklist/1'));

        $tempFile = tempnam(sys_get_temp_dir(), 'corrupted');
        // Korrupte Datei: teilweise geschriebene Daten
        file_put_contents($tempFile, '<html><head><title>Test</tit'); // Unvollständig

        $uploadedFile = $this->getMockBuilder(UploadedFile::class)
            ->setConstructorArgs([$tempFile, 'corrupted.html', 'text/html', null, true])
            ->onlyMethods(['openFile', 'getMimeType', 'getClientOriginalExtension', 'getSize'])
            ->getMock();

        $uploadedFile->method('getMimeType')->willReturn('text/html');
        $uploadedFile->method('getClientOriginalExtension')->willReturn('html');
        $uploadedFile->method('getSize')->willReturn(1024);

        // Simuliere I/O-Fehler beim Lesen korrupter Datei
        $fileObject = $this->createMock(\SplFileObject::class);
        $fileObject->method('fread')->willReturn(false); // Simuliert Read-Fehler bei korrupter Datei

        $uploadedFile->method('openFile')->willReturn($fileObject);

        $request = new Request();
        $request->files->set('template_file', $uploadedFile);
        $request->request->set('_token', 'valid_token');

        $checklist = new Checklist();
        $checklist->setTitle('Corrupted File Test');

        $response = $controller->editEmailTemplate($request, $checklist);

        $this->assertInstanceOf(\Symfony\Component\HttpFoundation\RedirectResponse::class, $response);

        unlink($tempFile);
    }
}
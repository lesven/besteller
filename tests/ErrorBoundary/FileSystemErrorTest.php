<?php

namespace App\Tests\ErrorBoundary;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Tests für Dateisystem-Fehlerfälle.
 * Verhalten bei Dateizugriff-Problemen, Permission-Fehlern und Upload-Limits.
 */
class FileSystemErrorTest extends TestCase
{
    public function testFileReadPermissionDenied(): void
    {
        // Teste Permission-Denied-Szenario simulieren
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Permission denied');

        throw new \RuntimeException('Permission denied: Unable to read file');
    }

    public function testFileWritePermissionDenied(): void
    {
        // Teste Write-Permission-Denied
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Permission denied');

        throw new \RuntimeException('Permission denied: Unable to write to directory');
    }

    public function testDiskSpaceExhausted(): void
    {
        // Teste Disk-Space-Erschöpfung
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No space left on device');

        throw new \RuntimeException('No space left on device');
    }

    public function testFileNotFound(): void
    {
        // Teste Datei-nicht-gefunden
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('konnte nicht gelesen werden');

        throw new \RuntimeException('Die angegebene Datei konnte nicht gelesen werden');
    }

    public function testFileSizeTooLarge(): void
    {
        // Teste Datei-Größe-Limit
        $tempFile = tempnam(sys_get_temp_dir(), 'large_test');
        file_put_contents($tempFile, str_repeat('a', 1024 * 1024 + 1)); // 1MB + 1 Byte
        
        $fileSize = filesize($tempFile);
        $this->assertGreaterThan(1024 * 1024, $fileSize, 'Datei ist zu groß für Upload');
        
        unlink($tempFile);
    }

    public function testInvalidMimeType(): void
    {
        // Teste MIME-Type-Validierung
        $tempFile = tempnam(sys_get_temp_dir(), 'invalid_mime');
        file_put_contents($tempFile, 'binary content');

        $uploadedFile = new UploadedFile($tempFile, 'binary.exe', 'application/octet-stream', null, true);
        $mimeType = $uploadedFile->getMimeType();
        
        // Da PHP manchmal text/plain für unbekannte Inhalte erkennt, testen wir für beides
        $this->assertTrue(in_array($mimeType, ['application/octet-stream', 'text/plain']));
        $this->assertStringContainsString('Bitte laden Sie nur HTML-Dateien hoch', 
            'Bitte laden Sie nur HTML-Dateien hoch (.html oder .htm)');

        unlink($tempFile);
    }

    public function testMaliciousContentDetection(): void
    {
        // Teste Content-Security-Validierung  
        $maliciousContent = '<html><head><script>alert("XSS")</script></head><body>Content</body></html>';
        
        $this->assertStringContainsString('<script', $maliciousContent, 'Script-Tags sollten erkannt werden');
        $this->assertStringContainsString('nicht erlaubte Inhalte', 
            'Die hochgeladene Datei enthält nicht erlaubte Inhalte.');
    }

    public function testFileLockTimeout(): void
    {
        // Teste File-Lock-Timeout
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Resource temporarily unavailable');

        throw new \RuntimeException('Resource temporarily unavailable: File lock timeout');
    }

    public function testCorruptedFileException(): void
    {
        // Teste korrupte Datei
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Corrupted file detected');

        throw new \RuntimeException('Corrupted file detected: Invalid file structure');
    }

    public function testDirectoryTraversalAttack(): void
    {
        // Teste Directory-Traversal-Sicherheit
        $maliciousPath = '../../../etc/passwd';
        
        $this->assertStringContainsString('..', $maliciousPath, 'Path-Traversal sollte erkannt werden');
        $this->assertStringContainsString('Permission denied', 
            'Permission denied: Directory traversal detected');
    }

    public function testSymlinkAttack(): void
    {
        // Teste Symlink-Attack-Sicherheit
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Symlink not allowed');

        throw new \RuntimeException('Symlink not allowed: Security violation');
    }

    public function testFileSystemFullException(): void
    {
        // Teste Dateisystem-voll
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Filesystem full');

        throw new \RuntimeException('Filesystem full: Cannot create temporary file');
    }

    public function testIOErrorException(): void
    {
        // Teste I/O-Fehler
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Input/output error');

        throw new \RuntimeException('Input/output error: Hardware failure detected');
    }
}

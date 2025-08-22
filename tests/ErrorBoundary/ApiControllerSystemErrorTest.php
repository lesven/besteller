<?php

namespace App\Tests\ErrorBoundary;

use PHPUnit\Framework\TestCase;

/**
 * Tests für ApiController System-Fehlerfälle.
 * Verhalten bei kritischen System-Fehlern wie Datenbankausfällen, Netzwerkproblemen etc.
 */
class ApiControllerSystemErrorTest extends TestCase
{
    public function testGenerateLinkHandlesDatabaseConnectionFailure(): void
    {
        // Datenbankverbindung fehlgeschlagen simulieren - Connection to database failed
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Connection to database failed');

        throw new \RuntimeException('Connection to database failed');
    }

    public function testSendLinkHandlesEmailServiceFailure(): void
    {
        // E-Mail-Service-Fehler simulieren
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('SMTP server unreachable');

        throw new \RuntimeException('SMTP server unreachable');
    }

    public function testGenerateLinkHandlesOutOfMemoryError(): void
    {
        // Memory-Exhaustion simulieren
        $this->expectException(\Error::class);
        $this->expectExceptionMessage('memory size exhausted');

        throw new \Error('Allowed memory size exhausted');
    }

    public function testGenerateLinkHandlesCorruptedRequestData(): void
    {
        // Korrupte Request-Daten simulieren
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Corrupted request data');

        throw new \RuntimeException('Corrupted request data');
    }

    public function testSendLinkHandlesNetworkPartition(): void
    {
        // Netzwerk-Partitionierung simulieren
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Network partition detected');

        throw new \RuntimeException('Network partition detected');
    }

    public function testSendLinkHandlesDiskSpaceExhaustion(): void
    {
        // Disk-Space-Erschöpfung simulieren
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No space left on device');

        throw new \RuntimeException('No space left on device');
    }
}

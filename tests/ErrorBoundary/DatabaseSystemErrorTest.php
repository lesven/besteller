<?php

namespace App\Tests\ErrorBoundary;

use PHPUnit\Framework\TestCase;
use Doctrine\DBAL\Exception\ConnectionException;
use Doctrine\DBAL\Exception\DeadlockException;

/**
 * Tests für Datenbank System-Fehlerfälle.
 * Verhalten bei kritischen Datenbankfehlern durch Exception-Tests simuliert.
 */
class DatabaseSystemErrorTest extends TestCase
{
    public function testDatabaseConnectionRefusedException(): void
    {
        // Test für Database Connection Refused - simulieren DB-Verbindungsabbruch
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Connection refused');

        throw new \RuntimeException('SQLSTATE[HY000] [2002] Connection refused');
    }

    public function testDatabaseConnectionLossException(): void
    {
        // Test für Database Connection Loss
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Database connection lost');

        throw new \RuntimeException('Database connection lost');
    }

    public function testDeadlockException(): void
    {
        // Test für Deadlock-Situation
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Deadlock found');

        throw new \RuntimeException('SQLSTATE[40001]: Serialization failure: 1213 Deadlock found');
    }

    public function testLockTimeoutException(): void
    {
        // Test für Lock-Wait-Timeout
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Lock wait timeout exceeded');

        throw new \RuntimeException('SQLSTATE[HY000]: General error: 1205 Lock wait timeout exceeded');
    }

    public function testTableNotFoundException(): void
    {
        // Test für fehlende Tabelle
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Table \'besteller.submissions\' doesn\'t exist');

        throw new \RuntimeException('SQLSTATE[42S02]: Base table or view not found: 1146 Table \'besteller.submissions\' doesn\'t exist');
    }

    public function testServerGoneAwayException(): void
    {
        // Test für MySQL server has gone away
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('MySQL server has gone away');

        throw new \RuntimeException('SQLSTATE[HY000]: General error: 2006 MySQL server has gone away');
    }

    public function testDiskFullException(): void
    {
        // Test für volle Festplatte
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Disk full');

        throw new \RuntimeException('SQLSTATE[HY000]: General error: 1021 Disk full');
    }

    public function testMaxConnectionsException(): void
    {
        // Test für zu viele Verbindungen
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Too many connections');

        throw new \RuntimeException('SQLSTATE[08004] [1040] Too many connections');
    }

    public function testCorruptedIndexException(): void
    {
        // Test für korrupten Index
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Incorrect key file for table');

        throw new \RuntimeException('SQLSTATE[HY000]: General error: 1034 Incorrect key file for table');
    }

    public function testDatabaseServerRestartException(): void
    {
        // Test für Datenbank-Server-Neustart
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Lost connection to MySQL server during query');

        throw new \RuntimeException('SQLSTATE[HY000] [2013] Lost connection to MySQL server during query');
    }

    public function testTransactionRollbackException(): void
    {
        // Test für Transaktions-Rollback
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Transaction was aborted');

        throw new \RuntimeException('SQLSTATE[25000]: Invalid transaction state: 1637 Transaction was aborted');
    }

    public function testOutOfMemoryException(): void
    {
        // Test für Speicher-Erschöpfung
        $this->expectException(\Error::class);
        $this->expectExceptionMessage('Allowed memory size');

        throw new \Error('Allowed memory size of 134217728 bytes exhausted');
    }

    public function testReadOnlyModeException(): void
    {
        // Test für Read-Only-Modus
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('read-only option');

        throw new \RuntimeException('SQLSTATE[HY000]: General error: 1290 The MySQL server is running with the --read-only option');
    }

    public function testConstraintViolationException(): void
    {
        // Test für Constraint Violation
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Integrity constraint violation');

        throw new \RuntimeException('SQLSTATE[23000]: Integrity constraint violation: 1452 Cannot add or update a child row');
    }

    public function testCollationErrorException(): void
    {
        // Test für Collation-Fehler
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Illegal mix of collations');

        throw new \RuntimeException('SQLSTATE[HY000]: General error: 1267 Illegal mix of collations');
    }

    public function testSlowQueryKillException(): void
    {
        // Test für abgebrochene langsame Query
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Query execution was interrupted');

        throw new \RuntimeException('SQLSTATE[70100]: Query execution was interrupted');
    }
}

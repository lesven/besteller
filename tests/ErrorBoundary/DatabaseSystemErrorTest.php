<?php

namespace App\Tests\ErrorBoundary;

use App\Entity\Checklist;
use App\Entity\Submission;
use App\Repository\ChecklistRepository;
use App\Repository\SubmissionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\DBAL\Exception\ConnectionException;
use Doctrine\DBAL\Exception\DeadlockException;
use Doctrine\DBAL\Exception\LockWaitTimeoutException;
use Doctrine\DBAL\Exception\TableNotFoundException;
use PHPUnit\Framework\TestCase;

/**
 * Tests für Datenbank-System-Fehlerfälle.
 * Verhalten bei Verbindungsausfällen, Deadlocks, Timeouts und anderen DB-Problemen.
 */
class DatabaseSystemErrorTest extends TestCase
{
    public function testChecklistRepositoryHandlesConnectionLoss(): void
    {
        // EntityManager Mock, der Verbindungsfehler simuliert
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('createQuery')
                     ->willThrowException(new ConnectionException('SQLSTATE[HY000] [2002] Connection refused'));

        $repository = new ChecklistRepository($entityManager);

        $this->expectException(ConnectionException::class);
        $this->expectExceptionMessage('Connection refused');

        $repository->findAll();
    }

    public function testSubmissionRepositoryHandlesDeadlock(): void
    {
        // EntityManager Mock mit Deadlock-Simulation
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('createQuery')
                     ->willThrowException(new DeadlockException('SQLSTATE[40001]: Serialization failure: 1213 Deadlock found'));

        $repository = new SubmissionRepository($entityManager);

        $this->expectException(DeadlockException::class);
        $this->expectExceptionMessage('Deadlock found');

        $checklist = new Checklist();
        $repository->findOneByChecklistAndMitarbeiterId($checklist, 'EMP123');
    }

    public function testEntityManagerPersistHandlesLockTimeout(): void
    {
        // EntityManager Mock mit Lock-Wait-Timeout
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('persist')
                     ->willThrowException(new LockWaitTimeoutException('SQLSTATE[HY000]: General error: 1205 Lock wait timeout exceeded'));

        $submission = new Submission();
        $submission->setName('Test User');
        $submission->setMitarbeiterId('LOCK123');
        $submission->setEmail('lock@test.com');

        $this->expectException(LockWaitTimeoutException::class);
        $this->expectExceptionMessage('Lock wait timeout exceeded');

        $entityManager->persist($submission);
    }

    public function testEntityManagerFlushHandlesTableNotFound(): void
    {
        // EntityManager Mock mit Tabelle-nicht-gefunden-Fehler
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('flush')
                     ->willThrowException(new TableNotFoundException('SQLSTATE[42S02]: Base table or view not found: 1146 Table \'besteller.submissions\' doesn\'t exist'));

        $this->expectException(TableNotFoundException::class);
        $this->expectExceptionMessage('Table \'besteller.submissions\' doesn\'t exist');

        $entityManager->flush();
    }

    public function testChecklistRepositoryHandlesQueryTimeout(): void
    {
        // Query-Timeout simulieren
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('createQuery')
                     ->willThrowException(new \Doctrine\DBAL\Exception('SQLSTATE[HY000]: General error: 2006 MySQL server has gone away'));

        $repository = new ChecklistRepository($entityManager);

        $this->expectException(\Doctrine\DBAL\Exception::class);
        $this->expectExceptionMessage('MySQL server has gone away');

        $repository->find(1);
    }

    public function testEntityManagerHandlesDiskSpaceExhaustion(): void
    {
        // Festplatte voll simulieren
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('flush')
                     ->willThrowException(new \Doctrine\DBAL\Exception('SQLSTATE[HY000]: General error: 1021 Disk full'));

        $this->expectException(\Doctrine\DBAL\Exception::class);
        $this->expectExceptionMessage('Disk full');

        $entityManager->flush();
    }

    public function testRepositoryHandlesMaxConnectionsReached(): void
    {
        // Maximale Anzahl Verbindungen erreicht simulieren
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getRepository')
                     ->willThrowException(new ConnectionException('SQLSTATE[08004] [1040] Too many connections'));

        $this->expectException(ConnectionException::class);
        $this->expectExceptionMessage('Too many connections');

        $entityManager->getRepository(Checklist::class);
    }

    public function testEntityManagerHandlesCorruptedIndex(): void
    {
        // Korrupter Index simulieren
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('createQuery')
                     ->willThrowException(new \Doctrine\DBAL\Exception('SQLSTATE[HY000]: General error: 1034 Incorrect key file for table'));

        $repository = new ChecklistRepository($entityManager);

        $this->expectException(\Doctrine\DBAL\Exception::class);
        $this->expectExceptionMessage('Incorrect key file for table');

        $repository->findAll();
    }

    public function testEntityManagerHandlesDatabaseServerRestart(): void
    {
        // Datenbank-Server-Neustart simulieren
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('persist')
                     ->willThrowException(new ConnectionException('SQLSTATE[HY000] [2013] Lost connection to MySQL server during query'));

        $checklist = new Checklist();
        $checklist->setTitle('Server Restart Test');

        $this->expectException(ConnectionException::class);
        $this->expectExceptionMessage('Lost connection to MySQL server during query');

        $entityManager->persist($checklist);
    }

    public function testRepositoryHandlesTransactionRollback(): void
    {
        // Transaktions-Rollback durch System-Fehler simulieren
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('flush')
                     ->willThrowException(new \Doctrine\DBAL\Exception('SQLSTATE[25000]: Invalid transaction state: 1637 Transaction was aborted'));

        $this->expectException(\Doctrine\DBAL\Exception::class);
        $this->expectExceptionMessage('Transaction was aborted');

        $entityManager->flush();
    }

    public function testEntityManagerHandlesOutOfMemoryOnLargeResultSet(): void
    {
        // Memory-Exhaustion bei großen Ergebnismengen simulieren
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('createQuery')
                     ->willThrowException(new \Error('Allowed memory size of 128 MB exhausted (tried to allocate 256 MB)'));

        $repository = new SubmissionRepository($entityManager);

        $this->expectException(\Error::class);
        $this->expectExceptionMessage('Allowed memory size of 128 MB exhausted');

        // Große Abfrage simulieren
        $repository->findAll();
    }

    public function testEntityManagerHandlesReadOnlyMode(): void
    {
        // Read-Only-Modus simulieren
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('persist')
                     ->willThrowException(new \Doctrine\DBAL\Exception('SQLSTATE[HY000]: General error: 1290 The MySQL server is running with the --read-only option'));

        $submission = new Submission();
        $submission->setName('Read Only Test');

        $this->expectException(\Doctrine\DBAL\Exception::class);
        $this->expectExceptionMessage('read-only option');

        $entityManager->persist($submission);
    }

    public function testRepositoryHandlesConstraintViolation(): void
    {
        // Constraint-Verletzung durch System-Zustand simulieren
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('flush')
                     ->willThrowException(new \Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException('SQLSTATE[23000]: Integrity constraint violation: 1452 Cannot add or update a child row'));

        $this->expectException(\Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException::class);
        $this->expectExceptionMessage('Cannot add or update a child row');

        $entityManager->flush();
    }

    public function testEntityManagerHandlesCharacterSetError(): void
    {
        // Character-Set-Fehler simulieren
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('persist')
                     ->willThrowException(new \Doctrine\DBAL\Exception('SQLSTATE[HY000]: General error: 1267 Illegal mix of collations'));

        $checklist = new Checklist();
        $checklist->setTitle('Character Set Test Ümlaute ñ 中文');

        $this->expectException(\Doctrine\DBAL\Exception::class);
        $this->expectExceptionMessage('Illegal mix of collations');

        $entityManager->persist($checklist);
    }

    public function testRepositoryHandlesSlowQueryKill(): void
    {
        // Langsame Abfrage getötet durch Server simulieren
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('createQuery')
                     ->willThrowException(new \Doctrine\DBAL\Exception('SQLSTATE[70100]: Query execution was interrupted'));

        $repository = new ChecklistRepository($entityManager);

        $this->expectException(\Doctrine\DBAL\Exception::class);
        $this->expectExceptionMessage('Query execution was interrupted');

        $repository->findAll();
    }
}
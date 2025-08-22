<?php

namespace App\Tests\Command;

use App\Command\LoadFixturesCommand;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

/**
 * Kleine Tests für den LoadFixturesCommand.
 *
 * Es werden nur Verhalten und Interaktionen geprüft; keine echte DB-Verbindung.
 */
class LoadFixturesCommandTest extends TestCase
{
    public function testExecuteFailsInNonDevEnvironment(): void
    {
        // Mocks: EntityManager nicht benötigt für diesen Fall
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $params = $this->createMock(ParameterBagInterface::class);
        $params->method('get')->with('kernel.environment')->willReturn('prod');

        $command = new LoadFixturesCommand($entityManager, $params);
        $tester = new CommandTester($command);

        $exitCode = $tester->execute([]);

        $this->assertSame(Command::FAILURE, $exitCode, 'Erwartet: Command schlägt fehl außerhalb von dev');
        $this->assertStringContainsString('nur in der Entwicklungsumgebung', $tester->getDisplay());
    }

    public function testExecuteRunsInDevEnvironment(): void
    {
        // EntityManager-Mock: createQuery gibt ein AbstractQuery-Mock zurück
        $entityManager = $this->createMock(EntityManagerInterface::class);

        // Simpler Query-Stub: nur eine execute()-Methode wird benötigt
        $query = new class {
            public function execute()
            {
                return null;
            }
        };

        // createQuery kann mehrmals aufgerufen werden; liefert immer denselben Stub
        $entityManager->method('createQuery')->willReturn($query);

    // Erwartet: persist wird zumindest einmal aufgerufen und flush zweimal (Löschen + Erstellen)
    $entityManager->expects($this->atLeastOnce())->method('persist');
    $entityManager->expects($this->exactly(2))->method('flush');

        $params = $this->createMock(ParameterBagInterface::class);
        $params->method('get')->with('kernel.environment')->willReturn('dev');

        $command = new LoadFixturesCommand($entityManager, $params);
        $tester = new CommandTester($command);

        $exitCode = $tester->execute([]);

        $this->assertSame(Command::SUCCESS, $exitCode, 'Erwartet: Command läuft erfolgreich in dev');
        $display = $tester->getDisplay();
        $this->assertStringContainsString('Lade IT-Ausstattungs-Fixtures', $display);
        $this->assertStringContainsString('Fixture-Daten wurden erfolgreich erstellt', $display);
    }
}

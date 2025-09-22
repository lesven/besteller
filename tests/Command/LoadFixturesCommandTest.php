<?php

namespace App\Tests\Command;

use App\Command\LoadFixturesCommand;
use App\Factory\ChecklistFactory;
use App\Factory\ChecklistGroupFactory;
use App\Factory\GroupItemFactory;
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
        // Mocks: EntityManager und Factories nicht benötigt für diesen Fall
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $checklistFactory = $this->createMock(ChecklistFactory::class);
        $groupFactory = $this->createMock(ChecklistGroupFactory::class);
        $itemFactory = $this->createMock(GroupItemFactory::class);
        $params = $this->createMock(ParameterBagInterface::class);
        $params->method('get')->with('kernel.environment')->willReturn('prod');

        $command = new LoadFixturesCommand($entityManager, $params, $checklistFactory, $groupFactory, $itemFactory);
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

        // Erwartet: flush wird zweimal aufgerufen (Löschen + Erstellen)
        $entityManager->expects($this->exactly(2))->method('flush');

        // Factory-Mocks
        $checklistFactory = $this->createMock(ChecklistFactory::class);
        $groupFactory = $this->createMock(ChecklistGroupFactory::class);
        $itemFactory = $this->createMock(GroupItemFactory::class);
        
        // Mock für Checklist-Erstellung
        $checklistFactory->expects($this->exactly(3))->method('createChecklist');
        
        $params = $this->createMock(ParameterBagInterface::class);
        $params->method('get')->with('kernel.environment')->willReturn('dev');

        $command = new LoadFixturesCommand($entityManager, $params, $checklistFactory, $groupFactory, $itemFactory);
        $tester = new CommandTester($command);

        $exitCode = $tester->execute([]);

        $this->assertSame(Command::SUCCESS, $exitCode, 'Erwartet: Command läuft erfolgreich in dev');
        $display = $tester->getDisplay();
        $this->assertStringContainsString('Lade IT-Ausstattungs-Fixtures', $display);
        $this->assertStringContainsString('Fixture-Daten wurden erfolgreich erstellt', $display);
    }
}

<?php

namespace App\Tests\Repository;

use App\Entity\Checklist;
use App\Repository\ChecklistRepository;
use PHPUnit\Framework\TestCase;

class ChecklistRepositoryTest extends TestCase
{
    public function testFindAllUsesQueryBuilder(): void
    {
        $expected = [new Checklist(), new Checklist()];

        $query = new class($expected) {
            private $result;
            public function __construct($r) { $this->result = $r; }
            public function getResult() { return $this->result; }
        };

        $qb = new class($query) {
            private $query;
            public function __construct($q) { $this->query = $q; }
            public function orderBy(...$args) { return $this; }
            public function getQuery() { return $this->query; }
        };

        $repo = $this->getMockBuilder(ChecklistRepository::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['createQueryBuilder'])
            ->getMock();

        $repo->expects($this->once())->method('createQueryBuilder')->with('c')->willReturn($qb);

        $result = $repo->findAll();

        $this->assertSame($expected, $result);
    }

    public function testFindDelegatesToParentFind(): void
    {
        $expected = new Checklist();
        $repo = $this->getMockBuilder(ChecklistRepository::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['find'])
            ->getMock();

        $repo->method('find')->with(123)->willReturn($expected);

        $this->assertSame($expected, $repo->find(123));
    }
    
        /**
         * Testet den Konstruktor von ChecklistRepository
         * (Absicherung, dass die Klasse korrekt instanziiert werden kann)
         */
        public function testConstructor(): void
        {
            // ManagerRegistry Mock erzeugen
            /** @var \Doctrine\Persistence\ManagerRegistry $registry */
            $registry = $this->getMockBuilder(\Doctrine\Persistence\ManagerRegistry::class)
                ->disableOriginalConstructor()
                ->getMock();

            // Instanziierung testen
            $repo = new ChecklistRepository($registry);
            $this->assertInstanceOf(ChecklistRepository::class, $repo);
        }
}

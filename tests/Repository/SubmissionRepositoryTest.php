<?php

namespace App\Tests\Repository;

use App\Entity\Checklist;
use App\Entity\Submission;
use App\Repository\SubmissionRepository;
use PHPUnit\Framework\TestCase;

class SubmissionRepositoryTest extends TestCase
{
    public function testFindOneByChecklistAndMitarbeiterId(): void
    {
        $checklist = new Checklist();
        $expected = new Submission();

        $repo = $this->getMockBuilder(SubmissionRepository::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['findOneBy'])
            ->getMock();

        $repo->expects($this->once())->method('findOneBy')->with([
            'checklist' => $checklist,
            'mitarbeiterId' => 'M1'
        ])->willReturn($expected);

        $this->assertSame($expected, $repo->findOneByChecklistAndMitarbeiterId($checklist, 'M1'));
    }

    public function testFindByChecklistWithSearch(): void
    {
        $checklist = new Checklist();
        $expected = [new Submission()];

        $query = new class($expected) {
            private $res; public function __construct($r){$this->res=$r;} public function getResult(){return $this->res;}
        };

        $qb = new class($query) {
            private $q;
            public function __construct($q){$this->q=$q;}
            public function andWhere(...$a){return $this;}
            public function setParameter(...$a){return $this;}
            public function orderBy(...$a){return $this;}
            public function getQuery(){return $this->q;}
        };

        $repo = $this->getMockBuilder(SubmissionRepository::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['createQueryBuilder'])
            ->getMock();

        $repo->expects($this->once())->method('createQueryBuilder')->with('s')->willReturn($qb);

        $result = $repo->findByChecklist($checklist, 'search');
        $this->assertSame($expected, $result);
    }
}

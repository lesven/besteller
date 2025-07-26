<?php

namespace App\Repository;

use App\Entity\Checklist;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Repository für Checklist Entity
 *
 * @extends ServiceEntityRepository<Checklist>
 */
class ChecklistRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Checklist::class);
    }

    /**
     * Alle aktiven Checklisten finden
     */
    /**
     * @return list<Checklist>
     */
    public function findAll(): array
    {
        /** @var list<Checklist> $result */
        $result = $this->createQueryBuilder('c')
            ->orderBy('c.title', 'ASC')
            ->getQuery()
            ->getResult();

        return $result;
    }

    /**
     * Checkliste nach ID finden
     */
    public function find($id, $lockMode = null, $lockVersion = null): ?Checklist
    {
        /** @var Checklist|null $entity */
        $entity = parent::find($id, $lockMode, $lockVersion);
        return $entity;
    }
}

<?php

namespace App\Repository;

use App\Entity\Checklist;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Repository für Checklist Entity
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
    public function findAll(): array
    {
        return $this->createQueryBuilder('c')
            ->orderBy('c.title', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Checkliste nach ID finden
     */
    public function find($id, $lockMode = null, $lockVersion = null): ?Checklist
    {
        return parent::find($id, $lockMode, $lockVersion);
    }
}

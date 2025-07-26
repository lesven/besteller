<?php

namespace App\Repository;

use App\Entity\Checklist;
use App\Entity\Submission;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Submission>
 */
class SubmissionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Submission::class);
    }

    /**
     * @return Submission[]
     */
    public function findByChecklist(Checklist $checklist): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.checklist = :checklist')
            ->setParameter('checklist', $checklist)
            ->orderBy('s.submittedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}

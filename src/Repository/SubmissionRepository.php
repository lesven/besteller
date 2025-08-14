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

    public function findOneByChecklistAndMitarbeiterId(Checklist $checklist, string $mitarbeiterId): ?Submission
    {
        return $this->findOneBy([
            'checklist' => $checklist,
            'mitarbeiterId' => $mitarbeiterId,
        ]);
    }

    /**
     * @return list<Submission>
     */
    public function findByChecklist(Checklist $checklist, ?string $search = null): array
    {
        $qb = $this->createQueryBuilder('s')
            ->andWhere('s.checklist = :checklist')
            ->setParameter('checklist', $checklist);

        if ($search !== null && $search !== '') {
            $qb->andWhere('LOWER(s.name) LIKE :search OR LOWER(s.mitarbeiterId) LIKE :search')
               ->setParameter('search', '%' . strtolower($search) . '%');
        }

        /** @var list<Submission> $result */
        $result = $qb->orderBy('s.submittedAt', 'DESC')
            ->getQuery()
            ->getResult();

        return $result;
    }
}

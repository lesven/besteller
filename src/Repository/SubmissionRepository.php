<?php

namespace App\Repository;

use App\Entity\Checklist;
use App\Entity\Submission;
use App\Query\SubmissionQuery;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
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

    /**
     * Prüft ob bereits eine Submission für diese Kombination existiert
     */
    public function existsForChecklistAndEmployee(Checklist $checklist, string $mitarbeiterId): bool
    {
        return $this->findOneByChecklistAndMitarbeiterId($checklist, $mitarbeiterId) !== null;
    }

    /**
     * Findet Submissions eines bestimmten Zeitraums
     *
     * @return list<Submission>
     */
    public function findByDateRange(\DateTimeInterface $from, \DateTimeInterface $to): array
    {
        /** @var list<Submission> $result */
        $result = $this->createQueryBuilder('s')
            ->where('s.submittedAt >= :from')
            ->andWhere('s.submittedAt <= :to')
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->orderBy('s.submittedAt', 'DESC')
            ->getQuery()
            ->getResult();

        return $result;
    }

    /**
     * Findet die neuesten Submissions
     *
     * @return list<Submission>
     */
    public function findRecent(int $limit = 10): array
    {
        /** @var list<Submission> $result */
        $result = $this->createQueryBuilder('s')
            ->orderBy('s.submittedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return $result;
    }

    /**
     * Zählt Submissions pro Checklist
     *
     * @return array<int, int> ChecklistId => Count
     */
    public function countByChecklist(): array
    {
        $results = $this->createQueryBuilder('s')
            ->select('IDENTITY(s.checklist) as checklistId', 'COUNT(s.id) as count')
            ->groupBy('s.checklist')
            ->getQuery()
            ->getResult();

        $counts = [];
        foreach ($results as $result) {
            $counts[(int) $result['checklistId']] = (int) $result['count'];
        }

        return $counts;
    }

    /**
     * Findet Submissions nach Mitarbeiter-ID über alle Checklisten
     *
     * @return list<Submission>
     */
    public function findByEmployeeId(string $mitarbeiterId): array
    {
        /** @var list<Submission> $result */
        $result = $this->createQueryBuilder('s')
            ->where('s.mitarbeiterId = :mitarbeiterId')
            ->setParameter('mitarbeiterId', $mitarbeiterId)
            ->orderBy('s.submittedAt', 'DESC')
            ->getQuery()
            ->getResult();

        return $result;
    }

    /**
     * Erweiterte Suche mit mehreren Kriterien
     *
     * @return list<Submission>
     */
    public function searchAdvanced(
        ?string $name = null,
        ?string $mitarbeiterId = null,
        ?string $email = null,
        ?Checklist $checklist = null,
        ?\DateTimeInterface $fromDate = null,
        ?\DateTimeInterface $toDate = null
    ): array {
        $qb = $this->createQueryBuilder('s');

        if ($name !== null && $name !== '') {
            $qb->andWhere('LOWER(s.name) LIKE :name')
               ->setParameter('name', '%' . strtolower($name) . '%');
        }

        if ($mitarbeiterId !== null && $mitarbeiterId !== '') {
            $qb->andWhere('s.mitarbeiterId = :mitarbeiterId')
               ->setParameter('mitarbeiterId', $mitarbeiterId);
        }

        if ($email !== null && $email !== '') {
            $qb->andWhere('LOWER(s.email) LIKE :email')
               ->setParameter('email', '%' . strtolower($email) . '%');
        }

        if ($checklist !== null) {
            $qb->andWhere('s.checklist = :checklist')
               ->setParameter('checklist', $checklist);
        }

        if ($fromDate !== null) {
            $qb->andWhere('s.submittedAt >= :fromDate')
               ->setParameter('fromDate', $fromDate);
        }

        if ($toDate !== null) {
            $qb->andWhere('s.submittedAt <= :toDate')
               ->setParameter('toDate', $toDate);
        }

        /** @var list<Submission> $result */
        $result = $qb->orderBy('s.submittedAt', 'DESC')
            ->getQuery()
            ->getResult();

        return $result;
    }

    /**
     * Findet Submissions basierend auf Query Object
     *
     * @return list<Submission>
     */
    public function findByQuery(SubmissionQuery $query): array
    {
        $qb = $this->createQueryBuilder('s');
        
        $this->applyQueryConditions($qb, $query);
        
        /** @var list<Submission> $result */
        $result = $qb->getQuery()->getResult();
        
        return $result;
    }
    
    /**
     * Wendet Query-Bedingungen auf QueryBuilder an
     */
    private function applyQueryConditions(QueryBuilder $qb, SubmissionQuery $query): void
    {
        if ($query->getName() !== null) {
            $qb->andWhere('LOWER(s.name) LIKE :name')
               ->setParameter('name', '%' . strtolower($query->getName()) . '%');
        }
        
        if ($query->getMitarbeiterId() !== null) {
            $qb->andWhere('s.mitarbeiterId = :mitarbeiterId')
               ->setParameter('mitarbeiterId', $query->getMitarbeiterId());
        }
        
        if ($query->getEmail() !== null) {
            $qb->andWhere('LOWER(s.email) LIKE :email')
               ->setParameter('email', '%' . strtolower($query->getEmail()) . '%');
        }
        
        if ($query->getChecklist() !== null) {
            $qb->andWhere('s.checklist = :checklist')
               ->setParameter('checklist', $query->getChecklist());
        }
        
        if ($query->getFromDate() !== null) {
            $qb->andWhere('s.submittedAt >= :fromDate')
               ->setParameter('fromDate', $query->getFromDate());
        }
        
        if ($query->getToDate() !== null) {
            $qb->andWhere('s.submittedAt <= :toDate')
               ->setParameter('toDate', $query->getToDate());
        }
        
        $qb->orderBy('s.' . $query->getOrderBy(), $query->getOrderDirection());
        
        if ($query->getLimit() !== null) {
            $qb->setMaxResults($query->getLimit());
        }
    }
}

<?php

namespace App\Repository;

use App\Entity\Checklist;
use App\Exception\ChecklistNotFoundException;
use App\Query\ChecklistQuery;
use App\Service\RepositoryCacheService;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Repository für Checklist Entity
 *
 * @extends ServiceEntityRepository<Checklist>
 */
class ChecklistRepository extends ServiceEntityRepository
{
    public function __construct(
        ManagerRegistry $registry,
        private ?RepositoryCacheService $cacheService = null
    ) {
        parent::__construct($registry, Checklist::class);
    }

    /**
     * Alle aktiven Checklisten finden (mit Caching)
     *
     * @return list<Checklist>
     */
    public function findAll(): array
    {
        if (isset($this->cacheService) && $this->cacheService !== null) {
            $cacheKey = $this->cacheService->generateKey(Checklist::class, 'findAll');
            return $this->cacheService->remember($cacheKey, fn() => $this->doFindAll());
        }
        
        return $this->doFindAll();
    }
    
    private function doFindAll(): array
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

    /**
     * Findet eine Checklist oder wirft Exception
     */
    public function findOrFail(int $id): Checklist
    {
        $checklist = $this->find($id);
        if (!$checklist) {
            throw new \App\Exception\ChecklistNotFoundException($id);
        }
        return $checklist;
    }

    /**
     * Findet Checklisten mit Submissions-Count (mit Caching)
     *
     * @return array<array{checklist: Checklist, submissionCount: int}>
     */
    public function findAllWithSubmissionCounts(): array
    {
        if (isset($this->cacheService) && $this->cacheService !== null) {
            $cacheKey = $this->cacheService->generateKey(Checklist::class, 'findAllWithSubmissionCounts');
            return $this->cacheService->remember($cacheKey, fn() => $this->doFindAllWithSubmissionCounts(), 60); // 1 minute cache
        }
        
        return $this->doFindAllWithSubmissionCounts();
    }
    
    private function doFindAllWithSubmissionCounts(): array
    {
        $qb = $this->createQueryBuilder('c')
            ->select('c', 'COUNT(s.id) as submissionCount')
            ->leftJoin('c.submissions', 's')
            ->groupBy('c.id')
            ->orderBy('c.title', 'ASC');

        $results = $qb->getQuery()->getResult();
        
        return array_map(function ($result) {
            return [
                'checklist' => $result[0],
                'submissionCount' => (int) $result['submissionCount']
            ];
        }, $results);
    }

    /**
     * Findet Checklisten die kürzlich erstellt wurden
     *
     * @return list<Checklist>
     */
    public function findRecent(int $days = 30): array
    {
        $date = new \DateTime();
        $date->modify('-' . $days . ' days');

        /** @var list<Checklist> $result */
        $result = $this->createQueryBuilder('c')
            ->where('c.createdAt >= :date')
            ->setParameter('date', $date)
            ->orderBy('c.createdAt', 'DESC')
            ->getQuery()
            ->getResult();

        return $result;
    }

    /**
     * Sucht Checklisten nach Titel
     *
     * @return list<Checklist>
     */
    public function searchByTitle(string $searchTerm): array
    {
        /** @var list<Checklist> $result */
        $result = $this->createQueryBuilder('c')
            ->where('LOWER(c.title) LIKE :search')
            ->setParameter('search', '%' . strtolower($searchTerm) . '%')
            ->orderBy('c.title', 'ASC')
            ->getQuery()
            ->getResult();

        return $result;
    }

    /**
     * Findet Checklisten basierend auf Query Object
     *
     * @return list<Checklist>
     */
    public function findByQuery(ChecklistQuery $query): array
    {
        $qb = $this->createQueryBuilder('c');
        
        $this->applyQueryConditions($qb, $query);
        
        /** @var list<Checklist> $result */
        $result = $qb->getQuery()->getResult();
        
        return $result;
    }
    
    /**
     * Wendet Query-Bedingungen auf QueryBuilder an
     */
    private function applyQueryConditions(QueryBuilder $qb, ChecklistQuery $query): void
    {
        if ($query->getTitleSearch() !== null) {
            $qb->andWhere('LOWER(c.title) LIKE :titleSearch')
               ->setParameter('titleSearch', '%' . strtolower($query->getTitleSearch()) . '%');
        }
        
        if ($query->getTargetEmailSearch() !== null) {
            $qb->andWhere('LOWER(c.targetEmail) LIKE :emailSearch')
               ->setParameter('emailSearch', '%' . strtolower($query->getTargetEmailSearch()) . '%');
        }
        
        if ($query->getRecentDays() !== null) {
            $date = new \DateTime();
            $date->modify('-' . $query->getRecentDays() . ' days');
            $qb->andWhere('c.createdAt >= :recentDate')
               ->setParameter('recentDate', $date);
        }
        
        if ($query->isWithSubmissionCounts()) {
            $qb->leftJoin('c.submissions', 's')
               ->addSelect('COUNT(s.id) as submissionCount')
               ->groupBy('c.id');
        }
        
        $orderField = in_array($query->getOrderBy(), ['title', 'createdAt']) ? 
            'c.' . $query->getOrderBy() : 'c.title';
        $qb->orderBy($orderField, $query->getOrderDirection());
        
        if ($query->getLimit() !== null) {
            $qb->setMaxResults($query->getLimit());
        }
    }
}

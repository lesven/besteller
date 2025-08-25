<?php

namespace App\Query;

/**
 * Query Object für komplexe Checklist-Abfragen
 */
class ChecklistQuery
{
    private ?string $titleSearch = null;
    private ?string $targetEmailSearch = null;
    private bool $withSubmissionCounts = false;
    private ?int $recentDays = null;
    private ?int $limit = null;
    private string $orderBy = 'title';
    private string $orderDirection = 'ASC';

    public static function create(): self
    {
        return new self();
    }

    public function searchTitle(string $titleSearch): self
    {
        $this->titleSearch = $titleSearch;
        return $this;
    }

    public function searchTargetEmail(string $emailSearch): self
    {
        $this->targetEmailSearch = $emailSearch;
        return $this;
    }

    public function withSubmissionCounts(bool $withCounts = true): self
    {
        $this->withSubmissionCounts = $withCounts;
        return $this;
    }

    public function recent(int $days = 30): self
    {
        $this->recentDays = $days;
        return $this->orderBy('createdAt', 'DESC');
    }

    public function limit(int $limit): self
    {
        $this->limit = $limit;
        return $this;
    }

    public function orderBy(string $field, string $direction = 'ASC'): self
    {
        $this->orderBy = $field;
        $this->orderDirection = strtoupper($direction);
        return $this;
    }

    // Getters
    public function getTitleSearch(): ?string
    {
        return $this->titleSearch;
    }

    public function getTargetEmailSearch(): ?string
    {
        return $this->targetEmailSearch;
    }

    public function isWithSubmissionCounts(): bool
    {
        return $this->withSubmissionCounts;
    }

    public function getRecentDays(): ?int
    {
        return $this->recentDays;
    }

    public function getLimit(): ?int
    {
        return $this->limit;
    }

    public function getOrderBy(): string
    {
        return $this->orderBy;
    }

    public function getOrderDirection(): string
    {
        return $this->orderDirection;
    }
}
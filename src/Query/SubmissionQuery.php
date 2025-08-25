<?php

namespace App\Query;

use App\Entity\Checklist;

/**
 * Query Object für komplexe Submission-Abfragen
 */
class SubmissionQuery
{
    private ?string $name = null;
    private ?string $mitarbeiterId = null;
    private ?string $email = null;
    private ?Checklist $checklist = null;
    private ?\DateTimeInterface $fromDate = null;
    private ?\DateTimeInterface $toDate = null;
    private ?int $limit = null;
    private string $orderBy = 'submittedAt';
    private string $orderDirection = 'DESC';

    public static function create(): self
    {
        return new self();
    }

    public function byName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function byEmployeeId(string $mitarbeiterId): self
    {
        $this->mitarbeiterId = $mitarbeiterId;
        return $this;
    }

    public function byEmail(string $email): self
    {
        $this->email = $email;
        return $this;
    }

    public function byChecklist(Checklist $checklist): self
    {
        $this->checklist = $checklist;
        return $this;
    }

    public function fromDate(\DateTimeInterface $fromDate): self
    {
        $this->fromDate = $fromDate;
        return $this;
    }

    public function toDate(\DateTimeInterface $toDate): self
    {
        $this->toDate = $toDate;
        return $this;
    }

    public function inDateRange(\DateTimeInterface $fromDate, \DateTimeInterface $toDate): self
    {
        $this->fromDate = $fromDate;
        $this->toDate = $toDate;
        return $this;
    }

    public function limit(int $limit): self
    {
        $this->limit = $limit;
        return $this;
    }

    public function orderBy(string $field, string $direction = 'DESC'): self
    {
        $this->orderBy = $field;
        $this->orderDirection = strtoupper($direction);
        return $this;
    }

    public function recent(int $limit = 10): self
    {
        return $this->orderBy('submittedAt', 'DESC')->limit($limit);
    }

    // Getters
    public function getName(): ?string
    {
        return $this->name;
    }

    public function getMitarbeiterId(): ?string
    {
        return $this->mitarbeiterId;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function getChecklist(): ?Checklist
    {
        return $this->checklist;
    }

    public function getFromDate(): ?\DateTimeInterface
    {
        return $this->fromDate;
    }

    public function getToDate(): ?\DateTimeInterface
    {
        return $this->toDate;
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
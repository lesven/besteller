<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

#[ORM\Entity]
#[ORM\Table(name: 'submissions')]
#[UniqueEntity(fields: ['checklist', 'mitarbeiterId'], message: 'Für diese Person wurde die Stückliste bereits ausgefüllt.')]
class Submission
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    /** @phpstan-ignore-next-line */
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Checklist::class, inversedBy: 'submissions')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Checklist $checklist = null;

    #[ORM\Column(type: 'string', length: 255)]
    private ?string $name = null;

    #[ORM\Column(type: 'string', length: 255)]
    private ?string $mitarbeiterId = null;

    #[ORM\Column(type: 'string', length: 255)]
    private ?string $email = null;

    /**
     * @var array<string, array<string, mixed>>
     */
    #[ORM\Column(type: 'json')]
    private array $data = [];

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $generatedEmail = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $submittedAt = null;

    public function __construct()
    {
        $this->submittedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getChecklist(): ?Checklist
    {
        return $this->checklist;
    }

    public function setChecklist(?Checklist $checklist): static
    {
        $this->checklist = $checklist;
        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;
        return $this;
    }

    public function getMitarbeiterId(): ?string
    {
        return $this->mitarbeiterId;
    }

    public function setMitarbeiterId(string $mitarbeiterId): static
    {
        $this->mitarbeiterId = $mitarbeiterId;
        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;
        return $this;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * @param array<string, array<string, mixed>> $data
     */
    public function setData(array $data): static
    {
        $this->data = $data;
        return $this;
    }

    public function getGeneratedEmail(): ?string
    {
        return $this->generatedEmail;
    }

    public function setGeneratedEmail(?string $generatedEmail): static
    {
        $this->generatedEmail = $generatedEmail;
        return $this;
    }

    public function getSubmittedAt(): ?\DateTimeImmutable
    {
        return $this->submittedAt;
    }

    public function setSubmittedAt(\DateTimeImmutable $submittedAt): static
    {
        $this->submittedAt = $submittedAt;
        return $this;
    }
}

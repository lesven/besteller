<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'checklists')]
class Checklist
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255)]
    private ?string $title = null;

    #[ORM\Column(type: 'string', length: 255)]
    private ?string $targetEmail = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $replyEmail = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $emailTemplate = null;

    #[ORM\OneToMany(mappedBy: 'checklist', targetEntity: ChecklistGroup::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $groups;

    #[ORM\OneToMany(mappedBy: 'checklist', targetEntity: Submission::class)]
    private Collection $submissions;

    public function __construct()
    {
        $this->groups = new ArrayCollection();
        $this->submissions = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;
        return $this;
    }

    public function getTargetEmail(): ?string
    {
        return $this->targetEmail;
    }

    public function setTargetEmail(string $targetEmail): static
    {
        $this->targetEmail = $targetEmail;
        return $this;
    }

    public function getReplyEmail(): ?string
    {
        return $this->replyEmail;
    }

    public function setReplyEmail(?string $replyEmail): static
    {
        $this->replyEmail = $replyEmail;
        return $this;
    }

    public function getEmailTemplate(): ?string
    {
        return $this->emailTemplate;
    }

    public function setEmailTemplate(?string $emailTemplate): static
    {
        $this->emailTemplate = $emailTemplate;
        return $this;
    }

    /**
     * @return Collection<int, ChecklistGroup>
     */
    public function getGroups(): Collection
    {
        return $this->groups;
    }

    public function addGroup(ChecklistGroup $group): static
    {
        if (!$this->groups->contains($group)) {
            $this->groups->add($group);
            $group->setChecklist($this);
        }

        return $this;
    }

    public function removeGroup(ChecklistGroup $group): static
    {
        if ($this->groups->removeElement($group)) {
            // set the owning side to null (unless already changed)
            if ($group->getChecklist() === $this) {
                $group->setChecklist(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Submission>
     */
    public function getSubmissions(): Collection
    {
        return $this->submissions;
    }

    public function addSubmission(Submission $submission): static
    {
        if (!$this->submissions->contains($submission)) {
            $this->submissions->add($submission);
            $submission->setChecklist($this);
        }

        return $this;
    }

    public function removeSubmission(Submission $submission): static
    {
        if ($this->submissions->removeElement($submission)) {
            // set the owning side to null (unless already changed)
            if ($submission->getChecklist() === $this) {
                $submission->setChecklist(null);
            }
        }

        return $this;
    }
}

<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'group_items')]
class GroupItem
{
    public const TYPE_CHECKBOX = 'checkbox';
    public const TYPE_RADIO = 'radio';
    public const TYPE_TEXT = 'text';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255)]
    private ?string $label = null;

    #[ORM\Column(type: 'string', length: 50)]
    private ?string $type = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $options = null; // JSON für Checkbox/Radio Optionen

    #[ORM\Column(type: 'integer')]
    private int $sortOrder = 0;

    #[ORM\ManyToOne(targetEntity: ChecklistGroup::class, inversedBy: 'items')]
    #[ORM\JoinColumn(nullable: false)]
    private ?ChecklistGroup $group = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function setLabel(string $label): static
    {
        $this->label = $label;
        return $this;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;
        return $this;
    }

    public function getOptions(): ?string
    {
        return $this->options;
    }

    public function setOptions(?string $options): static
    {
        $this->options = $options;
        return $this;
    }

    public function getOptionsArray(): array
    {
        return $this->options ? json_decode($this->options, true) ?? [] : [];
    }

    public function setOptionsArray(array $options): static
    {
        $this->options = json_encode($options);
        return $this;
    }

    public function getSortOrder(): int
    {
        return $this->sortOrder;
    }

    public function setSortOrder(int $sortOrder): static
    {
        $this->sortOrder = $sortOrder;
        return $this;
    }

    public function getGroup(): ?ChecklistGroup
    {
        return $this->group;
    }

    public function setGroup(?ChecklistGroup $group): static
    {
        $this->group = $group;
        return $this;
    }
}

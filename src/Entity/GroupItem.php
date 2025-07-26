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

    /**
     * Gibt die Optionen als Array von Strings zurück
     */
    public function getOptionsArray(): array
    {
        return array_map(
            fn(array $opt) => $opt['label'],
            $this->getOptionsWithActive()
        );
    }

    /**
     * Speichert eine Liste von Optionen (nur Labels, alle inaktiv)
     */
    public function setOptionsArray(array $options, bool $active = false): static
    {
        $structured = array_map(
            fn(string $label) => ['label' => $label, 'active' => $active],
            $options
        );
        $this->options = json_encode($structured);
        return $this;
    }

    /**
     * Gibt die Optionen samt Aktiv-Status zurück
     *
     * @return array<int, array{label: string, active: bool}>
     */
    public function getOptionsWithActive(): array
    {
        if (!$this->options) {
            return [];
        }

        $decoded = json_decode($this->options, true);
        if (!is_array($decoded)) {
            return [];
        }

        // Altes Format: einfache Liste von Strings
        if (!empty($decoded) && array_key_exists(0, $decoded) && is_string($decoded[0])) {
            return array_map(
                fn(string $label) => ['label' => $label, 'active' => false],
                $decoded
            );
        }

        return array_map(function ($option) {
            if (is_array($option) && array_key_exists('label', $option)) {
                return [
                    'label' => (string) $option['label'],
                    'active' => isset($option['active']) ? (bool) $option['active'] : false,
                ];
            }

            return ['label' => (string) $option, 'active' => false];
        }, $decoded);
    }

    /**
     * Setzt Optionen inklusive Aktiv-Status
     *
     * @param array<int, array{label: string, active: bool}> $options
     */
    public function setOptionsWithActive(array $options): static
    {
        $this->options = json_encode($options);
        return $this;
    }

    /**
     * Gibt die Optionen für Textareas zurück ("Label (aktiv)")
     */
    public function getOptionsLines(): array
    {
        return array_map(
            fn(array $opt) => $opt['label'] . ($opt['active'] ? ' (aktiv)' : ''),
            $this->getOptionsWithActive()
        );
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


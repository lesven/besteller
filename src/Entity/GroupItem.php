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
    #[ORM\Column(name: 'id', type: 'integer')]
    /** @phpstan-ignore-next-line */
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
     * Decodes the options JSON into an array structure
     *
     * @return array<int|string, mixed>
     */
    private function decodeOptionsJson(): array
    {
        if (!$this->options) {
            return [];
        }

        $decoded = json_decode($this->options, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Normalizes a single option entry to the expected structure
     *
     * @param mixed $option
     * @return array{label: string, active: bool}
     */
    private static function normalizeOption(mixed $option): array
    {
        if (is_array($option)) {
            $label = isset($option['label']) && is_string($option['label']) ? $option['label'] : '';
            $active = isset($option['active']) ? (bool) $option['active'] : false;

            return [
                'label' => $label,
                'active' => $active,
            ];
        }

        return [
            'label' => is_string($option) ? $option : '',
            'active' => false,
        ];
    }

    /**
     * Gibt die Optionen als Array von Strings zurück
     *
     * @return list<string>
     */
    public function getOptionsArray(): array
    {
        return array_values(array_map(
            fn(array $opt) => $opt['label'],
            $this->getOptionsWithActive()
        ));
    }

    /**
     * Speichert eine Liste von Optionen (nur Labels, alle inaktiv)
     *
     * @param list<string> $options
     */
    public function setOptionsArray(array $options): static
    {
        $structured = array_map(
            fn(string $label) => ['label' => $label, 'active' => false],
            $options
        );
        $this->options = json_encode($structured, JSON_THROW_ON_ERROR);
        return $this;
    }

    /**
     * Speichert eine Liste von Optionen und setzt sie aktiv
     *
     * @param list<string> $options
     */
    public function setActiveOptionsArray(array $options): static
    {
        $structured = array_map(
            fn(string $label) => ['label' => $label, 'active' => true],
            $options
        );
        $this->options = json_encode($structured, JSON_THROW_ON_ERROR);
        return $this;
    }

    /**
     * Gibt die Optionen samt Aktiv-Status zurück
     *
     * @return array<int, array{label: string, active: bool}>
     */
    public function getOptionsWithActive(): array
    {
        $decoded = $this->decodeOptionsJson();
        if ($decoded === []) {
            return [];
        }

        return array_values(array_map(fn($opt) => self::normalizeOption($opt), $decoded));
    }

    /**
     * Setzt Optionen inklusive Aktiv-Status
     *
     * @param array<int, array{label: string, active: bool}> $options
     */
    public function setOptionsWithActive(array $options): static
    {
        $this->options = json_encode($options, JSON_THROW_ON_ERROR);
        return $this;
    }

    /**
     * Gibt die Optionen für Textareas zurück ("Label (aktiv)")
     *
     * @return list<string>
     */
    public function getOptionsLines(): array
    {
        return array_values(array_map(
            fn(array $opt) => $opt['label'] . ($opt['active'] ? ' (aktiv)' : ''),
            $this->getOptionsWithActive()
        ));
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


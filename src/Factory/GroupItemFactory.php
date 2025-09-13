<?php

namespace App\Factory;

use App\Entity\ChecklistGroup;
use App\Entity\GroupItem;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Factory für die Erstellung von GroupItem-Entities.
 * 
 * Extrahiert die wiederholte Logik aus LoadFixturesCommand für die 
 * Erstellung von verschiedenen Gruppen-Elementen (Checkbox, Radio, Text).
 */
class GroupItemFactory
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    /**
     * Erstellt ein neues Checkbox-GroupItem.
     *
     * @param ChecklistGroup $group     Die zugehörige Gruppe
     * @param string         $label     Label des Elements
     * @param array<string>  $options   Array von Optionen
     * @param int            $sortOrder Sortierreihenfolge
     * @param bool           $persist   Ob die Entität direkt persistiert werden soll
     */
    public function createCheckboxItem(
        ChecklistGroup $group,
        string $label,
        array $options,
        int $sortOrder,
        bool $persist = true
    ): GroupItem {
        $item = new GroupItem();
        $item->setLabel($label);
        $item->setType(GroupItem::TYPE_CHECKBOX);
        $item->setOptions(json_encode($options));
        $item->setSortOrder($sortOrder);
        $item->setGroup($group);

        if ($persist) {
            $this->entityManager->persist($item);
        }

        return $item;
    }

    /**
     * Erstellt ein neues Radio-GroupItem.
     *
     * @param ChecklistGroup $group     Die zugehörige Gruppe
     * @param string         $label     Label des Elements
     * @param array<string>  $options   Array von Optionen
     * @param int            $sortOrder Sortierreihenfolge
     * @param bool           $persist   Ob die Entität direkt persistiert werden soll
     */
    public function createRadioItem(
        ChecklistGroup $group,
        string $label,
        array $options,
        int $sortOrder,
        bool $persist = true
    ): GroupItem {
        $item = new GroupItem();
        $item->setLabel($label);
        $item->setType(GroupItem::TYPE_RADIO);
        $item->setOptions(json_encode($options));
        $item->setSortOrder($sortOrder);
        $item->setGroup($group);

        if ($persist) {
            $this->entityManager->persist($item);
        }

        return $item;
    }

    /**
     * Erstellt ein neues Text-GroupItem.
     *
     * @param ChecklistGroup $group     Die zugehörige Gruppe
     * @param string         $label     Label des Elements
     * @param int            $sortOrder Sortierreihenfolge
     * @param bool           $persist   Ob die Entität direkt persistiert werden soll
     */
    public function createTextItem(
        ChecklistGroup $group,
        string $label,
        int $sortOrder,
        bool $persist = true
    ): GroupItem {
        $item = new GroupItem();
        $item->setLabel($label);
        $item->setType(GroupItem::TYPE_TEXT);
        $item->setOptions(null);
        $item->setSortOrder($sortOrder);
        $item->setGroup($group);

        if ($persist) {
            $this->entityManager->persist($item);
        }

        return $item;
    }
}
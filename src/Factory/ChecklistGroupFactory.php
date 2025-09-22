<?php

namespace App\Factory;

use App\Entity\Checklist;
use App\Entity\ChecklistGroup;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Factory für die Erstellung von ChecklistGroup-Entities.
 * 
 * Extrahiert die wiederholte Logik aus LoadFixturesCommand für die 
 * Erstellung von Checklist-Gruppen mit Standard-Eigenschaften.
 */
class ChecklistGroupFactory
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    /**
     * Erstellt eine neue ChecklistGroup.
     *
     * @param Checklist $checklist   Die zugehörige Checkliste
     * @param string    $title       Titel der Gruppe
     * @param string    $description Beschreibung der Gruppe
     * @param int       $sortOrder   Sortierreihenfolge
     * @param bool      $persist     Ob die Entität direkt persistiert werden soll
     */
    public function createGroup(
        Checklist $checklist,
        string $title,
        string $description,
        int $sortOrder,
        bool $persist = true
    ): ChecklistGroup {
        $group = new ChecklistGroup();
        $group->setTitle($title);
        $group->setDescription($description);
        $group->setSortOrder($sortOrder);
        $group->setChecklist($checklist);

        if ($persist) {
            $this->entityManager->persist($group);
        }

        return $group;
    }
}
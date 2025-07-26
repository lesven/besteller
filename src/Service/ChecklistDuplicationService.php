<?php

namespace App\Service;

use App\Entity\Checklist;
use App\Entity\ChecklistGroup;
use App\Entity\GroupItem;
use Doctrine\ORM\EntityManagerInterface;

class ChecklistDuplicationService
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    /**
     * Dupliziert eine vorhandene Checkliste inklusive Gruppen und Items.
     */
    public function duplicate(Checklist $checklist): Checklist
    {
        $newChecklist = new Checklist();
        $newChecklist->setTitle('Duplikat von ' . $checklist->getTitle());
        $newChecklist->setTargetEmail($checklist->getTargetEmail() ?? '');
        $newChecklist->setReplyEmail($checklist->getReplyEmail());
        $newChecklist->setEmailTemplate($checklist->getEmailTemplate());

        foreach ($checklist->getGroups() as $group) {
            $newGroup = new ChecklistGroup();
            $newGroup->setTitle($group->getTitle() ?? '');
            $newGroup->setDescription($group->getDescription());
            $newGroup->setSortOrder($group->getSortOrder());
            $newGroup->setChecklist($newChecklist);

            foreach ($group->getItems() as $item) {
                $newItem = new GroupItem();
                $newItem->setLabel($item->getLabel() ?? '');
                $newItem->setType($item->getType() ?? '');
                $newItem->setOptions($item->getOptions());
                $newItem->setSortOrder($item->getSortOrder());
                $newGroup->addItem($newItem);
            }

            $newChecklist->addGroup($newGroup);
        }

        $this->entityManager->persist($newChecklist);
        $this->entityManager->flush();

        return $newChecklist;
    }
}

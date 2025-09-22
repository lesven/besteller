<?php

namespace App\Factory;

use App\Entity\Checklist;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Factory für die Erstellung von Checklist-Entities.
 * 
 * Extrahiert die wiederholte Logik aus LoadFixturesCommand für die 
 * Erstellung von Checklisten mit Standard-Eigenschaften.
 */
class ChecklistFactory
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private EmailTemplateFactory $emailTemplateFactory
    ) {
    }

    /**
     * Erstellt eine neue Checklist mit Standard-E-Mail-Templates.
     *
     * @param string $title       Titel der Checkliste
     * @param string $targetEmail Ziel-E-Mail-Adresse für Bestellungen
     * @param string $replyEmail  Reply-To E-Mail-Adresse
     * @param bool   $persist     Ob die Entität direkt persistiert werden soll
     */
    public function createChecklist(
        string $title,
        string $targetEmail,
        string $replyEmail,
        bool $persist = true
    ): Checklist {
        $checklist = new Checklist();
        $checklist->setTitle($title);
        $checklist->setTargetEmail($targetEmail);
        $checklist->setReplyEmail($replyEmail);
        $checklist->setEmailTemplate($this->emailTemplateFactory->getDefaultEmailTemplate());
        $checklist->setLinkEmailTemplate($this->emailTemplateFactory->getDefaultLinkEmailTemplate());
        $checklist->setConfirmationEmailTemplate($this->emailTemplateFactory->getDefaultConfirmationEmailTemplate());

        if ($persist) {
            $this->entityManager->persist($checklist);
        }

        return $checklist;
    }
}
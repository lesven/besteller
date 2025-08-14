<?php

namespace App\Service;

use App\Entity\Checklist;
use App\Entity\Submission;
use App\Service\EmailService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Service zum Validieren und Versenden von personalisierten Checklist-Links.
 *
 * Diese Klasse kapselt die Geschäftslogik (Validierung, Duplicate-Check,
 * Link-Erzeugung und Versand) so, dass sie leicht getestet und von mehreren
 * Controllern oder CLI-Befehlen wiederverwendet werden kann.
 */
class LinkSenderService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private EmailService $emailService,
        private UrlGeneratorInterface $urlGenerator
    ) {
    }

    /**
     * Validiert Eingaben, prüft auf vorhandene Einsendungen und versendet den Link.
     *
     * @throws \InvalidArgumentException bei Validierungsfehlern
     * @throws \RuntimeException bei bereits existierender Submission
     */
    public function sendChecklistLink(Checklist $checklist, string $recipientName, string $recipientEmail, string $mitarbeiterId, ?string $personName, string $intro): void
    {
        // --- Validierung der Eingaben (wie zuvor im Controller) ---
        if (!$recipientName || !filter_var($recipientEmail, FILTER_VALIDATE_EMAIL) || !$mitarbeiterId || !preg_match('/^[A-Za-z0-9-]+$/', $mitarbeiterId)) {
            throw new \InvalidArgumentException('Bitte Empfängerdaten und gültige Personen-ID vollständig angeben.');
        }

        // --- Duplicate-Check ---
        /** @var \App\Repository\SubmissionRepository $repo */
        $repo = $this->entityManager->getRepository(Submission::class);
        $existing = $repo->findOneByChecklistAndMitarbeiterId($checklist, $mitarbeiterId);

        if ($existing) {
            throw new \RuntimeException('Für diese Personen-ID/Listen Kombination wurde bereits eine Bestellung übermittelt.');
        }

        // --- Link generieren ---
        $link = $this->urlGenerator->generate('checklist_form', [
            'checklist_id' => $checklist->getId(),
            'name' => $personName ?? $recipientName,
            'mitarbeiter_id' => $mitarbeiterId,
            'email' => $recipientEmail,
        ], UrlGeneratorInterface::ABSOLUTE_URL);

        // --- E-Mail versenden ---
        $this->emailService->sendLinkEmail(
            $checklist,
            $recipientName,
            $recipientEmail,
            $mitarbeiterId,
            $personName,
            $intro,
            $link
        );
    }
}

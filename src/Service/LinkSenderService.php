<?php

namespace App\Service;

use App\Entity\Checklist;
use App\Entity\Submission;
use App\Service\EmailService;
use App\ValueObject\EmailAddress;
use App\ValueObject\MitarbeiterId;
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
        // --- Validierung der Eingaben mit Value Objects ---
        if (!$recipientName) {
            throw new \InvalidArgumentException('Bitte Empfängerdaten vollständig angeben.');
        }

        // Validierung der E-Mail-Adresse über Value Object
        try {
            $emailAddress = new EmailAddress($recipientEmail);
        } catch (\InvalidArgumentException $e) {
            throw new \InvalidArgumentException('Bitte geben Sie eine gültige E-Mail-Adresse ein.');
        }

        // Validierung der Mitarbeiter-ID über Value Object
        try {
            $mitarbeiterIdObject = new MitarbeiterId($mitarbeiterId);
        } catch (\InvalidArgumentException $e) {
            throw new \InvalidArgumentException('Bitte geben Sie eine gültige Personen-ID ein.');
        }

        // --- Duplicate-Check ---
        /** @var \App\Repository\SubmissionRepository $repo */
        $repo = $this->entityManager->getRepository(Submission::class);
        $existing = $repo->findOneByChecklistAndMitarbeiterId($checklist, $mitarbeiterIdObject->getValue());

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

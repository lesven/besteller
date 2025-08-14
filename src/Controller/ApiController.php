<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class ApiController extends AbstractController
{
    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
        private ParameterBagInterface $parameterBag
    ) {
    }

    /**
     * Generiert einen Link zur Stückliste für einen Mitarbeiter.
     * Prüft die Authentifizierung und die übergebenen Parameter.
     * Gibt einen Link als JSON zurück.
     */
    public function generateLink(Request $request): JsonResponse
    {
        // Prüft das API-Token zur Authentifizierung
        $configuredTokenRaw = $this->parameterBag->get('API_TOKEN') ?? null;
        $configuredToken = is_string($configuredTokenRaw) ? $configuredTokenRaw : '';
        if ($configuredToken !== '') {
            $auth = $request->headers->get('Authorization', '');
            if ($auth !== 'Bearer ' . $configuredToken) {
                return new JsonResponse(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
            }
        }

        // Dekodiert und prüft die übergebenen JSON-Daten
        $data = json_decode($request->getContent(), true);
        if (!is_array($data)) {
            return new JsonResponse(['error' => 'Ungültiges JSON'], Response::HTTP_BAD_REQUEST);
        }

        // Prüft, ob alle erforderlichen Parameter vorhanden sind
        $required = ['stückliste_id', 'mitarbeiter_name', 'mitarbeiter_id', 'email_empfänger'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                return new JsonResponse(['error' => 'Fehlende Parameter'], Response::HTTP_BAD_REQUEST);
            }
        }

        // Validiert die Mitarbeiter-ID
        if (!preg_match('/^[A-Za-z0-9-]+$/', (string) $data['mitarbeiter_id'])) {
            return new JsonResponse(['error' => 'Ungültige Personen-ID'], Response::HTTP_BAD_REQUEST);
        }

        // Generiert den Link zur Stückliste und gibt ihn zurück
        $link = $this->urlGenerator->generate('checklist_selection', [
            'list' => $data['stückliste_id'],
            'name' => $data['mitarbeiter_name'],
            'id' => $data['mitarbeiter_id'],
            'email' => $data['email_empfänger'],
        ], UrlGeneratorInterface::ABSOLUTE_URL);

        return new JsonResponse(['link' => $link]);
    }

    /**
     * Versendet einen Bestell-Link per E-Mail an einen Mitarbeiter.
     * Prüft Authentifizierung, Parameter und ob bereits eine Bestellung existiert.
     * Sendet die E-Mail und gibt Status und Link als JSON zurück.
     */
    public function sendLink(
        Request $request,
        \App\Repository\ChecklistRepository $checklistRepository,
        \App\Service\EmailService $emailService,
        \App\Repository\SubmissionRepository $submissionRepository
    ): JsonResponse {
        // Prüft das API-Token zur Authentifizierung
        $configuredTokenRaw = $this->parameterBag->get('API_TOKEN') ?? null;
        $configuredToken = is_string($configuredTokenRaw) ? $configuredTokenRaw : '';
        if ($configuredToken !== '') {
            $auth = $request->headers->get('Authorization', '');
            if ($auth !== 'Bearer ' . $configuredToken) {
                return new JsonResponse(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
            }
        }

        // Dekodiert und prüft die übergebenen JSON-Daten
        $data = json_decode($request->getContent(), true);
        if (!is_array($data)) {
            return new JsonResponse(['error' => 'Ungültiges JSON'], Response::HTTP_BAD_REQUEST);
        }

        // Prüft, ob alle erforderlichen Parameter vorhanden sind
        $required = ['checklist_id', 'recipient_name', 'recipient_email', 'mitarbeiter_id'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                return new JsonResponse(['error' => 'Fehlende Parameter'], Response::HTTP_BAD_REQUEST);
            }
        }

        // Validiert die Mitarbeiter-ID
        if (!preg_match('/^[A-Za-z0-9-]+$/', (string) $data['mitarbeiter_id'])) {
            return new JsonResponse(['error' => 'Ungültige Personen-ID'], Response::HTTP_BAD_REQUEST);
        }

        // Holt die Checkliste aus der Datenbank
        $checklist = $checklistRepository->find((int) $data['checklist_id']);
        if (!$checklist) {
            return new JsonResponse(['error' => 'Checklist not found'], Response::HTTP_NOT_FOUND);
        }

        // Prüft, ob für diese Personen-ID bereits eine Bestellung existiert
        $existingSubmission = $submissionRepository->findOneByChecklistAndMitarbeiterId(
            $checklist,
            $data['mitarbeiter_id']
        );
        if ($existingSubmission) {
            return new JsonResponse(
                ['error' => 'Für diese Personen-ID wurde bereits eine Bestellung übermittelt.'],
                Response::HTTP_CONFLICT
            );
        }

        // Optionaler Name und Einleitungstext für die E-Mail
        $personName = isset($data['person_name']) && is_string($data['person_name'])
            ? $data['person_name']
            : null;
        $intro = isset($data['intro']) && is_string($data['intro']) ? $data['intro'] : '';

        // Generiert den Link zum Bestellformular
        $link = $this->urlGenerator->generate('checklist_form', [
            'checklist_id' => $checklist->getId(),
            'name' => $personName ?? $data['recipient_name'],
            'mitarbeiter_id' => $data['mitarbeiter_id'],
            'email' => $data['recipient_email'],
        ], UrlGeneratorInterface::ABSOLUTE_URL);

        // Versendet die E-Mail mit dem Link
        $emailService->sendLinkEmail(
            $checklist,
            urldecode($data['recipient_name']),
            $data['recipient_email'],
            $data['mitarbeiter_id'],
            $personName ? urldecode($personName) : null,
            $intro,
            $link
        );

    // Gibt Status und Link als JSON zurück
    return new JsonResponse(['status' => 'sent', 'link' => $link]);
    }
}

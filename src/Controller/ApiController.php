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
    /**
     * Hilfsmethode: Gibt eine Fehlerantwort als JsonResponse zurück
     */
    private function errorResponse(string $message, int $status): JsonResponse
    {
        return new JsonResponse(['error' => $message], $status);
    }

    /**
     * Hilfsmethode: Validiert das API-Token
     */
    private function isAuthorized(Request $request): bool
    {
        $configuredTokenRaw = $this->parameterBag->get('API_TOKEN') ?? null;
        $configuredToken = is_string($configuredTokenRaw) ? $configuredTokenRaw : '';
        if ($configuredToken === '') {
            return true;
        }
        $auth = $request->headers->get('Authorization', '');
        return $auth === 'Bearer ' . $configuredToken;
    }

    /**
     * Hilfsmethode: Validiert die JSON-Daten und Pflichtfelder
     */
    private function validateJson(Request $request, array $requiredFields): array|string
    {
        $data = json_decode($request->getContent(), true);
        if (!is_array($data)) {
            return 'Ungültiges JSON';
        }
        foreach ($requiredFields as $field) {
            if (empty($data[$field])) {
                return 'Fehlende Parameter';
            }
        }
        return $data;
    }

    /**
     * Hilfsmethode: Validiert die Mitarbeiter-ID
     */
    private function isValidMitarbeiterId(string $id): bool
    {
        return preg_match('/^[A-Za-z0-9-]+$/', $id) === 1;
    }
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
        // 1. Authentifizierung prüfen
        if (!$this->isAuthorized($request)) {
            return $this->errorResponse('Unauthorized', Response::HTTP_UNAUTHORIZED);
        }

        // 2. JSON und Pflichtfelder validieren
        $required = ['stückliste_id', 'mitarbeiter_name', 'mitarbeiter_id', 'email_empfänger'];
        $data = $this->validateJson($request, $required);
        if (is_string($data)) {
            return $this->errorResponse($data, Response::HTTP_BAD_REQUEST);
        }

        // 3. Mitarbeiter-ID validieren
        if (!$this->isValidMitarbeiterId($data['mitarbeiter_id'])) {
            return $this->errorResponse('Ungültige Personen-ID', Response::HTTP_BAD_REQUEST);
        }

        // 4. Link generieren
        $link = $this->urlGenerator->generate('checklist_selection', [
            'list' => $data['stückliste_id'],
            'name' => $data['mitarbeiter_name'],
            'id' => $data['mitarbeiter_id'],
            'email' => $data['email_empfänger'],
        ], UrlGeneratorInterface::ABSOLUTE_URL);

        // 5. Erfolg zurückgeben
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
        // 1. Authentifizierung prüfen
        if (!$this->isAuthorized($request)) {
            return $this->errorResponse('Unauthorized', Response::HTTP_UNAUTHORIZED);
        }

        // 2. JSON und Pflichtfelder validieren
        $required = ['checklist_id', 'recipient_name', 'recipient_email', 'mitarbeiter_id'];
        $data = $this->validateJson($request, $required);
        if (is_string($data)) {
            return $this->errorResponse($data, Response::HTTP_BAD_REQUEST);
        }

        // 3. Mitarbeiter-ID validieren
        if (!$this->isValidMitarbeiterId($data['mitarbeiter_id'])) {
            return $this->errorResponse('Ungültige Personen-ID', Response::HTTP_BAD_REQUEST);
        }

        // 4. Checkliste holen
        $checklist = $checklistRepository->find((int) $data['checklist_id']);
        if (!$checklist) {
            return $this->errorResponse('Checklist not found', Response::HTTP_NOT_FOUND);
        }

        // 5. Prüfen, ob Bestellung existiert
        $existingSubmission = $submissionRepository->findOneByChecklistAndMitarbeiterId(
            $checklist,
            $data['mitarbeiter_id']
        );
        if ($existingSubmission) {
            return $this->errorResponse('Für diese Personen-ID wurde bereits eine Bestellung übermittelt.', Response::HTTP_CONFLICT);
        }

        // 6. Optionale Felder
        $personName = isset($data['person_name']) && is_string($data['person_name'])
            ? $data['person_name']
            : null;
        $intro = isset($data['intro']) && is_string($data['intro']) ? $data['intro'] : '';

        // 7. Link generieren
        $link = $this->urlGenerator->generate('checklist_form', [
            'checklist_id' => $checklist->getId(),
            'name' => $personName ?? $data['recipient_name'],
            'mitarbeiter_id' => $data['mitarbeiter_id'],
            'email' => $data['recipient_email'],
        ], UrlGeneratorInterface::ABSOLUTE_URL);

        // 8. E-Mail versenden
        $emailService->sendLinkEmail(
            $checklist,
            urldecode($data['recipient_name']),
            $data['recipient_email'],
            $data['mitarbeiter_id'],
            $personName ? urldecode($personName) : null,
            $intro,
            $link
        );

        // 9. Erfolg zurückgeben
        return new JsonResponse(['status' => 'sent', 'link' => $link]);
    }
}

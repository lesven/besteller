<?php

namespace App\Controller;

use App\Exception\JsonValidationException;
use App\Service\EmployeeIdValidatorService;
use App\Repository\ChecklistRepository;
use App\Service\EmailService;
use App\Repository\SubmissionRepository;
use InvalidArgumentException;
use App\Service\LinkSenderService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use App\Service\ApiValidationService;

class ApiController extends AbstractController
{
    private ApiValidationService $apiValidationService;
    private LinkSenderService $linkSenderService;
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
     * Hilfsmethode: Validiert die Mitarbeiter-ID
     */
    // Validiert die Mitarbeiter-ID
    private function isValidMitarbeiterId(string $mitarbeiterId): bool
    {
        return $this->employeeIdValidator->isValid($mitarbeiterId);
    }

    /**
     * Hilfsmethode: Liefert einen string-Parameter oder wirft eine Exception.
     * Alle Kommentare hier auf Deutsch laut Vorgabe.
     *
     * @param array<string,mixed> $data
     */
    private function requireString(array $data, string $key, string $errorMessage): string
    {
        if (!isset($data[$key]) || !is_string($data[$key]) || $data[$key] === '') {
            throw new InvalidArgumentException($errorMessage);
        }
        return $data[$key];
    }

    /**
     * Hilfsmethode: Parst die Stücklisten-ID sicher zu int.
     * Akzeptiert int, numeric string und float; leere Werte sind ungültig.
     *
     * @param mixed $value
     */
    private function parseChecklistId(mixed $value): int
    {
        if (is_int($value)) {
            return $value;
        }
        if (is_string($value) || is_float($value)) {
            if ($value === '') {
                throw new InvalidArgumentException('Ungültige Stücklisten-ID');
            }
            return (int) $value;
        }
        throw new InvalidArgumentException('Ungültige Stücklisten-ID');
    }

    /**
     * Extrahiert und validiert die benötigten Parameter für generateLink.
     *
     * @param array<string,mixed> $data Decodierte JSON-Daten
     * @return array{checklistId:int,mitarbeiterId:string,mitarbeiterName:string,emailEmpfaenger:string}
     * @throws \InvalidArgumentException Bei fehlenden oder ungültigen Parametern
     */
    private function extractGenerateLinkParams(array $data): array
    {
        // erforderliche string-Parameter holen oder Exception werfen
        $mitarbeiterId = $this->requireString($data, 'mitarbeiter_id', 'Ungültige Personen-ID');
        $mitarbeiterName = $this->requireString($data, 'mitarbeiter_name', 'Ungültiger Name');
        $emailEmpfaenger = $this->requireString($data, 'email_empfänger', 'Ungültige E-Mail');

        // Mitarbeiter-ID zusätzlich validieren
        if (!$this->isValidMitarbeiterId($mitarbeiterId)) {
            throw new InvalidArgumentException('Ungültige Personen-ID');
        }

        // Stücklisten-ID parsen
        if (!isset($data['stückliste_id'])) {
            throw new InvalidArgumentException('Ungültige Stücklisten-ID');
        }
        $checklistId = $this->parseChecklistId($data['stückliste_id']);

        return [
            'checklistId' => $checklistId,
            'mitarbeiterId' => $mitarbeiterId,
            'mitarbeiterName' => $mitarbeiterName,
            'emailEmpfaenger' => $emailEmpfaenger,
        ];
    }
    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
        private ParameterBagInterface $parameterBag,
        private EmployeeIdValidatorService $employeeIdValidator,
        LinkSenderService $linkSenderService,
        ApiValidationService $apiValidationService
    ) {
        $this->linkSenderService = $linkSenderService;
        $this->apiValidationService = $apiValidationService;
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
        try {
            $data = $this->apiValidationService->validateJson($request, $required);
        } catch (JsonValidationException $e) {
            return $this->errorResponse($e->getMessage(), Response::HTTP_BAD_REQUEST);
        }

        try {
            $params = $this->extractGenerateLinkParams($data);
        } catch (InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), Response::HTTP_BAD_REQUEST);
        }

        $mitarbeiterId = $params['mitarbeiterId'];
        $mitarbeiterName = $params['mitarbeiterName'];
        $emailEmpfaenger = $params['emailEmpfaenger'];
        $checklistId = $params['checklistId'];

        // 4. Link generieren
        $link = $this->urlGenerator->generate('checklist_selection', [
            'list' => $checklistId,
            'name' => $mitarbeiterName,
            'id' => $mitarbeiterId,
            'email' => $emailEmpfaenger,
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
        ChecklistRepository $checklistRepository
    ): JsonResponse {
        // 1. Authentifizierung prüfen
        if (!$this->isAuthorized($request)) {
            return $this->errorResponse('Unauthorized', Response::HTTP_UNAUTHORIZED);
        }

        // 2. JSON und Pflichtfelder validieren
        $required = ['checklist_id', 'recipient_name', 'recipient_email', 'mitarbeiter_id'];
        try {
            $data = $this->apiValidationService->validateJson($request, $required);
        } catch (JsonValidationException $e) {
            return $this->errorResponse($e->getMessage(), Response::HTTP_BAD_REQUEST);
        }

        $checklistId = is_int($data['checklist_id']) ? $data['checklist_id'] : (int) $data['checklist_id'];
        $checklist = $checklistRepository->find($checklistId);
        if (!$checklist) {
            return $this->errorResponse('Checklist not found', Response::HTTP_NOT_FOUND);
        }

        $recipientName = $data['recipient_name'];
        $recipientEmail = $data['recipient_email'];
        $mitarbeiterId = $data['mitarbeiter_id'];
        $personName = isset($data['person_name']) && is_string($data['person_name']) ? $data['person_name'] : null;
        $intro = isset($data['intro']) && is_string($data['intro']) ? $data['intro'] : '';

        try {
            $this->linkSenderService->sendChecklistLink(
                $checklist,
                $recipientName,
                $recipientEmail,
                $mitarbeiterId,
                $personName,
                $intro
            );
        } catch (InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), Response::HTTP_BAD_REQUEST);
        } catch (\RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), Response::HTTP_CONFLICT);
        }

        // Link generieren (wie im Service)
        $link = $this->urlGenerator->generate('checklist_form', [
            'checklist_id' => $checklist->getId(),
            'name' => $personName ?? $recipientName,
            'mitarbeiter_id' => $mitarbeiterId,
            'email' => $recipientEmail,
        ], UrlGeneratorInterface::ABSOLUTE_URL);

        return new JsonResponse(['status' => 'sent', 'link' => $link]);
    }
}

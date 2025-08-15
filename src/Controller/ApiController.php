<?php

namespace App\Controller;

use App\Exception\JsonValidationException;
use App\Service\EmployeeIdValidatorService;
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
     *
     * @param string[] $requiredFields Liste der Pflichtfelder
     * @return array<string,mixed> Decodierte JSON-Daten
     * @throws JsonValidationException Bei ungültigen JSON-Daten oder fehlenden Pflichtfeldern
     */
    private function validateJson(Request $request, array $requiredFields): array
    {
        $data = json_decode($request->getContent(), true);
        if (!is_array($data)) {
            throw new JsonValidationException('Ungültiges JSON');
        }

        // präzise Typinformation für PHPStan
        /** @var array<string,mixed> $typedData */
        $typedData = $data;

        foreach ($requiredFields as $field) {
            if (empty($typedData[$field])) {
                throw new JsonValidationException('Fehlende Parameter');
            }
        }

        return $typedData;
    }

    /**
     * Hilfsmethode: Validiert die Mitarbeiter-ID
     */
    private function isValidMitarbeiterId(string $id): bool
    {
        return $this->employeeIdValidator->isValid($id);
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
        if (!isset($data['mitarbeiter_id']) || !is_string($data['mitarbeiter_id'])) {
            throw new \InvalidArgumentException('Ungültige Personen-ID');
        }
        if (!isset($data['mitarbeiter_name']) || !is_string($data['mitarbeiter_name'])) {
            throw new \InvalidArgumentException('Ungültiger Name');
        }
        if (!isset($data['email_empfänger']) || !is_string($data['email_empfänger'])) {
            throw new \InvalidArgumentException('Ungültige E-Mail');
        }
        if (!isset($data['stückliste_id']) || (!is_int($data['stückliste_id']) && !is_string($data['stückliste_id']) && !is_float($data['stückliste_id']))) {
            throw new \InvalidArgumentException('Ungültige Stücklisten-ID');
        }

        $mitarbeiterId = $data['mitarbeiter_id'];
        if (!$this->isValidMitarbeiterId($mitarbeiterId)) {
            throw new \InvalidArgumentException('Ungültige Personen-ID');
        }

        $mitarbeiterName = $data['mitarbeiter_name'];
        $emailEmpfaenger = $data['email_empfänger'];

        if (is_int($data['stückliste_id'])) {
            $checklistId = $data['stückliste_id'];
        } elseif (is_string($data['stückliste_id']) || is_float($data['stückliste_id'])) {
            $checklistId = (int) $data['stückliste_id'];
        } else {
            $checklistId = (int) $data['stückliste_id'];
        }

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
        private EmployeeIdValidatorService $employeeIdValidator
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
        try {
            $data = $this->validateJson($request, $required);
        } catch (JsonValidationException $e) {
            return $this->errorResponse($e->getMessage(), Response::HTTP_BAD_REQUEST);
        }

        try {
            $params = $this->extractGenerateLinkParams($data);
        } catch (\InvalidArgumentException $e) {
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
        try {
            $data = $this->validateJson($request, $required);
        } catch (JsonValidationException $e) {
            return $this->errorResponse($e->getMessage(), Response::HTTP_BAD_REQUEST);
        }

        // Type-safety for expected fields
        if (!isset($data['mitarbeiter_id']) || !is_string($data['mitarbeiter_id'])) {
            return $this->errorResponse('Ungültige Personen-ID', Response::HTTP_BAD_REQUEST);
        }
        if (!isset($data['recipient_name']) || !is_string($data['recipient_name'])) {
            return $this->errorResponse('Ungültiger Empfängername', Response::HTTP_BAD_REQUEST);
        }
        if (!isset($data['recipient_email']) || !is_string($data['recipient_email'])) {
            return $this->errorResponse('Ungültige Empfänger-E-Mail', Response::HTTP_BAD_REQUEST);
        }
        if (!isset($data['checklist_id']) || (!is_int($data['checklist_id']) && !is_string($data['checklist_id']) && !is_float($data['checklist_id']))) {
            return $this->errorResponse('Ungültige Checklist-ID', Response::HTTP_BAD_REQUEST);
        }

        $mitarbeiterId = $data['mitarbeiter_id'];
        if (!$this->isValidMitarbeiterId($mitarbeiterId)) {
            return $this->errorResponse('Ungültige Personen-ID', Response::HTTP_BAD_REQUEST);
        }

        $checklistId = is_int($data['checklist_id']) ? $data['checklist_id'] : (int) $data['checklist_id'];
        $checklist = $checklistRepository->find($checklistId);
        if (!$checklist) {
            return $this->errorResponse('Checklist not found', Response::HTTP_NOT_FOUND);
        }

        // 5. Prüfen, ob Bestellung existiert
        $existingSubmission = $submissionRepository->findOneByChecklistAndMitarbeiterId(
            $checklist,
            $mitarbeiterId
        );
        if ($existingSubmission) {
            return $this->errorResponse('Für diese Personen-ID wurde bereits eine Bestellung übermittelt.', Response::HTTP_CONFLICT);
        }

        // 6. Optionale Felder
        $personName = isset($data['person_name']) && is_string($data['person_name'])
            ? $data['person_name']
            : null;
        $intro = isset($data['intro']) && is_string($data['intro']) ? $data['intro'] : '';

        // assign validated recipient vars
        $recipientName = $data['recipient_name'];
        $recipientEmail = $data['recipient_email'];

        // 7. Link generieren
        $link = $this->urlGenerator->generate('checklist_form', [
            'checklist_id' => $checklist->getId(),
            'name' => $personName ?? $recipientName,
            'mitarbeiter_id' => $mitarbeiterId,
            'email' => $recipientEmail,
        ], UrlGeneratorInterface::ABSOLUTE_URL);

        // 8. E-Mail versenden
        $emailService->sendLinkEmail(
            $checklist,
            urldecode($recipientName),
            $recipientEmail,
            $mitarbeiterId,
            $personName ? urldecode($personName) : null,
            $intro,
            $link
        );

        // 9. Erfolg zurückgeben
        return new JsonResponse(['status' => 'sent', 'link' => $link]);
    }
}

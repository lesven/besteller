<?php

namespace App\Controller;

use App\Exception\JsonValidationException;
use App\Service\EmployeeIdValidatorService;
use InvalidArgumentException;
use App\Service\LinkSenderService;
use App\Service\ApiControllerHelper;
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
    private ApiControllerHelper $helper;
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
    // helper methods moved to ApiControllerHelper to keep controller slim
    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
        private ParameterBagInterface $parameterBag,
        private EmployeeIdValidatorService $employeeIdValidator,
        LinkSenderService $linkSenderService,
        ApiValidationService $apiValidationService,
        ?ApiControllerHelper $helper = null
    ) {
        $this->linkSenderService = $linkSenderService;
        $this->apiValidationService = $apiValidationService;
        $this->helper = $helper ?? new ApiControllerHelper($parameterBag, $employeeIdValidator);
    }

    /**
     * Generiert einen Link zur Stückliste für einen Mitarbeiter.
     * Prüft die Authentifizierung und die übergebenen Parameter.
     * Gibt einen Link als JSON zurück.
     */
    public function generateLink(Request $request): JsonResponse
    {
    // 1. Authentifizierung prüfen
    if (!$this->helper->isAuthorized($request)) {
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
            $params = $this->helper->extractGenerateLinkParams($data);
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
        $checklistRepository
    ): JsonResponse {
    // 1. Authentifizierung prüfen
    if (!$this->helper->isAuthorized($request)) {
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
    [$mitarbeiterId, $personName, $intro] = $this->helper->extractSendLinkParams($data);

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

    // parameter-extraction moved to ApiControllerHelper
}

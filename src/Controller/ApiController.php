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

    public function generateLink(Request $request): JsonResponse
    {
        $configuredTokenRaw = $this->parameterBag->get('API_TOKEN') ?? null;
        $configuredToken = is_string($configuredTokenRaw) ? $configuredTokenRaw : '';
        if ($configuredToken !== '') {
            $auth = $request->headers->get('Authorization', '');
            if ($auth !== 'Bearer ' . $configuredToken) {
                return new JsonResponse(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
            }
        }

        $data = json_decode($request->getContent(), true);
        if (!is_array($data)) {
            return new JsonResponse(['error' => 'Ungültiges JSON'], Response::HTTP_BAD_REQUEST);
        }

        $required = ['stückliste_id', 'mitarbeiter_name', 'mitarbeiter_id', 'email_empfänger'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                return new JsonResponse(['error' => 'Fehlende Parameter'], Response::HTTP_BAD_REQUEST);
            }
        }

        $link = $this->urlGenerator->generate('checklist_selection', [
            'list' => $data['stückliste_id'],
            'name' => $data['mitarbeiter_name'],
            'id' => $data['mitarbeiter_id'],
            'email' => $data['email_empfänger'],
        ], UrlGeneratorInterface::ABSOLUTE_URL);

        return new JsonResponse(['link' => $link]);
    }

    public function sendLink(
        Request $request,
        \App\Repository\ChecklistRepository $checklistRepository,
        \App\Service\EmailService $emailService
    ): JsonResponse {
        $configuredTokenRaw = $this->parameterBag->get('API_TOKEN') ?? null;
        $configuredToken = is_string($configuredTokenRaw) ? $configuredTokenRaw : '';
        if ($configuredToken !== '') {
            $auth = $request->headers->get('Authorization', '');
            if ($auth !== 'Bearer ' . $configuredToken) {
                return new JsonResponse(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
            }
        }

        $data = json_decode($request->getContent(), true);
        if (!is_array($data)) {
            return new JsonResponse(['error' => 'Ungültiges JSON'], Response::HTTP_BAD_REQUEST);
        }

        $required = ['checklist_id', 'recipient_name', 'recipient_email', 'mitarbeiter_id'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                return new JsonResponse(['error' => 'Fehlende Parameter'], Response::HTTP_BAD_REQUEST);
            }
        }

        $checklist = $checklistRepository->find((int) $data['checklist_id']);
        if (!$checklist) {
            return new JsonResponse(['error' => 'Checklist not found'], Response::HTTP_NOT_FOUND);
        }

        $personName = isset($data['person_name']) && is_string($data['person_name'])
            ? $data['person_name']
            : null;
        $intro = isset($data['intro']) && is_string($data['intro']) ? $data['intro'] : '';

        $link = $this->urlGenerator->generate('checklist_form', [
            'checklist_id' => $checklist->getId(),
            'name' => $personName ?? $data['recipient_name'],
            'mitarbeiter_id' => $data['mitarbeiter_id'],
            'email' => $data['recipient_email'],
        ], UrlGeneratorInterface::ABSOLUTE_URL);

        $emailService->sendLinkEmail(
            $checklist,
            $data['recipient_name'],
            $data['recipient_email'],
            $data['mitarbeiter_id'],
            $personName,
            $intro,
            $link
        );

        return new JsonResponse(['status' => 'sent', 'link' => $link]);
    }
}

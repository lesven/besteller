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
}

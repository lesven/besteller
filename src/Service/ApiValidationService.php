<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\Request;
use App\Exception\JsonValidationException;

/**
 * Service zur Validierung von JSON-Daten und Pflichtfeldern für API-Requests.
 */
class ApiValidationService
{
    /**
     * Validiert die JSON-Daten und Pflichtfelder
     *
     * @param Request $request
     * @param string[] $requiredFields
     * @return array<string,mixed>
     * @throws JsonValidationException
     */
    public function validateJson(Request $request, array $requiredFields): array
    {
        $data = json_decode($request->getContent(), true);
        if (!is_array($data)) {
            throw new JsonValidationException('Ungültiges JSON');
        }
        foreach ($requiredFields as $field) {
            if (empty($data[$field])) {
                throw new JsonValidationException('Fehlende Parameter: ' . $field);
            }
        }
        return $data;
    }
}

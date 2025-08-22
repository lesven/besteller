<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use InvalidArgumentException;

/**
 * Kleiner Helfer für wiederverwendete API-Controller-Operationen.
 * Ziel: Auslagern von Parameter-Parsing und einfachen Prüfungen aus dem Controller.
 */
class ApiControllerHelper
{
    public function __construct(
        private ParameterBagInterface $parameterBag,
        private EmployeeIdValidatorService $employeeIdValidator
    ) {
    }

    // Prüft das konfigurierbare API-Token
    public function isAuthorized(Request $request): bool
    {
        $configuredTokenRaw = $this->parameterBag->get('API_TOKEN') ?? null;
        $configuredToken = is_string($configuredTokenRaw) ? $configuredTokenRaw : '';
        if ($configuredToken === '') {
            return true;
        }
        $auth = $request->headers->get('Authorization', '');
        return $auth === 'Bearer ' . $configuredToken;
    }

    // Validiert die Mitarbeiter-ID mit dem Validator-Service
    public function isValidMitarbeiterId(string $mitarbeiterId): bool
    {
        return $this->employeeIdValidator->isValid($mitarbeiterId);
    }

    // Extrahiert und validiert Parameter für generateLink
    public function extractGenerateLinkParams(array $data): array
    {
        $mitarbeiterId = $this->requireString($data, 'mitarbeiter_id', 'Ungültige Personen-ID');
        $mitarbeiterName = $this->requireString($data, 'mitarbeiter_name', 'Ungültiger Name');
        $emailEmpfaenger = $this->requireString($data, 'email_empfänger', 'Ungültige E-Mail');

        if (!$this->isValidMitarbeiterId($mitarbeiterId)) {
            throw new InvalidArgumentException('Ungültige Personen-ID');
        }

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

    // Extrahiert optionale Parameter für sendLink
    public function extractSendLinkParams(array $data): array
    {
        $mitarbeiterId = isset($data['mitarbeiter_id']) ? $data['mitarbeiter_id'] : '';
        $mitarbeiterId = is_string($mitarbeiterId) || is_int($mitarbeiterId) ? (string) $mitarbeiterId : '';

        $personName = null;
        if (isset($data['person_name']) && is_string($data['person_name'])) {
            $personName = $data['person_name'];
        }

        $intro = '';
        if (isset($data['intro']) && is_string($data['intro'])) {
            $intro = $data['intro'];
        }

        return [$mitarbeiterId, $personName, $intro];
    }

    // --- interne Helfer ---
    private function requireString(array $data, string $key, string $errorMessage): string
    {
        if (!isset($data[$key]) || !is_string($data[$key]) || $data[$key] === '') {
            throw new InvalidArgumentException($errorMessage);
        }
        return $data[$key];
    }

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
}

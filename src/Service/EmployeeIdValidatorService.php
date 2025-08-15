<?php

namespace App\Service;

/**
 * Service zur Validierung von Mitarbeiter-IDs.
 * 
 * Kapselt die Validierungslogik für Mitarbeiter-IDs, um Code-Duplikation
 * zu vermeiden und eine zentrale Stelle für Änderungen zu schaffen.
 */
class EmployeeIdValidatorService
{
    /**
     * Regex-Pattern für gültige Mitarbeiter-IDs.
     * Erlaubt Buchstaben (groß/klein), Zahlen und Bindestriche.
     */
    public const MITARBEITER_ID_REGEX = '/^[A-Za-z0-9-]+$/';

    /**
     * Validiert eine Mitarbeiter-ID gegen das definierte Pattern.
     *
     * @param string $mitarbeiterId Die zu validierende Mitarbeiter-ID
     * @return bool True wenn die ID gültig ist, false andernfalls
     */
    public function isValid(string $mitarbeiterId): bool
    {
        return preg_match(self::MITARBEITER_ID_REGEX, $mitarbeiterId) === 1;
    }
}
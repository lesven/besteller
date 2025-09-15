<?php

namespace App\Service;

use App\ValueObject\MitarbeiterId;

/**
 * Legacy service for backward compatibility with existing tests.
 * @deprecated Use MitarbeiterId Value Object directly
 */
class EmployeeIdValidatorService
{
    public function isValid(string $mitarbeiterId): bool
    {
        try {
            new MitarbeiterId($mitarbeiterId);
            return true;
        } catch (\InvalidArgumentException $e) {
            return false;
        }
    }
}
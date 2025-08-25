<?php

namespace App\Exception;

class InvalidEmployeeIdException extends \InvalidArgumentException
{
    public function __construct(string $employeeId, ?\Throwable $previous = null)
    {
        parent::__construct(
            sprintf('Invalid employee ID format: %s', $employeeId),
            0,
            $previous
        );
    }
}
<?php

namespace App\Exception;

class SubmissionAlreadyExistsException extends \RuntimeException
{
    public function __construct(string $mitarbeiterId, int $checklistId, ?\Throwable $previous = null)
    {
        parent::__construct(
            sprintf('Submission already exists for employee ID %s and checklist %d', $mitarbeiterId, $checklistId),
            0,
            $previous
        );
    }
}
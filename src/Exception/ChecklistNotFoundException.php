<?php

namespace App\Exception;

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ChecklistNotFoundException extends NotFoundHttpException
{
    public function __construct(int $checklistId, ?\Throwable $previous = null)
    {
        parent::__construct(
            sprintf('Checklist with ID %d was not found', $checklistId),
            $previous
        );
    }
}
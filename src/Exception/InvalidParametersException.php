<?php

namespace App\Exception;

use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class InvalidParametersException extends BadRequestHttpException
{
    public function __construct(array $missingParameters = [], ?\Throwable $previous = null)
    {
        $message = empty($missingParameters) 
            ? 'Invalid parameters provided'
            : sprintf('Missing required parameters: %s', implode(', ', $missingParameters));
            
        parent::__construct($message, $previous);
    }
}
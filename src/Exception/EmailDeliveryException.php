<?php

namespace App\Exception;

class EmailDeliveryException extends \RuntimeException
{
    public function __construct(string $recipient, string $reason = '', ?\Throwable $previous = null)
    {
        $message = sprintf('Failed to send email to %s', $recipient);
        if ($reason) {
            $message .= ': ' . $reason;
        }
        
        parent::__construct($message, 0, $previous);
    }
}
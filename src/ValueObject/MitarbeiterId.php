<?php

namespace App\ValueObject;

use InvalidArgumentException;

class MitarbeiterId
{
    public const PATTERN = '/^[A-Za-z0-9-]+$/';
    
    private string $id;

    public function __construct(string $id)
    {
        if (trim($id) === '') {
            throw new InvalidArgumentException('Employee ID cannot be empty');
        }
        
        if (!preg_match(self::PATTERN, $id)) {
            throw new InvalidArgumentException(
                sprintf('Employee ID "%s" must contain only letters, numbers and hyphens', $id)
            );
        }
        
        $this->id = $id;
    }

    public function getValue(): string
    {
        return $this->id;
    }

    public function __toString(): string
    {
        return $this->id;
    }

    public function equals(MitarbeiterId $other): bool
    {
        return $this->id === $other->id;
    }
}
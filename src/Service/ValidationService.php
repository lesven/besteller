<?php

namespace App\Service;

use App\Exception\InvalidEmployeeIdException;
use App\Exception\InvalidParametersException;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class ValidationService
{
    public function __construct(
        private ValidatorInterface $validator
    ) {}

    /**
     * Validates employee ID format
     */
    public function validateEmployeeId(string $employeeId): void
    {
        $constraints = [
            new Assert\NotBlank(message: 'Employee ID cannot be empty'),
            new Assert\Length(max: 255, maxMessage: 'Employee ID cannot exceed {{ limit }} characters'),
            new Assert\Regex(
                pattern: '/^[A-Za-z0-9_-]+$/',
                message: 'Employee ID can only contain letters, numbers, underscores and dashes'
            )
        ];

        $violations = $this->validator->validate($employeeId, $constraints);
        
        if (count($violations) > 0) {
            throw new InvalidEmployeeIdException($employeeId);
        }
    }

    /**
     * Validates email format
     */
    public function validateEmail(string $email): void
    {
        $constraints = [
            new Assert\NotBlank(message: 'Email cannot be empty'),
            new Assert\Email(message: 'Invalid email format'),
            new Assert\Length(max: 255, maxMessage: 'Email cannot exceed {{ limit }} characters')
        ];

        $violations = $this->validator->validate($email, $constraints);
        
        if (count($violations) > 0) {
            throw new ValidationFailedException($email, $violations);
        }
    }

    /**
     * Validates name format
     */
    public function validateName(string $name): void
    {
        $constraints = [
            new Assert\NotBlank(message: 'Name cannot be empty'),
            new Assert\Length(
                min: 2,
                max: 255,
                minMessage: 'Name must be at least {{ limit }} characters',
                maxMessage: 'Name cannot exceed {{ limit }} characters'
            )
        ];

        $violations = $this->validator->validate($name, $constraints);
        
        if (count($violations) > 0) {
            throw new ValidationFailedException($name, $violations);
        }
    }

    /**
     * Validates checklist submission data
     */
    public function validateSubmissionData(array $data): void
    {
        if (empty($data)) {
            throw new InvalidParametersException(['form_data']);
        }

        // Validate structure - each group should contain items with proper structure
        foreach ($data as $groupTitle => $groupData) {
            if (!is_string($groupTitle) || empty($groupTitle)) {
                throw new InvalidParametersException(['group_title']);
            }

            if (!is_array($groupData)) {
                throw new InvalidParametersException(['group_data']);
            }

            foreach ($groupData as $itemLabel => $itemData) {
                if (!is_string($itemLabel) || empty($itemLabel)) {
                    throw new InvalidParametersException(['item_label']);
                }

                // Check new data structure
                if (is_array($itemData) && isset($itemData['type'], $itemData['value'])) {
                    $this->validateItemValue($itemData['type'], $itemData['value']);
                }
            }
        }
    }

    private function validateItemValue(string $type, mixed $value): void
    {
        switch ($type) {
            case 'text':
            case 'radio':
                if (!is_string($value)) {
                    throw new InvalidParametersException(['item_value']);
                }
                break;
            case 'checkbox':
                if (!is_array($value)) {
                    throw new InvalidParametersException(['checkbox_values']);
                }
                break;
            default:
                throw new InvalidParametersException(['item_type']);
        }
    }
}
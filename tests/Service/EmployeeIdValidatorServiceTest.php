<?php

namespace App\Tests\Service;

use App\Service\EmployeeIdValidatorService;
use PHPUnit\Framework\TestCase;

class EmployeeIdValidatorServiceTest extends TestCase
{
    private EmployeeIdValidatorService $validator;

    protected function setUp(): void
    {
        $this->validator = new EmployeeIdValidatorService();
    }

    /**
     * @dataProvider validIdProvider
     */
    public function testValidIds(string $id): void
    {
        $this->assertTrue($this->validator->isValid($id));
    }

    /**
     * @dataProvider invalidIdProvider
     */
    public function testInvalidIds(string $id): void
    {
        $this->assertFalse($this->validator->isValid($id));
    }

    /**
     * @return array<string, array<string>>
     */
    public function validIdProvider(): array
    {
        return [
            'Simple number' => ['123'],
            'Simple letters' => ['ABC'],
            'Mixed case letters' => ['AbC'],
            'Letters and numbers' => ['ABC123'],
            'With dash' => ['ABC-123'],
            'Multiple dashes' => ['A-B-C-123'],
            'Single character' => ['A'],
            'Single number' => ['1'],
            'Single dash' => ['-'],
            'Starts with dash' => ['-ABC'],
            'Ends with dash' => ['ABC-'],
        ];
    }

    /**
     * @return array<string, array<string>>
     */
    public function invalidIdProvider(): array
    {
        return [
            'Empty string' => [''],
            'Space' => [' '],
            'With space' => ['ABC 123'],
            'With underscore' => ['ABC_123'],
            'With dot' => ['ABC.123'],
            'With special chars' => ['ABC@123'],
            'With umlauts' => ['ABCüöä'],
            'With slash' => ['ABC/123'],
            'With backslash' => ['ABC\\123'],
            'With newline' => ["ABC\n123"],
            'With tab' => ["ABC\t123"],
        ];
    }

    public function testConstantIsDefined(): void
    {
        $this->assertSame('/^[A-Za-z0-9-]+$/', EmployeeIdValidatorService::MITARBEITER_ID_REGEX);
    }
}
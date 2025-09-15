<?php

namespace App\Tests\ValueObject;

use App\ValueObject\MitarbeiterId;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class MitarbeiterIdTest extends TestCase
{
    public function testValidMitarbeiterIds(): void
    {
        $validIds = [
            'ABC123',
            'employee-1',
            '12345',
            'USER-2023-001',
            'a',
            'Z',
            '1-2-3',
            'Test123-456'
        ];

        foreach ($validIds as $id) {
            $mitarbeiterId = new MitarbeiterId($id);
            $this->assertSame($id, $mitarbeiterId->getValue());
            $this->assertSame($id, (string) $mitarbeiterId);
        }
    }

    public function testInvalidMitarbeiterIdsThrowException(): void
    {
        $invalidIds = [
            '',
            '  ',
            ' ABC123',
            'ABC123 ',
            'ABC@123',
            'user+test',
            'test.user',
            'user name',
            'test/123',
            'user\\name',
            'test#123',
            'user$123',
            'test%123'
        ];

        foreach ($invalidIds as $id) {
            $this->expectException(InvalidArgumentException::class);
            new MitarbeiterId($id);
        }
    }

    public function testEmptyStringThrowsSpecificException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Employee ID cannot be empty');
        new MitarbeiterId('');
    }

    public function testInvalidCharactersThrowsSpecificException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Employee ID "test@user" must contain only letters, numbers and hyphens');
        new MitarbeiterId('test@user');
    }

    public function testEquals(): void
    {
        $id1 = new MitarbeiterId('ABC123');
        $id2 = new MitarbeiterId('ABC123');
        $id3 = new MitarbeiterId('DEF456');

        $this->assertTrue($id1->equals($id2));
        $this->assertFalse($id1->equals($id3));
    }

    public function testToString(): void
    {
        $idString = 'TEST-123';
        $mitarbeiterId = new MitarbeiterId($idString);

        $this->assertSame($idString, (string) $mitarbeiterId);
    }

    public function testPatternConstant(): void
    {
        $this->assertSame('/^[A-Za-z0-9-]+$/', MitarbeiterId::PATTERN);
    }
}
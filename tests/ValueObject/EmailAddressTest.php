<?php

namespace App\Tests\ValueObject;

use App\ValueObject\EmailAddress;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class EmailAddressTest extends TestCase
{
    public function testValidEmailAddress(): void
    {
        $validEmails = [
            'test@example.com',
            'user.name@domain.co.uk',
            'noreply@besteller.local',
            'admin+test@example.org'
        ];

        foreach ($validEmails as $email) {
            $emailAddress = new EmailAddress($email);
            $this->assertSame($email, $emailAddress->getValue());
            $this->assertSame($email, (string) $emailAddress);
        }
    }

    public function testInvalidEmailAddressThrowsException(): void
    {
        $invalidEmails = [
            'invalid-email',
            '@example.com',
            'test@',
            'test..test@example.com',
            '',
            ' ',
            'test@domain',
            'test @example.com'
        ];

        foreach ($invalidEmails as $email) {
            $this->expectException(InvalidArgumentException::class);
            $this->expectExceptionMessage(sprintf('"%s" is not a valid email address', $email));
            new EmailAddress($email);
        }
    }

    public function testEquals(): void
    {
        $email1 = new EmailAddress('test@example.com');
        $email2 = new EmailAddress('test@example.com');
        $email3 = new EmailAddress('other@example.com');

        $this->assertTrue($email1->equals($email2));
        $this->assertFalse($email1->equals($email3));
    }

    public function testToString(): void
    {
        $emailString = 'test@example.com';
        $email = new EmailAddress($emailString);

        $this->assertSame($emailString, (string) $email);
    }
}
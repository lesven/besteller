<?php

namespace App\Tests\Entity;

use App\Entity\EmailSettings;
use PHPUnit\Framework\TestCase;

class EmailSettingsTest extends TestCase
{
    public function testDefaultsAndSetters(): void
    {
        $s = new EmailSettings();
        $this->assertSame('localhost', $s->getHost());
        $this->assertSame(25, $s->getPort());
        $this->assertSame('noreply@besteller.local', $s->getSenderEmail());

        $s->setHost('smtp.example')->setPort(587)->setSenderEmail('no-reply@example.com')->setIgnoreSsl(true);
        $this->assertSame('smtp.example', $s->getHost());
        $this->assertSame(587, $s->getPort());
        $this->assertSame('no-reply@example.com', $s->getSenderEmail());
        $this->assertTrue($s->isIgnoreSsl());
    }

    public function testUsernameAndPassword(): void
    {
        $s = new EmailSettings();

        // Test default values (nullable)
        $this->assertNull($s->getUsername());
        $this->assertNull($s->getPassword());

        // Test setting values
        $s->setUsername('testuser');
        $s->setPassword('testpass');

        $this->assertSame('testuser', $s->getUsername());
        $this->assertSame('testpass', $s->getPassword());

        // Test setting back to null
        $s->setUsername(null);
        $s->setPassword(null);

        $this->assertNull($s->getUsername());
        $this->assertNull($s->getPassword());
    }

    public function testIgnoreSsl(): void
    {
        $s = new EmailSettings();

        // Default should be false
        $this->assertFalse($s->isIgnoreSsl());

        // Test setting to true
        $s->setIgnoreSsl(true);
        $this->assertTrue($s->isIgnoreSsl());

        // Test setting back to false
        $s->setIgnoreSsl(false);
        $this->assertFalse($s->isIgnoreSsl());
    }

    public function testId(): void
    {
        $s = new EmailSettings();

        // ID should be null initially (not set)
        $this->assertNull($s->getId());

        // Note: We can't test setting ID directly as it's auto-generated
        // This test ensures the getter works correctly
    }

    public function testFluentInterface(): void
    {
        $s = new EmailSettings();

        // Test that setters return $this for method chaining
        $result = $s->setHost('mail.example.com')
                    ->setPort(465)
                    ->setUsername('user@example.com')
                    ->setPassword('secret')
                    ->setIgnoreSsl(true)
                    ->setSenderEmail('sender@example.com');

        $this->assertSame($s, $result);
        $this->assertSame('mail.example.com', $s->getHost());
        $this->assertSame(465, $s->getPort());
        $this->assertSame('user@example.com', $s->getUsername());
        $this->assertSame('secret', $s->getPassword());
        $this->assertTrue($s->isIgnoreSsl());
        $this->assertSame('sender@example.com', $s->getSenderEmail());
    }

    public function testPortValidation(): void
    {
        $s = new EmailSettings();

        // Test valid ports
        $s->setPort(1);
        $this->assertSame(1, $s->getPort());

        $s->setPort(65535);
        $this->assertSame(65535, $s->getPort());

        // Note: Doctrine will handle type validation, but we test the basic functionality
    }

    public function testEmailValidation(): void
    {
        $s = new EmailSettings();

        // Test various email formats (basic validation)
        $validEmails = [
            'test@example.com',
            'user.name@domain.co.uk',
            'noreply@besteller.local'
        ];

        foreach ($validEmails as $email) {
            $s->setSenderEmail($email);
            $this->assertSame($email, $s->getSenderEmail());
        }
    }
}

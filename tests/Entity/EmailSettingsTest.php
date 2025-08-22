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
}

<?php

namespace App\Tests\Factory;

use App\Factory\EmailTemplateFactory;
use PHPUnit\Framework\TestCase;

/**
 * Tests für EmailTemplateFactory.
 */
class EmailTemplateFactoryTest extends TestCase
{
    private EmailTemplateFactory $factory;

    protected function setUp(): void
    {
        $this->factory = new EmailTemplateFactory();
    }

    public function testGetDefaultEmailTemplateReturnsValidHtml(): void
    {
        $template = $this->factory->getDefaultEmailTemplate();
        
        $this->assertStringContainsString('<!DOCTYPE html>', $template);
        $this->assertStringContainsString('{{name}}', $template);
        $this->assertStringContainsString('{{mitarbeiter_id}}', $template);
        $this->assertStringContainsString('{{stückliste}}', $template);
        $this->assertStringContainsString('{{auswahl}}', $template);
        $this->assertStringContainsString('{{rueckfragen_email}}', $template);
    }

    public function testGetDefaultLinkEmailTemplateReturnsValidHtml(): void
    {
        $template = $this->factory->getDefaultLinkEmailTemplate();
        
        $this->assertStringContainsString('<!DOCTYPE html>', $template);
        $this->assertStringContainsString('{{recipient_name}}', $template);
        $this->assertStringContainsString('{{person_name}}', $template);
        $this->assertStringContainsString('{{link}}', $template);
        $this->assertStringContainsString('{{intro}}', $template);
    }

    public function testGetDefaultConfirmationEmailTemplateReturnsValidHtml(): void
    {
        $template = $this->factory->getDefaultConfirmationEmailTemplate();
        
        $this->assertStringContainsString('<!DOCTYPE html>', $template);
        $this->assertStringContainsString('{{name}}', $template);
        $this->assertStringContainsString('{{mitarbeiter_id}}', $template);
        $this->assertStringContainsString('{{stückliste}}', $template);
        $this->assertStringContainsString('{{auswahl}}', $template);
        $this->assertStringContainsString('{{rueckfragen_email}}', $template);
    }
}
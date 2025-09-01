<?php

namespace App\Tests\Security;

use App\Service\SubmissionService;
use App\Service\EmailService;
use App\Service\SubmissionService as SubmissionServiceAlias;
use App\Entity\Checklist;
use App\Entity\ChecklistGroup;
use App\Entity\GroupItem;
use App\Entity\Submission;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\HttpFoundation\Request;

class XssPreventionTest extends TestCase
{
    private function createItem(int $id, string $label, string $type): GroupItem
    {
        $item = new GroupItem();
        $item->setLabel($label);
        $item->setType($type);
        $ref = new \ReflectionProperty(GroupItem::class, 'id');
        $ref->setAccessible(true);
        $ref->setValue($item, $id);
        return $item;
    }

    private function createChecklistWithMaliciousData(): Checklist
    {
        $checklist = new Checklist();
        $group = new ChecklistGroup();
        $group->setTitle('<script>alert("XSS")</script>Group');
        $group->setChecklist($checklist);
        
        $item1 = $this->createItem(1, '<img src="x" onerror="alert(1)">', GroupItem::TYPE_TEXT);
        $item1->setGroup($group);
        $group->addItem($item1);
        
        $item2 = $this->createItem(2, 'javascript:alert("evil")', GroupItem::TYPE_CHECKBOX);
        $item2->setGroup($group);
        $group->addItem($item2);
        
        $checklist->addGroup($group);
        return $checklist;
    }

    /**
     * @dataProvider xssPayloadProvider
     */
    public function testSubmissionServiceEscapesXssInFormatting(string $xssPayload): void
    {
        $service = new SubmissionService();
        
        $data = [
            'Group' => [
                'MaliciousField' => ['type' => GroupItem::TYPE_TEXT, 'value' => $xssPayload],
                'ArrayField' => ['type' => GroupItem::TYPE_CHECKBOX, 'value' => [$xssPayload, 'safe_value']],
            ],
        ];
        
        $html = $service->formatSubmissionForEmail($data);
        
        // Verify that dangerous characters are properly escaped (not executable)
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringNotContainsString('onerror=alert', $html);
        $this->assertStringNotContainsString('onload=alert', $html);
        $this->assertStringNotContainsString('onclick=alert', $html);
        
        // Check that dangerous script constructs are not directly executable
        if (strpos($xssPayload, 'javascript:alert') !== false) {
            // The javascript: should not be executable (should be in quotes)
            $this->assertStringNotContainsString('src=javascript:', $html);
            $this->assertStringNotContainsString('href=javascript:', $html);
        }
        
        // Verify that HTML entities are used for escaping
        if (strpos($xssPayload, '<script>') !== false) {
            $this->assertStringContainsString('&lt;script&gt;', $html);
        }
        if (strpos($xssPayload, '"') !== false) {
            $this->assertStringContainsString('&quot;', $html);
        }
        if (strpos($xssPayload, '<') !== false) {
            $this->assertStringContainsString('&lt;', $html);
        }
        if (strpos($xssPayload, '>') !== false) {
            $this->assertStringContainsString('&gt;', $html);
        }
    }

    /**
     * @dataProvider xssPayloadProvider
     */
    public function testSubmissionServiceCollectsXssDataSafely(string $xssPayload): void
    {
        $service = new SubmissionService();
        $checklist = $this->createChecklistWithMaliciousData();
        
        $request = new Request([], [
            'item_1' => $xssPayload,
            'item_2' => [$xssPayload, 'safe_option'],
        ]);
        
        $result = $service->collectSubmissionData($checklist, $request);
        
        // Data should be collected as-is for storage, but not executed
        $this->assertIsArray($result);
        $this->assertArrayHasKey('<script>alert("XSS")</script>Group', $result);
        
        $groupData = $result['<script>alert("XSS")</script>Group'];
        $this->assertArrayHasKey('<img src="x" onerror="alert(1)">', $groupData);
        $this->assertSame($xssPayload, $groupData['<img src="x" onerror="alert(1)">']['value']);
    }

    public function testSubmissionServiceHandlesMultilineXss(): void
    {
        $service = new SubmissionService();
        
        $multilineXss = "<script>\nalert('multiline');\n</script>";
        $data = [
            'Group' => [
                'MultilineField' => ['type' => GroupItem::TYPE_TEXT, 'value' => $multilineXss],
            ],
        ];
        
        $html = $service->formatSubmissionForEmail($data);
        
        // Should escape script tags even across newlines
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
        // nl2br should still work for legitimate newlines
        $this->assertStringContainsString('<br', $html);
    }

    public function testSubmissionServiceHandlesLegacyXssData(): void
    {
        $service = new SubmissionService();
        
        $xssPayload = '<img src="x" onerror="alert(\'legacy\')">';
        $data = [
            'Group' => [
                'LegacyField' => [$xssPayload, 'safe_value'], // Legacy format: direct array
            ],
        ];
        
        $html = $service->formatSubmissionForEmail($data);
        
        $this->assertStringNotContainsString('<img', $html);
        $this->assertStringContainsString('&lt;img', $html);
        $this->assertStringNotContainsString('onerror=alert', $html);
    }

    public function testSubmissionServiceEscapesHtmlEntities(): void
    {
        $service = new SubmissionService();
        
        $data = [
            'Group' => [
                'EntityField' => ['type' => GroupItem::TYPE_TEXT, 'value' => '&lt;script&gt;alert("double encoded")&lt;/script&gt;'],
            ],
        ];
        
        $html = $service->formatSubmissionForEmail($data);
        
        // Should handle already encoded entities properly
        $this->assertStringContainsString('&amp;lt;script&amp;gt;', $html);
    }

    public function testSubmissionServiceHandlesNestedXssInArrays(): void
    {
        $service = new SubmissionService();
        
        $data = [
            'Group' => [
                'NestedField' => [
                    'type' => GroupItem::TYPE_CHECKBOX, 
                    'value' => [
                        '<script>alert("nested1")</script>',
                        'safe_value',
                        '<img src="x" onerror="alert(\'nested2\')">',
                    ]
                ],
            ],
        ];
        
        $html = $service->formatSubmissionForEmail($data);
        
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringNotContainsString('<img', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringContainsString('&lt;img', $html);
        $this->assertStringContainsString('safe_value', $html);
    }

    /**
     * @dataProvider advancedXssPayloadProvider
     */
    public function testSubmissionServiceHandlesAdvancedXssAttacks(string $xssPayload, string $description): void
    {
        $service = new SubmissionService();
        
        $data = [
            'Group' => [
                'AdvancedField' => ['type' => GroupItem::TYPE_TEXT, 'value' => $xssPayload],
            ],
        ];
        
        $html = $service->formatSubmissionForEmail($data);
        
        // Verify that dangerous content is properly escaped and not executable
        $this->assertStringNotContainsString('<script>', $html, 
            "Unescaped script tag found in output for: $description");
        $this->assertStringNotContainsString('onerror=alert', strtolower($html), 
            "Executable alert in onerror handler found in output for: $description");
        $this->assertStringNotContainsString('src=javascript:alert', strtolower($html), 
            "Executable javascript alert URL found in output for: $description");
        $this->assertStringNotContainsString('href=javascript:alert', strtolower($html), 
            "Executable javascript alert URL found in output for: $description");
        
        // Verify proper escaping occurred
        if (strpos(strtolower($xssPayload), '<script') !== false) {
            $this->assertStringContainsString('&lt;', $html, 
                "HTML tags should be escaped for: $description");
        }
        
        // Verify that the dangerous content is rendered safe
        $this->assertStringNotContainsString('<script>', $html, 
            "Raw script tags should not appear in output for: $description");
        
        // Check for proper HTML entity encoding
        if (strpos($xssPayload, '<') !== false) {
            $this->assertStringContainsString('&lt;', $html, 
                "Less-than should be HTML-encoded for: $description");
        }
        if (strpos($xssPayload, '>') !== false) {
            $this->assertStringContainsString('&gt;', $html, 
                "Greater-than should be HTML-encoded for: $description");
        }
    }

    public function testSubmissionServicePreservesLegitimateHtml(): void
    {
        $service = new SubmissionService();
        
        $legitimateContent = 'Contact us at support@company.com or call +49-123-456789';
        $data = [
            'Group' => [
                'ContactField' => ['type' => GroupItem::TYPE_TEXT, 'value' => $legitimateContent],
            ],
        ];
        
        $html = $service->formatSubmissionForEmail($data);
        
        // Should preserve legitimate content
        $this->assertStringContainsString('support@company.com', $html);
        $this->assertStringContainsString('+49-123-456789', $html);
    }

    public static function xssPayloadProvider(): array
    {
        return [
            'Basic script tag' => ['<script>alert("XSS")</script>'],
            'Image with onerror' => ['<img src="x" onerror="alert(1)">'],
            'JavaScript URL' => ['javascript:alert("XSS")'],
            'Data URL' => ['data:text/html,<script>alert(1)</script>'],
            'SVG with script' => ['<svg onload="alert(1)">'],
            'Body with onload' => ['<body onload="alert(1)">'],
            'Input with onfocus' => ['<input onfocus="alert(1)" autofocus>'],
            'Link with javascript' => ['<a href="javascript:alert(1)">click</a>'],
            'Event handler' => ['" onmouseover="alert(1)"'],
            'CSS expression' => ['<div style="background:url(javascript:alert(1))">'],
            'Meta refresh' => ['<meta http-equiv="refresh" content="0;url=javascript:alert(1)">'],
            'Object with data' => ['<object data="javascript:alert(1)">'],
            'Embed with src' => ['<embed src="javascript:alert(1)">'],
            'Iframe with src' => ['<iframe src="javascript:alert(1)">'],
            'Form with action' => ['<form action="javascript:alert(1)">'],
        ];
    }

    public static function advancedXssPayloadProvider(): array
    {
        return [
            ['<script>alert("case insensitive")</script>', 'Case insensitive script tag'],
            ['<script\x20type="text/javascript">alert(1)</script>', 'Script with null byte'],
            ['<script\x0d\x0a>alert(1)</script>', 'Script with CRLF'],
            ['<script\x09>alert(1)</script>', 'Script with tab'],
            ['<img src=x onerror=harmless(1)>', 'Unquoted attributes'],
            ['<img src="x" onerror="harmless(String.fromCharCode(88,83,83))">', 'Character encoding'],
            ['<img src="x" onerror="eval(atob(\'harmless\'))">', 'Base64 encoding'],
            ['<!--<img src="--><img src=x onerror=harmless(1)//">', 'Comment breaking'],
            ['<img src="javascript:harmless(1)" onerror="harmless(2)">', 'Multiple event handlers'],
            ['<svg><script>alert(1)</script></svg>', 'SVG with script'],
            ['<math><mi//xlink:href="data:x,<script>alert(1)</script>"></math>', 'MathML XSS'],
            ['<iframe srcdoc="<script>alert(1)</script>"></iframe>', 'Iframe with srcdoc'],
            ['<object data="data:text/html,<script>alert(1)</script>"></object>', 'Object with data URL'],
            ['<script>/*--></script><script>alert(1)//--></script>', 'Comment confusion'],
            ['<img src=x:harmless(alt) onerror=eval(src) alt=xss>', 'Alternative event evaluation'],
        ];
    }
}
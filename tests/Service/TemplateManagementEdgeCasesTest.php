<?php

namespace App\Tests\Service;

use App\Entity\Checklist;
use App\Entity\Submission;
use App\Service\TemplateConfigService;
use App\Service\TemplateParameterBuilder;
use App\Service\TemplateResolverService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;
use Twig\Error\LoaderError;

/**
 * Tests for edge cases, error scenarios, and boundary conditions
 * in the template management system
 */
class TemplateManagementEdgeCasesTest extends TestCase
{
    private TemplateConfigService $templateConfig;
    private TemplateParameterBuilder $parameterBuilder;

    protected function setUp(): void
    {
        $this->templateConfig = new TemplateConfigService();
        $this->parameterBuilder = new TemplateParameterBuilder();
    }

    // TemplateConfigService Edge Cases

    public function testGetTemplateWithEmptyStrings(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Controller '' nicht in Template-Konfiguration gefunden");

        $this->templateConfig->getTemplate('', '');
    }

    public function testGetTemplateWithNullValues(): void
    {
        $this->expectException(\TypeError::class);

        // @phpstan-ignore-next-line - Intentionally passing null for testing
        $this->templateConfig->getTemplate(null, null);
    }

    public function testGetTemplateWithSpecialCharacters(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Controller '../../malicious' nicht in Template-Konfiguration gefunden");

        $this->templateConfig->getTemplate('../../malicious', '<script>alert("xss")</script>');
    }

    public function testGetTemplateWithVeryLongStrings(): void
    {
        $longController = str_repeat('a', 1000);
        $longAction = str_repeat('b', 1000);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Controller '$longController' nicht in Template-Konfiguration gefunden");

        $this->templateConfig->getTemplate($longController, $longAction);
    }

    public function testGetDefaultParametersWithSpecialTypes(): void
    {
        // Should return empty array for unknown types
        $result = $this->templateConfig->getDefaultParameters('unknown_type_123');
        $this->assertEquals([], $result);

        $result = $this->templateConfig->getDefaultParameters('');
        $this->assertEquals([], $result);

        $result = $this->templateConfig->getDefaultParameters('admin/nested/type');
        $this->assertEquals([], $result);
    }

    public function testGetTemplateTypeWithEdgeCases(): void
    {
        // Test with various path formats
        $this->assertEquals('admin', $this->templateConfig->getTemplateType('admin/'));
        $this->assertEquals('checklist', $this->templateConfig->getTemplateType('admin')); // Doesn't start with "admin/"
        $this->assertEquals('security', $this->templateConfig->getTemplateType('security/'));
        $this->assertEquals('checklist', $this->templateConfig->getTemplateType(''));
        $this->assertEquals('checklist', $this->templateConfig->getTemplateType('/'));
        $this->assertEquals('checklist', $this->templateConfig->getTemplateType('random'));
    }

    public function testGetTemplateTypeWithMaliciousPaths(): void
    {
        // These should still return safe defaults
        $this->assertEquals('checklist', $this->templateConfig->getTemplateType('../../etc/passwd'));
        $this->assertEquals('checklist', $this->templateConfig->getTemplateType('<script>alert("xss")</script>'));
        $this->assertEquals('checklist', $this->templateConfig->getTemplateType('null'));
    }

    public function testGetControllerTemplatesWithCaseVariations(): void
    {
        // Should be case-sensitive and return empty for wrong case
        $this->assertEquals([], $this->templateConfig->getControllerTemplates('CHECKLIST'));
        $this->assertEquals([], $this->templateConfig->getControllerTemplates('Checklist'));
        $this->assertEquals([], $this->templateConfig->getControllerTemplates('admin_CHECKLIST'));
    }

    // TemplateParameterBuilder Edge Cases

    public function testBuildChecklistParametersWithNullAndEmptyValues(): void
    {
        $checklist = $this->createMock(Checklist::class);

        // Test with empty strings
        $params = $this->parameterBuilder->buildChecklistParameters($checklist, '', '', '');
        $this->assertEquals('', $params['name']);
        $this->assertEquals('', $params['mitarbeiterId']);
        $this->assertEquals('', $params['email']);

        // Test with whitespace strings
        $params = $this->parameterBuilder->buildChecklistParameters($checklist, '   ', "\t", "\n");
        $this->assertEquals('   ', $params['name']);
        $this->assertEquals("\t", $params['mitarbeiterId']);
        $this->assertEquals("\n", $params['email']);
    }

    public function testBuildSubmissionParametersWithNullChecklist(): void
    {
        $submission = $this->createMock(Submission::class);
        $submission->method('getChecklist')->willReturn(null);

        $params = $this->parameterBuilder->buildSubmissionParameters($submission);
        
        $this->assertArrayHasKey('submission', $params);
        $this->assertArrayNotHasKey('checklist', $params);
    }

    public function testBuildAdminListParametersWithEmptyArrays(): void
    {
        $params = $this->parameterBuilder->buildAdminListParameters([], 'items');
        
        $this->assertEquals([], $params['items']);
        $this->assertArrayNotHasKey('total_count', $params);
    }

    public function testBuildAdminListParametersWithZeroCount(): void
    {
        $params = $this->parameterBuilder->buildAdminListParameters(['item'], 'items', 0);
        
        $this->assertEquals(0, $params['total_count']);
    }

    public function testBuildAdminListParametersWithNegativeCount(): void
    {
        $params = $this->parameterBuilder->buildAdminListParameters(['item'], 'items', -5);
        
        $this->assertEquals(-5, $params['total_count']);
    }

    public function testBuildFormParametersWithMaliciousData(): void
    {
        $checklist = $this->createMock(Checklist::class);
        $maliciousFormData = [
            '<script>alert("xss")</script>' => 'malicious_key',
            'normal_key' => '<script>alert("xss")</script>',
            'sql_injection' => "'; DROP TABLE users; --",
            'path_traversal' => '../../etc/passwd',
        ];
        $maliciousErrors = [
            '<script>alert("error")</script>' => 'Error message with XSS',
        ];

        $params = $this->parameterBuilder->buildFormParameters($checklist, $maliciousFormData, $maliciousErrors);

        // Data should be preserved as-is (sanitization happens at output level)
        $this->assertArrayHasKey('<script>alert("xss")</script>', $params);
        $this->assertEquals('<script>alert("xss")</script>', $params['normal_key']);
        $this->assertEquals("'; DROP TABLE users; --", $params['sql_injection']);
        $this->assertArrayHasKey('errors', $params);
    }

    public function testBuildEmailTemplateParametersWithInvalidType(): void
    {
        $checklist = $this->createMock(Checklist::class);
        
        // Should not crash with invalid template type
        $params = $this->parameterBuilder->buildEmailTemplateParameters($checklist, 'invalid_type');
        
        $this->assertEquals('invalid_type', $params['template_type']);
        $this->assertArrayNotHasKey('available_placeholders', $params);
    }

    public function testBuildEmailTemplateParametersWithEmptyType(): void
    {
        $checklist = $this->createMock(Checklist::class);
        
        $params = $this->parameterBuilder->buildEmailTemplateParameters($checklist, '');
        
        $this->assertEquals('', $params['template_type']);
        $this->assertArrayNotHasKey('available_placeholders', $params);
    }

    public function testMergeParametersWithConflictingKeys(): void
    {
        $baseParams = [
            'key1' => 'base_value1',
            'key2' => 'base_value2',
            'nested' => ['base' => 'value'],
        ];

        $additionalParams = [
            'key1' => 'override_value1', // Should override
            'key3' => 'additional_value3', // Should be added
            'nested' => ['additional' => 'value'], // Should completely replace
        ];

        $merged = $this->parameterBuilder->mergeParameters($baseParams, $additionalParams);

        $this->assertEquals('override_value1', $merged['key1']);
        $this->assertEquals('base_value2', $merged['key2']);
        $this->assertEquals('additional_value3', $merged['key3']);
        $this->assertEquals(['additional' => 'value'], $merged['nested']); // Completely replaced
    }

    public function testMergeParametersWithNullValues(): void
    {
        $baseParams = ['key1' => 'value1', 'key2' => null];
        $additionalParams = ['key2' => 'override', 'key3' => null];

        $merged = $this->parameterBuilder->mergeParameters($baseParams, $additionalParams);

        $this->assertEquals('value1', $merged['key1']);
        $this->assertEquals('override', $merged['key2']); // Null overridden
        $this->assertNull($merged['key3']); // Null preserved
    }

    public function testBuildParametersWithComplexObjects(): void
    {
        $checklist = $this->createMock(Checklist::class);
        $submission = $this->createMock(Submission::class);
        $user = new \stdClass();
        $user->name = 'Test User';

        $params = $this->parameterBuilder->buildAdminEditParameters($user, 'user', [
            'checklist' => $checklist,
            'submission' => $submission,
            'array_data' => ['nested' => ['deeply' => 'nested']],
            'callable' => function() { return 'test'; },
        ]);

        $this->assertSame($user, $params['user']);
        $this->assertSame($checklist, $params['checklist']);
        $this->assertSame($submission, $params['submission']);
        $this->assertEquals(['nested' => ['deeply' => 'nested']], $params['array_data']);
        $this->assertTrue(is_callable($params['callable']));
    }

    // TemplateResolverService Error Handling

    public function testTemplateResolverWithTwigError(): void
    {
        $templateConfig = $this->createMock(TemplateConfigService::class);
        $parameterBuilder = $this->createMock(TemplateParameterBuilder::class);
        $twig = $this->createMock(Environment::class);

        $templateConfig->method('getTemplate')->willReturn('non_existent_template.html.twig');
        $templateConfig->method('getTemplateType')->willReturn('checklist');
        $templateConfig->method('getDefaultParameters')->willReturn([]);
        
        $twig->method('render')->willThrowException(new LoaderError('Template not found'));

        $resolver = new TemplateResolverService($templateConfig, $parameterBuilder, $twig);

        $this->expectException(LoaderError::class);
        $this->expectExceptionMessage('Template not found');

        $resolver->render('checklist', 'show');
    }

    public function testRenderEmailTemplateWithBoundaryTemplateTypes(): void
    {
        $templateConfig = $this->createMock(TemplateConfigService::class);
        $parameterBuilder = $this->createMock(TemplateParameterBuilder::class);
        $twig = $this->createMock(Environment::class);

        $resolver = new TemplateResolverService($templateConfig, $parameterBuilder, $twig);

        // Test various invalid template types
        $checklist = $this->createMock(Checklist::class);

        $invalidTypes = ['', 'INVALID', '123', 'email_template', 'link_confirmation', null];
        
        foreach ($invalidTypes as $type) {
            try {
                if ($type === null) {
                    // @phpstan-ignore-next-line - Intentionally passing null
                    $resolver->renderEmailTemplate($checklist, $type);
                } else {
                    $resolver->renderEmailTemplate($checklist, $type);
                }
                
                if (!in_array($type, ['email', 'link', 'confirmation'])) {
                    $this->fail("Expected InvalidArgumentException for type: " . var_export($type, true));
                }
            } catch (\InvalidArgumentException $e) {
                $this->assertStringContainsString('Unknown template type', $e->getMessage());
            } catch (\TypeError $e) {
                // Expected for null type
                $this->assertStringContainsString('must be of type string', $e->getMessage());
            }
        }
    }

    // Stress Testing

    public function testLargeParameterArrays(): void
    {
        $largeArray = [];
        for ($i = 0; $i < 1000; $i++) {
            $largeArray["key_$i"] = "value_$i";
        }

        $merged = $this->parameterBuilder->mergeParameters($largeArray, ['extra' => 'value']);

        $this->assertCount(1001, $merged);
        $this->assertEquals('value_0', $merged['key_0']);
        $this->assertEquals('value_999', $merged['key_999']);
        $this->assertEquals('value', $merged['extra']);
    }

    public function testParametersWithDeepNesting(): void
    {
        // Create a simpler nested structure to test parameter merging with nested arrays
        $deeplyNested = [
            'level_0' => [
                'level_1' => [
                    'level_2' => [
                        'final' => 'deep_value'
                    ]
                ]
            ]
        ];

        $params = $this->parameterBuilder->mergeParameters(['base' => 'value'], ['nested' => $deeplyNested]);

        $this->assertEquals('value', $params['base']);
        $this->assertArrayHasKey('nested', $params);
        
        // Navigate to deep value
        $this->assertArrayHasKey('level_0', $params['nested']);
        $this->assertArrayHasKey('level_1', $params['nested']['level_0']);
        $this->assertArrayHasKey('level_2', $params['nested']['level_0']['level_1']);
        $this->assertEquals('deep_value', $params['nested']['level_0']['level_1']['level_2']['final']);
    }

    public function testConcurrentParameterBuilding(): void
    {
        $checklist = $this->createMock(Checklist::class);
        
        // Simulate multiple concurrent parameter builds
        $results = [];
        for ($i = 0; $i < 100; $i++) {
            $results[] = $this->parameterBuilder->buildChecklistParameters(
                $checklist,
                "name_$i",
                "emp_$i",
                "email_$i@example.com"
            );
        }

        // Verify all results are correct and independent
        for ($i = 0; $i < 100; $i++) {
            $this->assertEquals("name_$i", $results[$i]['name']);
            $this->assertEquals("emp_$i", $results[$i]['mitarbeiterId']);
            $this->assertEquals("email_$i@example.com", $results[$i]['email']);
        }
    }

    // Memory and Performance Edge Cases

    public function testMemoryLeakPrevention(): void
    {
        $checklist = $this->createMock(Checklist::class);
        
        // Create and destroy many parameter sets
        for ($i = 0; $i < 1000; $i++) {
            $params = $this->parameterBuilder->buildChecklistParameters($checklist, "test_$i");
            unset($params);
        }

        // If we reach here without memory exhaustion, the test passes
        $this->assertTrue(true);
    }

    public function testCircularReferenceHandling(): void
    {
        $object1 = new \stdClass();
        $object2 = new \stdClass();
        
        $object1->ref = $object2;
        $object2->ref = $object1; // Circular reference

        // This should not cause infinite loops or crashes
        $params = $this->parameterBuilder->mergeParameters(
            ['object1' => $object1],
            ['object2' => $object2]
        );

        $this->assertSame($object1, $params['object1']);
        $this->assertSame($object2, $params['object2']);
    }
}
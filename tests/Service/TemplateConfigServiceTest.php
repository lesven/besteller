<?php

namespace App\Tests\Service;

use App\Service\TemplateConfigService;
use PHPUnit\Framework\TestCase;

class TemplateConfigServiceTest extends TestCase
{
    private TemplateConfigService $service;

    protected function setUp(): void
    {
        $this->service = new TemplateConfigService();
    }

    public function testGetTemplateReturnsCorrectPath(): void
    {
        $template = $this->service->getTemplate('checklist', 'show');
        $this->assertEquals('checklist/show.html.twig', $template);

        $template = $this->service->getTemplate('admin_checklist', 'index');
        $this->assertEquals('admin/checklist/index.html.twig', $template);

        $template = $this->service->getTemplate('security', 'login');
        $this->assertEquals('security/login.html.twig', $template);
    }

    public function testGetTemplateThrowsExceptionForInvalidController(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Controller 'invalid_controller' nicht in Template-Konfiguration gefunden");

        $this->service->getTemplate('invalid_controller', 'show');
    }

    public function testGetTemplateThrowsExceptionForInvalidAction(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Action 'invalid_action' für Controller 'checklist' nicht in Template-Konfiguration gefunden");

        $this->service->getTemplate('checklist', 'invalid_action');
    }

    public function testGetDefaultParametersReturnsCorrectParameters(): void
    {
        $checklistParams = $this->service->getDefaultParameters('checklist');
        $this->assertEquals([
            'app_name' => 'Besteller',
            'show_navigation' => true,
        ], $checklistParams);

        $adminParams = $this->service->getDefaultParameters('admin');
        $this->assertEquals([
            'app_name' => 'Besteller Admin',
            'show_admin_menu' => true,
            'layout' => 'admin/base.html.twig',
        ], $adminParams);

        $securityParams = $this->service->getDefaultParameters('security');
        $this->assertEquals([
            'app_name' => 'Besteller',
            'show_navigation' => false,
        ], $securityParams);
    }

    public function testGetDefaultParametersReturnsEmptyForUnknownType(): void
    {
        $params = $this->service->getDefaultParameters('unknown_type');
        $this->assertEquals([], $params);
    }

    public function testGetTemplateTypeIdentifiesAdminTemplates(): void
    {
        $type = $this->service->getTemplateType('admin/checklist/index.html.twig');
        $this->assertEquals('admin', $type);

        $type = $this->service->getTemplateType('admin/user/edit.html.twig');
        $this->assertEquals('admin', $type);
    }

    public function testGetTemplateTypeIdentifiesSecurityTemplates(): void
    {
        $type = $this->service->getTemplateType('security/login.html.twig');
        $this->assertEquals('security', $type);
    }

    public function testGetTemplateTypeDefaultsToChecklistForOtherTemplates(): void
    {
        $type = $this->service->getTemplateType('checklist/show.html.twig');
        $this->assertEquals('checklist', $type);

        $type = $this->service->getTemplateType('some/other/template.html.twig');
        $this->assertEquals('checklist', $type);
    }

    public function testTemplateExistsReturnsTrueForValidTemplates(): void
    {
        $this->assertTrue($this->service->templateExists('checklist', 'show'));
        $this->assertTrue($this->service->templateExists('admin_checklist', 'index'));
        $this->assertTrue($this->service->templateExists('security', 'login'));
    }

    public function testTemplateExistsReturnsFalseForInvalidTemplates(): void
    {
        $this->assertFalse($this->service->templateExists('invalid_controller', 'show'));
        $this->assertFalse($this->service->templateExists('checklist', 'invalid_action'));
        $this->assertFalse($this->service->templateExists('invalid_controller', 'invalid_action'));
    }

    public function testGetControllerTemplatesReturnsAllTemplatesForController(): void
    {
        $checklistTemplates = $this->service->getControllerTemplates('checklist');
        $expected = [
            'show' => 'checklist/show.html.twig',
            'already_submitted' => 'checklist/already_submitted.html.twig',
            'success' => 'checklist/success.html.twig',
            'form' => 'checklist/form.html.twig',
        ];
        $this->assertEquals($expected, $checklistTemplates);

        $adminChecklistTemplates = $this->service->getControllerTemplates('admin_checklist');
        $this->assertArrayHasKey('index', $adminChecklistTemplates);
        $this->assertArrayHasKey('new', $adminChecklistTemplates);
        $this->assertArrayHasKey('edit', $adminChecklistTemplates);
        $this->assertEquals('admin/checklist/index.html.twig', $adminChecklistTemplates['index']);
    }

    public function testGetControllerTemplatesReturnsEmptyForInvalidController(): void
    {
        $templates = $this->service->getControllerTemplates('invalid_controller');
        $this->assertEquals([], $templates);
    }

    public function testGetAvailableControllersReturnsAllControllers(): void
    {
        $controllers = $this->service->getAvailableControllers();
        
        $this->assertContains('checklist', $controllers);
        $this->assertContains('admin_checklist', $controllers);
        $this->assertContains('admin_submission', $controllers);
        $this->assertContains('admin_group', $controllers);
        $this->assertContains('admin_user', $controllers);
        $this->assertContains('security', $controllers);
        $this->assertContains('admin_dashboard', $controllers);
        $this->assertContains('admin_email_settings', $controllers);
    }

    public function testAllChecklistTemplatesExist(): void
    {
        $expectedTemplates = [
            ['checklist', 'show'],
            ['checklist', 'already_submitted'],
            ['checklist', 'success'],
            ['checklist', 'form'],
        ];

        foreach ($expectedTemplates as [$controller, $action]) {
            $this->assertTrue(
                $this->service->templateExists($controller, $action),
                "Template $controller:$action should exist"
            );
        }
    }

    public function testAllAdminTemplatesExist(): void
    {
        $expectedAdminTemplates = [
            ['admin_checklist', 'index'],
            ['admin_checklist', 'new'],
            ['admin_checklist', 'edit'],
            ['admin_checklist', 'email_template'],
            ['admin_checklist', 'link_template'],
            ['admin_checklist', 'confirmation_template'],
            ['admin_submission', 'index'],
            ['admin_submission', 'by_checklist'],
            ['admin_group', 'create'],
            ['admin_group', 'edit'],
            ['admin_user', 'index'],
            ['admin_user', 'edit'],
        ];

        foreach ($expectedAdminTemplates as [$controller, $action]) {
            $this->assertTrue(
                $this->service->templateExists($controller, $action),
                "Template $controller:$action should exist"
            );
        }
    }

    /**
     * @dataProvider templatePathProvider
     */
    public function testTemplatePathsFollowCorrectNamingConvention(string $controller, string $action, string $expectedPath): void
    {
        $actualPath = $this->service->getTemplate($controller, $action);
        $this->assertEquals($expectedPath, $actualPath);
    }

    public static function templatePathProvider(): array
    {
        return [
            ['checklist', 'show', 'checklist/show.html.twig'],
            ['checklist', 'already_submitted', 'checklist/already_submitted.html.twig'],
            ['checklist', 'success', 'checklist/success.html.twig'],
            ['checklist', 'form', 'checklist/form.html.twig'],
            ['admin_checklist', 'index', 'admin/checklist/index.html.twig'],
            ['admin_checklist', 'new', 'admin/checklist/new.html.twig'],
            ['admin_checklist', 'edit', 'admin/checklist/edit.html.twig'],
            ['admin_submission', 'index', 'admin/submission/index.html.twig'],
            ['admin_user', 'index', 'admin/user/index.html.twig'],
            ['security', 'login', 'security/login.html.twig'],
            ['admin_dashboard', 'index', 'admin/dashboard.html.twig'],
        ];
    }

    /**
     * @dataProvider templateTypeProvider
     */
    public function testTemplateTypeDetection(string $templatePath, string $expectedType): void
    {
        $actualType = $this->service->getTemplateType($templatePath);
        $this->assertEquals($expectedType, $actualType);
    }

    public static function templateTypeProvider(): array
    {
        return [
            ['admin/checklist/index.html.twig', 'admin'],
            ['admin/user/edit.html.twig', 'admin'],
            ['admin/dashboard.html.twig', 'admin'],
            ['security/login.html.twig', 'security'],
            ['checklist/show.html.twig', 'checklist'],
            ['checklist/form.html.twig', 'checklist'],
            ['random/template.html.twig', 'checklist'], // Default fallback
        ];
    }
}
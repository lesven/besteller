<?php

namespace App\Tests\Integration;

use App\Entity\Checklist;
use App\Entity\Submission;
use App\Service\TemplateConfigService;
use App\Service\TemplateParameterBuilder;
use App\Service\TemplateResolverService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

/**
 * Integration tests for the complete template management system
 */
class TemplateManagementIntegrationTest extends TestCase
{
    private TemplateConfigService $templateConfig;
    private TemplateParameterBuilder $parameterBuilder;
    private TemplateResolverService $resolver;
    private Environment $twig;

    protected function setUp(): void
    {
        $this->templateConfig = new TemplateConfigService();
        $this->parameterBuilder = new TemplateParameterBuilder();
        
        // Setup Twig with mock templates
        $templates = $this->getMockTemplates();
        $loader = new ArrayLoader($templates);
        $this->twig = new Environment($loader);
        
        $this->resolver = new TemplateResolverService(
            $this->templateConfig,
            $this->parameterBuilder,
            $this->twig
        );
    }

    public function testCompleteChecklistShowWorkflow(): void
    {
        $checklist = $this->createMockChecklist();
        $name = 'Max Mustermann';
        $mitarbeiterId = 'EMP-123';
        $email = 'max@example.com';

        $response = $this->resolver->renderChecklistShow($checklist, $name, $mitarbeiterId, $email);

        $this->assertInstanceOf(Response::class, $response);
        $content = $response->getContent();
        
        // Verify that default parameters are included
        $this->assertStringContainsString('Besteller', $content); // app_name
        $this->assertStringContainsString('show_navigation: 1', $content); // show_navigation
        
        // Verify that custom parameters are included
        $this->assertStringContainsString('Test Checklist', $content); // checklist title
        $this->assertStringContainsString('Max Mustermann', $content); // name
        $this->assertStringContainsString('EMP-123', $content); // mitarbeiterId
        $this->assertStringContainsString('max@example.com', $content); // email
    }

    public function testCompleteAlreadySubmittedWorkflow(): void
    {
        $checklist = $this->createMockChecklist();
        $submission = $this->createMockSubmission();
        $name = 'Max Mustermann';

        $response = $this->resolver->renderAlreadySubmitted($checklist, $submission, $name);

        $this->assertInstanceOf(Response::class, $response);
        $content = $response->getContent();
        
        $this->assertStringContainsString('already_submitted', $content);
        $this->assertStringContainsString('Test Checklist', $content);
        $this->assertStringContainsString('Max Mustermann', $content);
        $this->assertStringContainsString('submission_123', $content);
    }

    public function testCompleteAdminListWorkflow(): void
    {
        $checklists = [$this->createMockChecklist(), $this->createMockChecklist()];
        $itemType = 'checklists';
        $totalCount = 25;

        $response = $this->resolver->renderAdminList('admin_checklist', $checklists, $itemType, $totalCount);

        $this->assertInstanceOf(Response::class, $response);
        $content = $response->getContent();
        
        // Verify admin default parameters
        $this->assertStringContainsString('Besteller Admin', $content); // app_name for admin
        $this->assertStringContainsString('show_admin_menu: 1', $content); // show_admin_menu
        $this->assertStringContainsString('admin/base.html.twig', $content); // layout
        
        // Verify list parameters
        $this->assertStringContainsString('total_count: 25', $content);
        $this->assertStringContainsString('admin_checklist_index', $content);
    }

    public function testCompleteEmailTemplateWorkflow(): void
    {
        $checklist = $this->createMockChecklist();
        $templateType = 'email';

        $response = $this->resolver->renderEmailTemplate($checklist, $templateType);

        $this->assertInstanceOf(Response::class, $response);
        $content = $response->getContent();
        
        $this->assertStringContainsString('email_template', $content);
        $this->assertStringContainsString('Test Checklist', $content);
        $this->assertStringContainsString('{name}', $content);
        $this->assertStringContainsString('{checklist_title}', $content);
        $this->assertStringContainsString('{items}', $content);
    }

    public function testTemplateTypeDetectionAndDefaultParameters(): void
    {
        // Test admin template
        $adminParams = $this->resolver->buildParameters('admin/checklist/index.html.twig', ['custom' => 'value']);
        $this->assertEquals('Besteller Admin', $adminParams['app_name']);
        $this->assertTrue($adminParams['show_admin_menu']);
        $this->assertEquals('admin/base.html.twig', $adminParams['layout']);
        $this->assertEquals('value', $adminParams['custom']);

        // Test security template
        $securityParams = $this->resolver->buildParameters('security/login.html.twig', ['error' => 'Invalid']);
        $this->assertEquals('Besteller', $securityParams['app_name']);
        $this->assertFalse($securityParams['show_navigation']);
        $this->assertEquals('Invalid', $securityParams['error']);

        // Test checklist template (default)
        $checklistParams = $this->resolver->buildParameters('checklist/show.html.twig', ['name' => 'Max']);
        $this->assertEquals('Besteller', $checklistParams['app_name']);
        $this->assertTrue($checklistParams['show_navigation']);
        $this->assertEquals('Max', $checklistParams['name']);
    }

    public function testParameterOverridePriority(): void
    {
        // Custom parameters should override default parameters
        $params = $this->resolver->buildParameters('checklist/show.html.twig', [
            'app_name' => 'Custom App Name',
            'show_navigation' => false,
            'custom_param' => 'Custom Value'
        ]);

        $this->assertEquals('Custom App Name', $params['app_name']); // Overridden
        $this->assertFalse($params['show_navigation']); // Overridden
        $this->assertEquals('Custom Value', $params['custom_param']); // Added
    }

    public function testAllConfiguredTemplatesCanBeRendered(): void
    {
        $controllers = $this->templateConfig->getAvailableControllers();
        
        foreach ($controllers as $controller) {
            $templates = $this->templateConfig->getControllerTemplates($controller);
            
            foreach ($templates as $action => $templatePath) {
                // Verify template path is correctly configured
                $actualPath = $this->templateConfig->getTemplate($controller, $action);
                $this->assertEquals($templatePath, $actualPath);
                
                // Verify template exists check works
                $this->assertTrue($this->templateConfig->templateExists($controller, $action));
                
                // Verify template can be resolved
                $resolvedPath = $this->resolver->getTemplatePath($controller, $action);
                $this->assertEquals($templatePath, $resolvedPath);
            }
        }
    }

    public function testTemplateConfigServiceIntegrationWithParameterBuilder(): void
    {
        $checklist = $this->createMockChecklist();
        $name = 'Integration Test User';

        // Test that TemplateParameterBuilder creates correct parameters
        $checklistParams = $this->parameterBuilder->buildChecklistParameters($checklist, $name);
        $this->assertArrayHasKey('checklist', $checklistParams);
        $this->assertArrayHasKey('name', $checklistParams);
        $this->assertEquals($name, $checklistParams['name']);

        // Test that TemplateConfigService provides correct paths
        $templatePath = $this->templateConfig->getTemplate('checklist', 'show');
        $this->assertEquals('checklist/show.html.twig', $templatePath);

        // Test that default parameters are correct for template type
        $templateType = $this->templateConfig->getTemplateType($templatePath);
        $defaultParams = $this->templateConfig->getDefaultParameters($templateType);
        $this->assertArrayHasKey('app_name', $defaultParams);
        $this->assertEquals('Besteller', $defaultParams['app_name']);
    }

    public function testCompleteWorkflowFromControllerToResponse(): void
    {
        // Simulate complete workflow like ChecklistController would do
        $checklist = $this->createMockChecklist();
        $name = 'Complete Workflow Test';
        $mitarbeiterId = 'EMP-999';
        $email = 'workflow@example.com';

        // Step 1: Build parameters (like TemplateParameterBuilder would do)
        $parameters = $this->parameterBuilder->buildChecklistParameters($checklist, $name, $mitarbeiterId, $email);

        // Step 2: Get template path (like TemplateConfigService would do)
        $templatePath = $this->templateConfig->getTemplate('checklist', 'show');

        // Step 3: Get template type and default parameters
        $templateType = $this->templateConfig->getTemplateType($templatePath);
        $defaultParams = $this->templateConfig->getDefaultParameters($templateType);

        // Step 4: Merge parameters
        $mergedParams = array_merge($defaultParams, $parameters);

        // Step 5: Render template
        $content = $this->twig->render($templatePath, $mergedParams);

        // Verify complete workflow
        $this->assertStringContainsString('checklist_show', $content);
        $this->assertStringContainsString('Complete Workflow Test', $content);
        $this->assertStringContainsString('EMP-999', $content);
        $this->assertStringContainsString('workflow@example.com', $content);
        $this->assertStringContainsString('Besteller', $content); // Default parameter
    }

    private function createMockChecklist(): Checklist
    {
        $checklist = $this->createMock(Checklist::class);
        $checklist->method('getTitle')->willReturn('Test Checklist');
        $checklist->method('getId')->willReturn(1);
        return $checklist;
    }

    private function createMockSubmission(): Submission
    {
        $submission = $this->createMock(Submission::class);
        $submission->method('getId')->willReturn(123);
        return $submission;
    }

    private function getMockTemplates(): array
    {
        return [
            'checklist/show.html.twig' => '
                checklist_show
                app_name: {{ app_name }}
                show_navigation: {{ show_navigation ? 1 : 0 }}
                title: {{ checklist.title }}
                name: {{ name ?? "N/A" }}
                mitarbeiterId: {{ mitarbeiterId ?? "N/A" }}
                email: {{ email ?? "N/A" }}
            ',
            
            'checklist/already_submitted.html.twig' => '
                already_submitted
                app_name: {{ app_name }}
                title: {{ checklist.title }}
                name: {{ name }}
                submission: submission_{{ submission.id }}
            ',
            
            'checklist/success.html.twig' => '
                success
                app_name: {{ app_name }}
                title: {{ checklist.title }}
                name: {{ name }}
            ',
            
            'checklist/form.html.twig' => '
                form
                app_name: {{ app_name }}
                title: {{ checklist.title }}
                {% for key, value in this if key not in ["app_name", "show_navigation", "checklist"] %}
                    {{ key }}: {{ value }}
                {% endfor %}
            ',
            
            'admin/checklist/index.html.twig' => '
                admin_checklist_index
                app_name: {{ app_name }}
                show_admin_menu: {{ show_admin_menu ? 1 : 0 }}
                layout: {{ layout }}
                total_count: {{ total_count ?? 0 }}
            ',
            
            'admin/checklist/edit.html.twig' => '
                admin_checklist_edit
                app_name: {{ app_name }}
                title: {{ checklist.title ?? "N/A" }}
            ',
            
            'admin/checklist/email_template.html.twig' => '
                email_template
                app_name: {{ app_name }}
                title: {{ checklist.title }}
                template_type: {{ template_type }}
                {% if available_placeholders is defined %}
                    {% for placeholder, description in available_placeholders %}
                        {{ placeholder }}: {{ description }}
                    {% endfor %}
                {% endif %}
            ',
            
            'security/login.html.twig' => '
                login
                app_name: {{ app_name }}
                show_navigation: {{ show_navigation ? 1 : 0 }}
                error: {{ error ?? "N/A" }}
            ',
            
            'admin/dashboard.html.twig' => '
                dashboard
                app_name: {{ app_name }}
                show_admin_menu: {{ show_admin_menu ? 1 : 0 }}
                {% if stats is defined %}
                    {% for key, value in stats %}
                        stat_{{ key }}: {{ value }}
                    {% endfor %}
                {% endif %}
            ',
        ];
    }
}
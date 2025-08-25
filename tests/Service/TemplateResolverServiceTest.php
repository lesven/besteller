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

class TemplateResolverServiceTest extends TestCase
{
    private TemplateConfigService $templateConfig;
    private TemplateParameterBuilder $parameterBuilder;
    private Environment $twig;
    private TemplateResolverService $resolver;

    protected function setUp(): void
    {
        $this->templateConfig = $this->createMock(TemplateConfigService::class);
        $this->parameterBuilder = $this->createMock(TemplateParameterBuilder::class);
        $this->twig = $this->createMock(Environment::class);
        
        $this->resolver = new TemplateResolverService(
            $this->templateConfig,
            $this->parameterBuilder,
            $this->twig
        );
    }

    public function testRenderWithDefaultParameters(): void
    {
        $controller = 'checklist';
        $action = 'show';
        $templatePath = 'checklist/show.html.twig';
        $templateType = 'checklist';
        $defaultParams = ['app_name' => 'Besteller'];
        $customParams = ['checklist' => $this->createMock(Checklist::class)];
        $mergedParams = array_merge($defaultParams, $customParams);
        $renderedContent = '<html>Rendered Template</html>';

        $this->templateConfig->expects($this->once())
            ->method('getTemplate')
            ->with($controller, $action)
            ->willReturn($templatePath);

        $this->templateConfig->expects($this->once())
            ->method('getTemplateType')
            ->with($templatePath)
            ->willReturn($templateType);

        $this->templateConfig->expects($this->once())
            ->method('getDefaultParameters')
            ->with($templateType)
            ->willReturn($defaultParams);

        $this->twig->expects($this->once())
            ->method('render')
            ->with($templatePath, $mergedParams)
            ->willReturn($renderedContent);

        $response = $this->resolver->render($controller, $action, $customParams);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals($renderedContent, $response->getContent());
    }

    public function testRenderTemplateDirectly(): void
    {
        $templatePath = 'admin/checklist/index.html.twig';
        $templateType = 'admin';
        $defaultParams = ['show_admin_menu' => true];
        $customParams = ['checklists' => []];
        $mergedParams = array_merge($defaultParams, $customParams);
        $renderedContent = '<html>Admin Template</html>';

        $this->templateConfig->expects($this->once())
            ->method('getTemplateType')
            ->with($templatePath)
            ->willReturn($templateType);

        $this->templateConfig->expects($this->once())
            ->method('getDefaultParameters')
            ->with($templateType)
            ->willReturn($defaultParams);

        $this->twig->expects($this->once())
            ->method('render')
            ->with($templatePath, $mergedParams)
            ->willReturn($renderedContent);

        $response = $this->resolver->renderTemplate($templatePath, $customParams);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals($renderedContent, $response->getContent());
    }

    public function testRenderChecklistShow(): void
    {
        $checklist = $this->createMock(Checklist::class);
        $name = 'Max Mustermann';
        $mitarbeiterId = 'EMP-123';
        $email = 'max@example.com';
        $builtParameters = [
            'checklist' => $checklist,
            'name' => $name,
            'mitarbeiterId' => $mitarbeiterId,
            'email' => $email,
        ];
        $templatePath = 'checklist/show.html.twig';
        $defaultParams = ['app_name' => 'Besteller'];
        $mergedParams = array_merge($defaultParams, $builtParameters);

        $this->parameterBuilder->expects($this->once())
            ->method('buildChecklistParameters')
            ->with($checklist, $name, $mitarbeiterId, $email)
            ->willReturn($builtParameters);

        $this->templateConfig->expects($this->once())
            ->method('getTemplate')
            ->with('checklist', 'show')
            ->willReturn($templatePath);

        $this->templateConfig->expects($this->once())
            ->method('getTemplateType')
            ->with($templatePath)
            ->willReturn('checklist');

        $this->templateConfig->expects($this->once())
            ->method('getDefaultParameters')
            ->with('checklist')
            ->willReturn($defaultParams);

        $this->twig->expects($this->once())
            ->method('render')
            ->with($templatePath, $mergedParams)
            ->willReturn('<html>Show Template</html>');

        $response = $this->resolver->renderChecklistShow($checklist, $name, $mitarbeiterId, $email);

        $this->assertInstanceOf(Response::class, $response);
    }

    public function testRenderAlreadySubmitted(): void
    {
        $checklist = $this->createMock(Checklist::class);
        $submission = $this->createMock(Submission::class);
        $name = 'Max Mustermann';
        $builtParameters = [
            'checklist' => $checklist,
            'name' => $name,
            'submission' => $submission,
        ];

        $this->parameterBuilder->expects($this->once())
            ->method('buildAlreadySubmittedParameters')
            ->with($checklist, $submission, $name)
            ->willReturn($builtParameters);

        $this->templateConfig->expects($this->once())
            ->method('getTemplate')
            ->with('checklist', 'already_submitted')
            ->willReturn('checklist/already_submitted.html.twig');

        $this->templateConfig->expects($this->once())
            ->method('getTemplateType')
            ->willReturn('checklist');

        $this->templateConfig->expects($this->once())
            ->method('getDefaultParameters')
            ->willReturn([]);

        $this->twig->expects($this->once())
            ->method('render')
            ->willReturn('<html>Already Submitted</html>');

        $response = $this->resolver->renderAlreadySubmitted($checklist, $submission, $name);

        $this->assertInstanceOf(Response::class, $response);
    }

    public function testRenderSuccess(): void
    {
        $checklist = $this->createMock(Checklist::class);
        $name = 'Max Mustermann';
        $builtParameters = ['checklist' => $checklist, 'name' => $name];

        $this->parameterBuilder->expects($this->once())
            ->method('buildSuccessParameters')
            ->with($checklist, $name)
            ->willReturn($builtParameters);

        $this->templateConfig->expects($this->once())
            ->method('getTemplate')
            ->with('checklist', 'success')
            ->willReturn('checklist/success.html.twig');

        $this->templateConfig->expects($this->once())
            ->method('getTemplateType')
            ->willReturn('checklist');

        $this->templateConfig->expects($this->once())
            ->method('getDefaultParameters')
            ->willReturn([]);

        $this->twig->expects($this->once())
            ->method('render')
            ->willReturn('<html>Success</html>');

        $response = $this->resolver->renderSuccess($checklist, $name);

        $this->assertInstanceOf(Response::class, $response);
    }

    public function testRenderForm(): void
    {
        $checklist = $this->createMock(Checklist::class);
        $formData = ['name' => 'Max', 'email' => 'max@example.com'];
        $builtParameters = ['checklist' => $checklist] + $formData;

        $this->parameterBuilder->expects($this->once())
            ->method('buildFormParameters')
            ->with($checklist, $formData)
            ->willReturn($builtParameters);

        $this->templateConfig->expects($this->once())
            ->method('getTemplate')
            ->with('checklist', 'form')
            ->willReturn('checklist/form.html.twig');

        $this->templateConfig->expects($this->once())
            ->method('getTemplateType')
            ->willReturn('checklist');

        $this->templateConfig->expects($this->once())
            ->method('getDefaultParameters')
            ->willReturn([]);

        $this->twig->expects($this->once())
            ->method('render')
            ->willReturn('<html>Form</html>');

        $response = $this->resolver->renderForm($checklist, $formData);

        $this->assertInstanceOf(Response::class, $response);
    }

    public function testRenderAdminList(): void
    {
        $controller = 'admin_checklist';
        $items = [$this->createMock(Checklist::class)];
        $itemType = 'checklists';
        $totalCount = 42;
        $builtParameters = ['checklists' => $items, 'total_count' => $totalCount];

        $this->parameterBuilder->expects($this->once())
            ->method('buildAdminListParameters')
            ->with($items, $itemType, $totalCount)
            ->willReturn($builtParameters);

        $this->templateConfig->expects($this->once())
            ->method('getTemplate')
            ->with($controller, 'index')
            ->willReturn('admin/checklist/index.html.twig');

        $this->templateConfig->expects($this->once())
            ->method('getTemplateType')
            ->willReturn('admin');

        $this->templateConfig->expects($this->once())
            ->method('getDefaultParameters')
            ->willReturn(['show_admin_menu' => true]);

        $this->twig->expects($this->once())
            ->method('render')
            ->willReturn('<html>Admin List</html>');

        $response = $this->resolver->renderAdminList($controller, $items, $itemType, $totalCount);

        $this->assertInstanceOf(Response::class, $response);
    }

    public function testRenderAdminEdit(): void
    {
        $controller = 'admin_checklist';
        $entity = $this->createMock(Checklist::class);
        $entityType = 'checklist';
        $additionalData = ['form_errors' => []];
        $builtParameters = ['checklist' => $entity] + $additionalData;

        $this->parameterBuilder->expects($this->once())
            ->method('buildAdminEditParameters')
            ->with($entity, $entityType, $additionalData)
            ->willReturn($builtParameters);

        $this->templateConfig->expects($this->once())
            ->method('getTemplate')
            ->with($controller, 'edit')
            ->willReturn('admin/checklist/edit.html.twig');

        $this->templateConfig->expects($this->once())
            ->method('getTemplateType')
            ->willReturn('admin');

        $this->templateConfig->expects($this->once())
            ->method('getDefaultParameters')
            ->willReturn(['show_admin_menu' => true]);

        $this->twig->expects($this->once())
            ->method('render')
            ->willReturn('<html>Admin Edit</html>');

        $response = $this->resolver->renderAdminEdit($controller, $entity, $entityType, $additionalData);

        $this->assertInstanceOf(Response::class, $response);
    }

    public function testRenderAdminNew(): void
    {
        $controller = 'admin_user';
        $entity = $this->createMock(\App\Entity\User::class);
        $entityType = 'user';
        $additionalData = ['roles' => ['ROLE_USER']];
        $builtParameters = ['user' => $entity] + $additionalData;

        $this->parameterBuilder->expects($this->once())
            ->method('buildAdminEditParameters')
            ->with($entity, $entityType, $additionalData)
            ->willReturn($builtParameters);

        $this->templateConfig->expects($this->once())
            ->method('getTemplate')
            ->with($controller, 'new')
            ->willReturn('admin/user/new.html.twig');

        $this->templateConfig->expects($this->once())
            ->method('getTemplateType')
            ->willReturn('admin');

        $this->templateConfig->expects($this->once())
            ->method('getDefaultParameters')
            ->willReturn(['show_admin_menu' => true]);

        $this->twig->expects($this->once())
            ->method('render')
            ->willReturn('<html>Admin New</html>');

        $response = $this->resolver->renderAdminNew($controller, $entity, $entityType, $additionalData);

        $this->assertInstanceOf(Response::class, $response);
    }

    public function testRenderDashboard(): void
    {
        $stats = ['total_checklists' => 15];
        $recentItems = [['type' => 'checklist', 'id' => 1]];
        $builtParameters = ['stats' => $stats, 'recent_items' => $recentItems];

        $this->parameterBuilder->expects($this->once())
            ->method('buildDashboardParameters')
            ->with($stats, $recentItems)
            ->willReturn($builtParameters);

        $this->templateConfig->expects($this->once())
            ->method('getTemplate')
            ->with('admin_dashboard', 'index')
            ->willReturn('admin/dashboard.html.twig');

        $this->templateConfig->expects($this->once())
            ->method('getTemplateType')
            ->willReturn('admin');

        $this->templateConfig->expects($this->once())
            ->method('getDefaultParameters')
            ->willReturn(['show_admin_menu' => true]);

        $this->twig->expects($this->once())
            ->method('render')
            ->willReturn('<html>Dashboard</html>');

        $response = $this->resolver->renderDashboard($stats, $recentItems);

        $this->assertInstanceOf(Response::class, $response);
    }

    public function testRenderLogin(): void
    {
        $lastUsername = 'admin@example.com';
        $error = 'Invalid credentials';
        $builtParameters = ['last_username' => $lastUsername, 'error' => $error];

        $this->parameterBuilder->expects($this->once())
            ->method('buildLoginParameters')
            ->with($lastUsername, $error)
            ->willReturn($builtParameters);

        $this->templateConfig->expects($this->once())
            ->method('getTemplate')
            ->with('security', 'login')
            ->willReturn('security/login.html.twig');

        $this->templateConfig->expects($this->once())
            ->method('getTemplateType')
            ->willReturn('security');

        $this->templateConfig->expects($this->once())
            ->method('getDefaultParameters')
            ->willReturn(['show_navigation' => false]);

        $this->twig->expects($this->once())
            ->method('render')
            ->willReturn('<html>Login</html>');

        $response = $this->resolver->renderLogin($lastUsername, $error);

        $this->assertInstanceOf(Response::class, $response);
    }

    public function testRenderEmailTemplateForEmailType(): void
    {
        $checklist = $this->createMock(Checklist::class);
        $templateType = 'email';
        $builtParameters = ['checklist' => $checklist, 'template_type' => $templateType];

        $this->parameterBuilder->expects($this->once())
            ->method('buildEmailTemplateParameters')
            ->with($checklist, $templateType)
            ->willReturn($builtParameters);

        $this->templateConfig->expects($this->once())
            ->method('getTemplate')
            ->with('admin_checklist', 'email_template')
            ->willReturn('admin/checklist/email_template.html.twig');

        $this->templateConfig->expects($this->once())
            ->method('getTemplateType')
            ->willReturn('admin');

        $this->templateConfig->expects($this->once())
            ->method('getDefaultParameters')
            ->willReturn([]);

        $this->twig->expects($this->once())
            ->method('render')
            ->willReturn('<html>Email Template</html>');

        $response = $this->resolver->renderEmailTemplate($checklist, $templateType);

        $this->assertInstanceOf(Response::class, $response);
    }

    public function testRenderEmailTemplateForLinkType(): void
    {
        $checklist = $this->createMock(Checklist::class);
        $templateType = 'link';

        $this->parameterBuilder->expects($this->once())
            ->method('buildEmailTemplateParameters')
            ->willReturn(['checklist' => $checklist, 'template_type' => $templateType]);

        $this->templateConfig->expects($this->once())
            ->method('getTemplate')
            ->with('admin_checklist', 'link_template')
            ->willReturn('admin/checklist/link_template.html.twig');

        $this->templateConfig->method('getTemplateType')->willReturn('admin');
        $this->templateConfig->method('getDefaultParameters')->willReturn([]);
        $this->twig->method('render')->willReturn('<html>Link Template</html>');

        $response = $this->resolver->renderEmailTemplate($checklist, $templateType);

        $this->assertInstanceOf(Response::class, $response);
    }

    public function testRenderEmailTemplateForConfirmationType(): void
    {
        $checklist = $this->createMock(Checklist::class);
        $templateType = 'confirmation';

        $this->parameterBuilder->expects($this->once())
            ->method('buildEmailTemplateParameters')
            ->willReturn(['checklist' => $checklist, 'template_type' => $templateType]);

        $this->templateConfig->expects($this->once())
            ->method('getTemplate')
            ->with('admin_checklist', 'confirmation_template')
            ->willReturn('admin/checklist/confirmation_template.html.twig');

        $this->templateConfig->method('getTemplateType')->willReturn('admin');
        $this->templateConfig->method('getDefaultParameters')->willReturn([]);
        $this->twig->method('render')->willReturn('<html>Confirmation Template</html>');

        $response = $this->resolver->renderEmailTemplate($checklist, $templateType);

        $this->assertInstanceOf(Response::class, $response);
    }

    public function testRenderEmailTemplateThrowsExceptionForInvalidType(): void
    {
        $checklist = $this->createMock(Checklist::class);
        $templateType = 'invalid_type';

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown template type: invalid_type');

        $this->resolver->renderEmailTemplate($checklist, $templateType);
    }

    public function testGetTemplatePath(): void
    {
        $controller = 'checklist';
        $action = 'show';
        $expectedPath = 'checklist/show.html.twig';

        $this->templateConfig->expects($this->once())
            ->method('getTemplate')
            ->with($controller, $action)
            ->willReturn($expectedPath);

        $actualPath = $this->resolver->getTemplatePath($controller, $action);

        $this->assertEquals($expectedPath, $actualPath);
    }

    public function testTemplateExists(): void
    {
        $controller = 'checklist';
        $action = 'show';

        $this->templateConfig->expects($this->once())
            ->method('templateExists')
            ->with($controller, $action)
            ->willReturn(true);

        $exists = $this->resolver->templateExists($controller, $action);

        $this->assertTrue($exists);
    }

    public function testBuildParameters(): void
    {
        $templatePath = 'admin/checklist/index.html.twig';
        $templateType = 'admin';
        $defaultParams = ['show_admin_menu' => true, 'app_name' => 'Admin'];
        $customParams = ['checklists' => [], 'title' => 'Checklist Overview'];
        $expectedParams = array_merge($defaultParams, $customParams);

        $this->templateConfig->expects($this->once())
            ->method('getTemplateType')
            ->with($templatePath)
            ->willReturn($templateType);

        $this->templateConfig->expects($this->once())
            ->method('getDefaultParameters')
            ->with($templateType)
            ->willReturn($defaultParams);

        $actualParams = $this->resolver->buildParameters($templatePath, $customParams);

        $this->assertEquals($expectedParams, $actualParams);
    }

    public function testBuildParametersWithEmptyCustomParameters(): void
    {
        $templatePath = 'security/login.html.twig';
        $templateType = 'security';
        $defaultParams = ['show_navigation' => false];

        $this->templateConfig->expects($this->once())
            ->method('getTemplateType')
            ->with($templatePath)
            ->willReturn($templateType);

        $this->templateConfig->expects($this->once())
            ->method('getDefaultParameters')
            ->with($templateType)
            ->willReturn($defaultParams);

        $actualParams = $this->resolver->buildParameters($templatePath, []);

        $this->assertEquals($defaultParams, $actualParams);
    }
}
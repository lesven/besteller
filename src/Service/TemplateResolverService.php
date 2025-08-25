<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

/**
 * Facade-Service für Template-Management
 * 
 * Kombiniert TemplateConfigService und TemplateParameterBuilder
 * für eine einheitliche Template-Schnittstelle
 */
class TemplateResolverService
{
    public function __construct(
        private TemplateConfigService $templateConfig,
        private TemplateParameterBuilder $parameterBuilder,
        private Environment $twig
    ) {
    }

    /**
     * Rendert Template mit automatischen Standard-Parametern
     */
    public function render(
        string $controller,
        string $action,
        array $parameters = []
    ): Response {
        $template = $this->templateConfig->getTemplate($controller, $action);
        $templateType = $this->templateConfig->getTemplateType($template);
        $defaultParams = $this->templateConfig->getDefaultParameters($templateType);
        
        $mergedParams = array_merge($defaultParams, $parameters);
        
        $content = $this->twig->render($template, $mergedParams);
        
        return new Response($content);
    }

    /**
     * Rendert Template direkt mit Template-Pfad
     */
    public function renderTemplate(string $template, array $parameters = []): Response
    {
        $templateType = $this->templateConfig->getTemplateType($template);
        $defaultParams = $this->templateConfig->getDefaultParameters($templateType);
        
        $mergedParams = array_merge($defaultParams, $parameters);
        
        $content = $this->twig->render($template, $mergedParams);
        
        return new Response($content);
    }

    /**
     * Rendert Checklist-Show-Template
     */
    public function renderChecklistShow(
        \App\Entity\Checklist $checklist,
        ?string $name = null,
        ?string $mitarbeiterId = null,
        ?string $email = null
    ): Response {
        $parameters = $this->parameterBuilder->buildChecklistParameters(
            $checklist,
            $name,
            $mitarbeiterId,
            $email
        );
        
        return $this->render('checklist', 'show', $parameters);
    }

    /**
     * Rendert Already-Submitted-Template
     */
    public function renderAlreadySubmitted(
        \App\Entity\Checklist $checklist,
        \App\Entity\Submission $submission,
        string $name
    ): Response {
        $parameters = $this->parameterBuilder->buildAlreadySubmittedParameters(
            $checklist,
            $submission,
            $name
        );
        
        return $this->render('checklist', 'already_submitted', $parameters);
    }

    /**
     * Rendert Success-Template
     */
    public function renderSuccess(
        \App\Entity\Checklist $checklist,
        string $name
    ): Response {
        $parameters = $this->parameterBuilder->buildSuccessParameters($checklist, $name);
        
        return $this->render('checklist', 'success', $parameters);
    }

    /**
     * Rendert Form-Template
     */
    public function renderForm(
        \App\Entity\Checklist $checklist,
        array $formData = []
    ): Response {
        $parameters = $this->parameterBuilder->buildFormParameters($checklist, $formData);
        
        return $this->render('checklist', 'form', $parameters);
    }

    /**
     * Rendert Admin-Listen-Template
     */
    public function renderAdminList(
        string $controller,
        array $items,
        string $itemType,
        ?int $totalCount = null
    ): Response {
        $parameters = $this->parameterBuilder->buildAdminListParameters(
            $items,
            $itemType,
            $totalCount
        );
        
        return $this->render($controller, 'index', $parameters);
    }

    /**
     * Rendert Admin-Edit-Template
     */
    public function renderAdminEdit(
        string $controller,
        object $entity,
        string $entityType,
        array $additionalData = []
    ): Response {
        $parameters = $this->parameterBuilder->buildAdminEditParameters(
            $entity,
            $entityType,
            $additionalData
        );
        
        return $this->render($controller, 'edit', $parameters);
    }

    /**
     * Rendert Admin-New-Template
     */
    public function renderAdminNew(
        string $controller,
        object $entity,
        string $entityType,
        array $additionalData = []
    ): Response {
        $parameters = $this->parameterBuilder->buildAdminEditParameters(
            $entity,
            $entityType,
            $additionalData
        );
        
        return $this->render($controller, 'new', $parameters);
    }

    /**
     * Rendert Dashboard-Template
     */
    public function renderDashboard(array $stats = [], array $recentItems = []): Response
    {
        $parameters = $this->parameterBuilder->buildDashboardParameters($stats, $recentItems);
        
        return $this->render('admin_dashboard', 'index', $parameters);
    }

    /**
     * Rendert Login-Template
     */
    public function renderLogin(?string $lastUsername = null, ?string $error = null): Response
    {
        $parameters = $this->parameterBuilder->buildLoginParameters($lastUsername, $error);
        
        return $this->render('security', 'login', $parameters);
    }

    /**
     * Rendert Email-Template-Vorlagen
     */
    public function renderEmailTemplate(
        \App\Entity\Checklist $checklist,
        string $templateType
    ): Response {
        $parameters = $this->parameterBuilder->buildEmailTemplateParameters(
            $checklist,
            $templateType
        );
        
        $action = match($templateType) {
            'email' => 'email_template',
            'link' => 'link_template',
            'confirmation' => 'confirmation_template',
            default => throw new \InvalidArgumentException("Unknown template type: $templateType")
        };
        
        return $this->render('admin_checklist', $action, $parameters);
    }

    /**
     * Gibt Template-Pfad zurück ohne zu rendern
     */
    public function getTemplatePath(string $controller, string $action): string
    {
        return $this->templateConfig->getTemplate($controller, $action);
    }

    /**
     * Validiert Template-Existenz
     */
    public function templateExists(string $controller, string $action): bool
    {
        return $this->templateConfig->templateExists($controller, $action);
    }

    /**
     * Baut Parameter mit Standard-Parametern zusammen
     */
    public function buildParameters(string $templatePath, array $parameters = []): array
    {
        $templateType = $this->templateConfig->getTemplateType($templatePath);
        $defaultParams = $this->templateConfig->getDefaultParameters($templateType);
        
        return array_merge($defaultParams, $parameters);
    }
}
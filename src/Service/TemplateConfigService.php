<?php

namespace App\Service;

/**
 * Service für zentrale Template-Konfiguration
 * 
 * Verwaltet Template-Pfade und Standard-Parameter für alle Templates
 */
class TemplateConfigService
{
    /**
     * Template-Konfiguration nach Controller/Aktion strukturiert
     */
    private const TEMPLATE_CONFIG = [
        // Checklist Controller Templates
        'checklist' => [
            'show' => 'checklist/show.html.twig',
            'already_submitted' => 'checklist/already_submitted.html.twig',
            'success' => 'checklist/success.html.twig',
            'form' => 'checklist/form.html.twig',
        ],
        
        // Admin Checklist Controller Templates
        'admin_checklist' => [
            'index' => 'admin/checklist/index.html.twig',
            'new' => 'admin/checklist/new.html.twig',
            'edit' => 'admin/checklist/edit.html.twig',
            'email_template' => 'admin/checklist/email_template.html.twig',
            'link_template' => 'admin/checklist/link_template.html.twig',
            'confirmation_template' => 'admin/checklist/confirmation_template.html.twig',
            'send_link' => 'admin/checklist/send_link.html.twig',
        ],
        
        // Admin Submission Controller Templates
        'admin_submission' => [
            'index' => 'admin/submission/index.html.twig',
            'by_checklist' => 'admin/submission/by_checklist.html.twig',
        ],
        
        // Admin Group Controller Templates
        'admin_group' => [
            'create' => 'admin/group/create.html.twig',
            'edit' => 'admin/group/edit.html.twig',
            'add_item' => 'admin/group/add_item.html.twig',
            'edit_item' => 'admin/group/edit_item.html.twig',
        ],
        
        // Admin User Controller Templates
        'admin_user' => [
            'index' => 'admin/user/index.html.twig',
            'edit' => 'admin/user/edit.html.twig',
        ],
        
        // Other Templates
        'security' => [
            'login' => 'security/login.html.twig',
        ],
        
        'admin_dashboard' => [
            'index' => 'admin/dashboard.html.twig',
        ],
        
        'admin_email_settings' => [
            'edit' => 'admin/email_settings/edit.html.twig',
        ],
    ];

    /**
     * Standard-Parameter für bestimmte Template-Typen
     */
    private const DEFAULT_PARAMETERS = [
        'checklist' => [
            'app_name' => 'Besteller',
            'show_navigation' => true,
        ],
        'admin' => [
            'app_name' => 'Besteller Admin',
            'show_admin_menu' => true,
            'layout' => 'admin/base.html.twig',
        ],
        'security' => [
            'app_name' => 'Besteller',
            'show_navigation' => false,
        ],
    ];

    /**
     * Gibt Template-Pfad für Controller/Aktion zurück
     */
    public function getTemplate(string $controller, string $action): string
    {
        if (!isset(self::TEMPLATE_CONFIG[$controller])) {
            throw new \InvalidArgumentException("Controller '$controller' nicht in Template-Konfiguration gefunden");
        }
        
        if (!isset(self::TEMPLATE_CONFIG[$controller][$action])) {
            throw new \InvalidArgumentException("Action '$action' für Controller '$controller' nicht in Template-Konfiguration gefunden");
        }
        
        return self::TEMPLATE_CONFIG[$controller][$action];
    }

    /**
     * Gibt Standard-Parameter für einen Template-Typ zurück
     */
    public function getDefaultParameters(string $templateType): array
    {
        return self::DEFAULT_PARAMETERS[$templateType] ?? [];
    }

    /**
     * Bestimmt Template-Typ basierend auf Template-Pfad
     */
    public function getTemplateType(string $templatePath): string
    {
        if (str_starts_with($templatePath, 'admin/')) {
            return 'admin';
        }
        
        if (str_starts_with($templatePath, 'security/')) {
            return 'security';
        }
        
        return 'checklist';
    }

    /**
     * Validiert, ob ein Template existiert
     */
    public function templateExists(string $controller, string $action): bool
    {
        return isset(self::TEMPLATE_CONFIG[$controller][$action]);
    }

    /**
     * Gibt alle Templates für einen Controller zurück
     */
    public function getControllerTemplates(string $controller): array
    {
        return self::TEMPLATE_CONFIG[$controller] ?? [];
    }

    /**
     * Gibt alle verfügbaren Controller zurück
     */
    public function getAvailableControllers(): array
    {
        return array_keys(self::TEMPLATE_CONFIG);
    }
}
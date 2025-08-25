<?php

namespace App\Service;

use App\Entity\Checklist;
use App\Entity\Submission;
use App\Entity\User;

/**
 * Service für konsistente Template-Parameter
 * 
 * Baut Standard-Parameter für verschiedene Entity-Typen auf
 */
class TemplateParameterBuilder
{
    /**
     * Baut Standard-Parameter für Checklist-Templates
     */
    public function buildChecklistParameters(
        Checklist $checklist,
        ?string $name = null,
        ?string $mitarbeiterId = null,
        ?string $email = null
    ): array {
        $parameters = [
            'checklist' => $checklist,
        ];

        if ($name !== null) {
            $parameters['name'] = $name;
        }

        if ($mitarbeiterId !== null) {
            $parameters['mitarbeiterId'] = $mitarbeiterId;
        }

        if ($email !== null) {
            $parameters['email'] = $email;
        }

        return $parameters;
    }

    /**
     * Baut Parameter für Submission-Templates
     */
    public function buildSubmissionParameters(
        Submission $submission,
        ?Checklist $checklist = null
    ): array {
        $parameters = [
            'submission' => $submission,
        ];

        // Checklist aus Submission verwenden oder übergebene verwenden
        if ($checklist !== null) {
            $parameters['checklist'] = $checklist;
        } elseif ($submission->getChecklist()) {
            $parameters['checklist'] = $submission->getChecklist();
        }

        return $parameters;
    }

    /**
     * Baut Parameter für bereits eingereichte Checklist
     */
    public function buildAlreadySubmittedParameters(
        Checklist $checklist,
        Submission $existingSubmission,
        string $name
    ): array {
        return [
            'checklist' => $checklist,
            'name' => $name,
            'submission' => $existingSubmission,
        ];
    }

    /**
     * Baut Parameter für Erfolgs-Template
     */
    public function buildSuccessParameters(
        Checklist $checklist,
        string $name
    ): array {
        return [
            'checklist' => $checklist,
            'name' => $name,
        ];
    }

    /**
     * Baut Parameter für Admin-Listen-Templates
     */
    public function buildAdminListParameters(
        array $items,
        string $itemType,
        ?int $totalCount = null
    ): array {
        $parameters = [
            $itemType => $items,
        ];

        if ($totalCount !== null) {
            $parameters['total_count'] = $totalCount;
        }

        return $parameters;
    }

    /**
     * Baut Parameter für Admin-Edit-Templates
     */
    public function buildAdminEditParameters(
        object $entity,
        string $entityType,
        array $additionalData = []
    ): array {
        $parameters = [
            $entityType => $entity,
        ];

        return array_merge($parameters, $additionalData);
    }

    /**
     * Baut Parameter für User-Templates
     */
    public function buildUserParameters(User $user, array $additionalData = []): array
    {
        $parameters = [
            'user' => $user,
        ];

        return array_merge($parameters, $additionalData);
    }

    /**
     * Baut Parameter für Formular-Templates
     */
    public function buildFormParameters(
        Checklist $checklist,
        array $formData = [],
        array $errors = []
    ): array {
        $parameters = [
            'checklist' => $checklist,
        ];

        if (!empty($formData)) {
            $parameters = array_merge($parameters, $formData);
        }

        if (!empty($errors)) {
            $parameters['errors'] = $errors;
        }

        return $parameters;
    }

    /**
     * Baut Parameter für Dashboard-Template
     */
    public function buildDashboardParameters(
        array $stats = [],
        array $recentItems = []
    ): array {
        $parameters = [];

        if (!empty($stats)) {
            $parameters['stats'] = $stats;
        }

        if (!empty($recentItems)) {
            $parameters['recent_items'] = $recentItems;
        }

        return $parameters;
    }

    /**
     * Baut Parameter für Login-Template
     */
    public function buildLoginParameters(
        ?string $lastUsername = null,
        ?string $error = null
    ): array {
        $parameters = [];

        if ($lastUsername !== null) {
            $parameters['last_username'] = $lastUsername;
        }

        if ($error !== null) {
            $parameters['error'] = $error;
        }

        return $parameters;
    }

    /**
     * Baut Parameter für E-Mail-Template-Vorlagen
     */
    public function buildEmailTemplateParameters(
        Checklist $checklist,
        string $templateType
    ): array {
        $parameters = [
            'checklist' => $checklist,
            'template_type' => $templateType,
        ];

        // Template-spezifische Parameter hinzufügen
        switch ($templateType) {
            case 'email':
                $parameters['available_placeholders'] = [
                    '{name}' => 'Name des Mitarbeiters',
                    '{checklist_title}' => 'Titel der Checkliste',
                    '{items}' => 'Liste der Checklist-Einträge',
                ];
                break;
            case 'link':
                $parameters['available_placeholders'] = [
                    '{link}' => 'Link zur Checkliste',
                    '{checklist_title}' => 'Titel der Checkliste',
                ];
                break;
            case 'confirmation':
                $parameters['available_placeholders'] = [
                    '{name}' => 'Name des Mitarbeiters',
                    '{submission_date}' => 'Datum der Einreichung',
                ];
                break;
        }

        return $parameters;
    }

    /**
     * Merge Standard-Parameter mit spezifischen Parametern
     */
    public function mergeParameters(array $baseParameters, array $additionalParameters): array
    {
        return array_merge($baseParameters, $additionalParameters);
    }
}
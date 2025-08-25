<?php

namespace App\Tests\Service;

use App\Entity\Checklist;
use App\Entity\Submission;
use App\Entity\User;
use App\Service\TemplateParameterBuilder;
use PHPUnit\Framework\TestCase;

class TemplateParameterBuilderTest extends TestCase
{
    private TemplateParameterBuilder $builder;

    protected function setUp(): void
    {
        $this->builder = new TemplateParameterBuilder();
    }

    public function testBuildChecklistParametersWithAllParameters(): void
    {
        $checklist = $this->createMock(Checklist::class);
        $name = 'Max Mustermann';
        $mitarbeiterId = 'EMP-123';
        $email = 'max@example.com';

        $parameters = $this->builder->buildChecklistParameters(
            $checklist,
            $name,
            $mitarbeiterId,
            $email
        );

        $expected = [
            'checklist' => $checklist,
            'name' => $name,
            'mitarbeiterId' => $mitarbeiterId,
            'email' => $email,
        ];

        $this->assertEquals($expected, $parameters);
    }

    public function testBuildChecklistParametersWithOnlyChecklist(): void
    {
        $checklist = $this->createMock(Checklist::class);

        $parameters = $this->builder->buildChecklistParameters($checklist);

        $expected = [
            'checklist' => $checklist,
        ];

        $this->assertEquals($expected, $parameters);
    }

    public function testBuildChecklistParametersWithPartialParameters(): void
    {
        $checklist = $this->createMock(Checklist::class);
        $name = 'Max Mustermann';

        $parameters = $this->builder->buildChecklistParameters($checklist, $name);

        $expected = [
            'checklist' => $checklist,
            'name' => $name,
        ];

        $this->assertEquals($expected, $parameters);
        $this->assertArrayNotHasKey('mitarbeiterId', $parameters);
        $this->assertArrayNotHasKey('email', $parameters);
    }

    public function testBuildSubmissionParametersWithChecklist(): void
    {
        $submission = $this->createMock(Submission::class);
        $checklist = $this->createMock(Checklist::class);

        $parameters = $this->builder->buildSubmissionParameters($submission, $checklist);

        $expected = [
            'submission' => $submission,
            'checklist' => $checklist,
        ];

        $this->assertEquals($expected, $parameters);
    }

    public function testBuildSubmissionParametersWithChecklistFromSubmission(): void
    {
        $submission = $this->createMock(Submission::class);
        $checklist = $this->createMock(Checklist::class);
        
        $submission->method('getChecklist')->willReturn($checklist);

        $parameters = $this->builder->buildSubmissionParameters($submission);

        $expected = [
            'submission' => $submission,
            'checklist' => $checklist,
        ];

        $this->assertEquals($expected, $parameters);
    }

    public function testBuildSubmissionParametersWithoutChecklist(): void
    {
        $submission = $this->createMock(Submission::class);
        $submission->method('getChecklist')->willReturn(null);

        $parameters = $this->builder->buildSubmissionParameters($submission);

        $expected = [
            'submission' => $submission,
        ];

        $this->assertEquals($expected, $parameters);
    }

    public function testBuildAlreadySubmittedParameters(): void
    {
        $checklist = $this->createMock(Checklist::class);
        $submission = $this->createMock(Submission::class);
        $name = 'Max Mustermann';

        $parameters = $this->builder->buildAlreadySubmittedParameters($checklist, $submission, $name);

        $expected = [
            'checklist' => $checklist,
            'name' => $name,
            'submission' => $submission,
        ];

        $this->assertEquals($expected, $parameters);
    }

    public function testBuildSuccessParameters(): void
    {
        $checklist = $this->createMock(Checklist::class);
        $name = 'Max Mustermann';

        $parameters = $this->builder->buildSuccessParameters($checklist, $name);

        $expected = [
            'checklist' => $checklist,
            'name' => $name,
        ];

        $this->assertEquals($expected, $parameters);
    }

    public function testBuildAdminListParametersWithTotalCount(): void
    {
        $items = [
            $this->createMock(Checklist::class),
            $this->createMock(Checklist::class),
        ];
        $itemType = 'checklists';
        $totalCount = 42;

        $parameters = $this->builder->buildAdminListParameters($items, $itemType, $totalCount);

        $expected = [
            'checklists' => $items,
            'total_count' => $totalCount,
        ];

        $this->assertEquals($expected, $parameters);
    }

    public function testBuildAdminListParametersWithoutTotalCount(): void
    {
        $items = [$this->createMock(User::class)];
        $itemType = 'users';

        $parameters = $this->builder->buildAdminListParameters($items, $itemType);

        $expected = [
            'users' => $items,
        ];

        $this->assertEquals($expected, $parameters);
        $this->assertArrayNotHasKey('total_count', $parameters);
    }

    public function testBuildAdminEditParameters(): void
    {
        $checklist = $this->createMock(Checklist::class);
        $entityType = 'checklist';
        $additionalData = ['form_errors' => ['title' => 'Required field']];

        $parameters = $this->builder->buildAdminEditParameters($checklist, $entityType, $additionalData);

        $expected = [
            'checklist' => $checklist,
            'form_errors' => ['title' => 'Required field'],
        ];

        $this->assertEquals($expected, $parameters);
    }

    public function testBuildAdminEditParametersWithoutAdditionalData(): void
    {
        $user = $this->createMock(User::class);
        $entityType = 'user';

        $parameters = $this->builder->buildAdminEditParameters($user, $entityType);

        $expected = [
            'user' => $user,
        ];

        $this->assertEquals($expected, $parameters);
    }

    public function testBuildUserParameters(): void
    {
        $user = $this->createMock(User::class);
        $additionalData = ['roles' => ['ROLE_ADMIN'], 'permissions' => ['edit', 'delete']];

        $parameters = $this->builder->buildUserParameters($user, $additionalData);

        $expected = [
            'user' => $user,
            'roles' => ['ROLE_ADMIN'],
            'permissions' => ['edit', 'delete'],
        ];

        $this->assertEquals($expected, $parameters);
    }

    public function testBuildUserParametersWithoutAdditionalData(): void
    {
        $user = $this->createMock(User::class);

        $parameters = $this->builder->buildUserParameters($user);

        $expected = [
            'user' => $user,
        ];

        $this->assertEquals($expected, $parameters);
    }

    public function testBuildFormParametersWithAllData(): void
    {
        $checklist = $this->createMock(Checklist::class);
        $formData = [
            'name' => 'Max Mustermann',
            'email' => 'max@example.com',
            'selections' => ['item1', 'item2'],
        ];
        $errors = [
            'name' => 'Name is required',
            'email' => 'Invalid email format',
        ];

        $parameters = $this->builder->buildFormParameters($checklist, $formData, $errors);

        $expected = [
            'checklist' => $checklist,
            'name' => 'Max Mustermann',
            'email' => 'max@example.com',
            'selections' => ['item1', 'item2'],
            'errors' => $errors,
        ];

        $this->assertEquals($expected, $parameters);
    }

    public function testBuildFormParametersWithOnlyChecklist(): void
    {
        $checklist = $this->createMock(Checklist::class);

        $parameters = $this->builder->buildFormParameters($checklist);

        $expected = [
            'checklist' => $checklist,
        ];

        $this->assertEquals($expected, $parameters);
        $this->assertArrayNotHasKey('errors', $parameters);
    }

    public function testBuildDashboardParametersWithAllData(): void
    {
        $stats = [
            'total_checklists' => 15,
            'total_submissions' => 42,
            'users_count' => 8,
        ];
        $recentItems = [
            ['type' => 'checklist', 'title' => 'New Checklist'],
            ['type' => 'submission', 'id' => 123],
        ];

        $parameters = $this->builder->buildDashboardParameters($stats, $recentItems);

        $expected = [
            'stats' => $stats,
            'recent_items' => $recentItems,
        ];

        $this->assertEquals($expected, $parameters);
    }

    public function testBuildDashboardParametersEmpty(): void
    {
        $parameters = $this->builder->buildDashboardParameters();

        $this->assertEquals([], $parameters);
    }

    public function testBuildLoginParametersWithAllData(): void
    {
        $lastUsername = 'admin@example.com';
        $error = 'Invalid credentials';

        $parameters = $this->builder->buildLoginParameters($lastUsername, $error);

        $expected = [
            'last_username' => $lastUsername,
            'error' => $error,
        ];

        $this->assertEquals($expected, $parameters);
    }

    public function testBuildLoginParametersEmpty(): void
    {
        $parameters = $this->builder->buildLoginParameters();

        $this->assertEquals([], $parameters);
    }

    public function testBuildEmailTemplateParametersForEmailTemplate(): void
    {
        $checklist = $this->createMock(Checklist::class);
        $templateType = 'email';

        $parameters = $this->builder->buildEmailTemplateParameters($checklist, $templateType);

        $expected = [
            'checklist' => $checklist,
            'template_type' => 'email',
            'available_placeholders' => [
                '{name}' => 'Name des Mitarbeiters',
                '{checklist_title}' => 'Titel der Checkliste',
                '{items}' => 'Liste der Checklist-Einträge',
            ],
        ];

        $this->assertEquals($expected, $parameters);
    }

    public function testBuildEmailTemplateParametersForLinkTemplate(): void
    {
        $checklist = $this->createMock(Checklist::class);
        $templateType = 'link';

        $parameters = $this->builder->buildEmailTemplateParameters($checklist, $templateType);

        $expected = [
            'checklist' => $checklist,
            'template_type' => 'link',
            'available_placeholders' => [
                '{link}' => 'Link zur Checkliste',
                '{checklist_title}' => 'Titel der Checkliste',
            ],
        ];

        $this->assertEquals($expected, $parameters);
    }

    public function testBuildEmailTemplateParametersForConfirmationTemplate(): void
    {
        $checklist = $this->createMock(Checklist::class);
        $templateType = 'confirmation';

        $parameters = $this->builder->buildEmailTemplateParameters($checklist, $templateType);

        $expected = [
            'checklist' => $checklist,
            'template_type' => 'confirmation',
            'available_placeholders' => [
                '{name}' => 'Name des Mitarbeiters',
                '{submission_date}' => 'Datum der Einreichung',
            ],
        ];

        $this->assertEquals($expected, $parameters);
    }

    public function testBuildEmailTemplateParametersForUnknownTemplate(): void
    {
        $checklist = $this->createMock(Checklist::class);
        $templateType = 'unknown';

        $parameters = $this->builder->buildEmailTemplateParameters($checklist, $templateType);

        $expected = [
            'checklist' => $checklist,
            'template_type' => 'unknown',
        ];

        $this->assertEquals($expected, $parameters);
        $this->assertArrayNotHasKey('available_placeholders', $parameters);
    }

    public function testMergeParameters(): void
    {
        $baseParameters = [
            'checklist' => $this->createMock(Checklist::class),
            'name' => 'Original Name',
            'type' => 'base',
        ];

        $additionalParameters = [
            'name' => 'Updated Name', // Should override
            'email' => 'new@example.com', // Should be added
            'extra' => 'additional data', // Should be added
        ];

        $merged = $this->builder->mergeParameters($baseParameters, $additionalParameters);

        $expected = [
            'checklist' => $baseParameters['checklist'],
            'name' => 'Updated Name', // Overridden
            'type' => 'base', // Kept from base
            'email' => 'new@example.com', // Added
            'extra' => 'additional data', // Added
        ];

        $this->assertEquals($expected, $merged);
    }

    public function testMergeParametersWithEmptyAdditional(): void
    {
        $baseParameters = ['key' => 'value'];
        $additionalParameters = [];

        $merged = $this->builder->mergeParameters($baseParameters, $additionalParameters);

        $this->assertEquals($baseParameters, $merged);
    }

    public function testMergeParametersWithEmptyBase(): void
    {
        $baseParameters = [];
        $additionalParameters = ['key' => 'value'];

        $merged = $this->builder->mergeParameters($baseParameters, $additionalParameters);

        $this->assertEquals($additionalParameters, $merged);
    }

    /**
     * @dataProvider itemTypeProvider
     */
    public function testBuildAdminListParametersWithDifferentItemTypes(string $itemType, array $items): void
    {
        $parameters = $this->builder->buildAdminListParameters($items, $itemType);

        $this->assertArrayHasKey($itemType, $parameters);
        $this->assertEquals($items, $parameters[$itemType]);
    }

    public static function itemTypeProvider(): array
    {
        return [
            ['checklists', ['checklist1', 'checklist2']],
            ['submissions', ['submission1', 'submission2']],
            ['users', ['user1', 'user2']],
            ['items', ['item1', 'item2', 'item3']],
        ];
    }
}
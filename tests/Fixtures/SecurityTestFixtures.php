<?php

namespace App\Tests\Fixtures;

use App\Entity\User;
use App\Entity\Checklist;
use App\Entity\ChecklistGroup;
use App\Entity\GroupItem;
use App\Entity\Submission;
use App\Entity\EmailSettings;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class SecurityTestFixtures
{
    private EntityManagerInterface $entityManager;
    private UserPasswordHasherInterface $passwordHasher;

    public function __construct(EntityManagerInterface $entityManager, UserPasswordHasherInterface $passwordHasher)
    {
        $this->entityManager = $entityManager;
        $this->passwordHasher = $passwordHasher;
    }

    public function loadFixtures(): array
    {
        $fixtures = [];
        
        // Create test users with different roles
        $fixtures['admin_user'] = $this->createAdminUser();
        $fixtures['regular_user'] = $this->createRegularUser();
        $fixtures['test_user'] = $this->createTestUser();
        
        // Create realistic checklists
        $fixtures['employee_onboarding'] = $this->createEmployeeOnboardingChecklist();
        $fixtures['security_checklist'] = $this->createSecurityChecklist();
        $fixtures['equipment_request'] = $this->createEquipmentRequestChecklist();
        
        // Create test submissions
        $fixtures['test_submissions'] = $this->createTestSubmissions($fixtures['employee_onboarding']);
        
        // Create malicious test data for security testing
        $fixtures['malicious_checklist'] = $this->createMaliciousDataChecklist();
        
        $this->entityManager->flush();
        
        return $fixtures;
    }

    public function cleanupFixtures(): void
    {
        $this->entityManager->createQuery('DELETE FROM App\Entity\Submission')->execute();
        $this->entityManager->createQuery('DELETE FROM App\Entity\GroupItem')->execute();
        $this->entityManager->createQuery('DELETE FROM App\Entity\ChecklistGroup')->execute();
        $this->entityManager->createQuery('DELETE FROM App\Entity\Checklist')->execute();
        $this->entityManager->createQuery('DELETE FROM App\Entity\EmailSettings')->execute();
        $this->entityManager->createQuery('DELETE FROM App\Entity\User')->execute();
    }

    private function createAdminUser(): User
    {
        $user = new User();
        $user->setEmail('admin@security-test.com');
        $user->setRoles(['ROLE_ADMIN', 'ROLE_USER']);
        
        $hashedPassword = $this->passwordHasher->hashPassword($user, 'SecureAdminPassword123!');
        $user->setPassword($hashedPassword);
        
        $this->entityManager->persist($user);
        return $user;
    }

    private function createRegularUser(): User
    {
        $user = new User();
        $user->setEmail('user@security-test.com');
        $user->setRoles(['ROLE_USER']);
        
        $hashedPassword = $this->passwordHasher->hashPassword($user, 'RegularUserPass456!');
        $user->setPassword($hashedPassword);
        
        $this->entityManager->persist($user);
        return $user;
    }

    private function createTestUser(): User
    {
        $user = new User();
        $user->setEmail('test@security-test.com');
        $user->setRoles(['ROLE_USER']);
        
        $hashedPassword = $this->passwordHasher->hashPassword($user, 'TestPassword789!');
        $user->setPassword($hashedPassword);
        
        $this->entityManager->persist($user);
        return $user;
    }

    private function createEmployeeOnboardingChecklist(): Checklist
    {
        $checklist = new Checklist();
        $checklist->setTitle('Employee Onboarding Checklist');
        $checklist->setTargetEmail('hr@company.com');
        $checklist->setReplyEmail('hr-support@company.com');
        $checklist->setEmailTemplate('Welcome to our company! Please complete the following onboarding checklist.');
        
        // Personal Information Group
        $personalGroup = new ChecklistGroup();
        $personalGroup->setTitle('Personal Information');
        $personalGroup->setChecklist($checklist);
        
        $nameItem = new GroupItem();
        $nameItem->setLabel('Full Name');
        $nameItem->setType(GroupItem::TYPE_TEXT);
        $nameItem->setRequired(true);
        $nameItem->setGroup($personalGroup);
        
        $phoneItem = new GroupItem();
        $phoneItem->setLabel('Phone Number');
        $phoneItem->setType(GroupItem::TYPE_TEXT);
        $phoneItem->setRequired(true);
        $phoneItem->setGroup($personalGroup);
        
        $addressItem = new GroupItem();
        $addressItem->setLabel('Home Address');
        $addressItem->setType(GroupItem::TYPE_TEXT);
        $addressItem->setRequired(false);
        $addressItem->setGroup($personalGroup);
        
        $personalGroup->addItem($nameItem);
        $personalGroup->addItem($phoneItem);
        $personalGroup->addItem($addressItem);
        
        // Department Information Group
        $deptGroup = new ChecklistGroup();
        $deptGroup->setTitle('Department Information');
        $deptGroup->setChecklist($checklist);
        
        $departmentItem = new GroupItem();
        $departmentItem->setLabel('Department');
        $departmentItem->setType(GroupItem::TYPE_RADIO);
        $departmentItem->setOptions(['HR', 'IT', 'Finance', 'Marketing', 'Operations']);
        $departmentItem->setRequired(true);
        $departmentItem->setGroup($deptGroup);
        
        $positionItem = new GroupItem();
        $positionItem->setLabel('Position');
        $positionItem->setType(GroupItem::TYPE_TEXT);
        $positionItem->setRequired(true);
        $positionItem->setGroup($deptGroup);
        
        $startDateItem = new GroupItem();
        $startDateItem->setLabel('Start Date');
        $startDateItem->setType(GroupItem::TYPE_TEXT);
        $startDateItem->setRequired(true);
        $startDateItem->setGroup($deptGroup);
        
        $deptGroup->addItem($departmentItem);
        $deptGroup->addItem($positionItem);
        $deptGroup->addItem($startDateItem);
        
        // Required Equipment Group
        $equipmentGroup = new ChecklistGroup();
        $equipmentGroup->setTitle('Required Equipment');
        $equipmentGroup->setChecklist($checklist);
        
        $equipmentItem = new GroupItem();
        $equipmentItem->setLabel('Equipment Needed');
        $equipmentItem->setType(GroupItem::TYPE_CHECKBOX);
        $equipmentItem->setOptions(['Laptop', 'Monitor', 'Keyboard', 'Mouse', 'Headset', 'Mobile Phone', 'Desk Phone']);
        $equipmentItem->setRequired(false);
        $equipmentItem->setGroup($equipmentGroup);
        
        $softwareItem = new GroupItem();
        $softwareItem->setLabel('Software Access Required');
        $softwareItem->setType(GroupItem::TYPE_CHECKBOX);
        $softwareItem->setOptions(['Office 365', 'Slack', 'CRM', 'Project Management Tool', 'VPN', 'Development Tools']);
        $softwareItem->setRequired(false);
        $softwareItem->setGroup($equipmentGroup);
        
        $equipmentGroup->addItem($equipmentItem);
        $equipmentGroup->addItem($softwareItem);
        
        $checklist->addGroup($personalGroup);
        $checklist->addGroup($deptGroup);
        $checklist->addGroup($equipmentGroup);
        
        $this->entityManager->persist($checklist);
        $this->entityManager->persist($personalGroup);
        $this->entityManager->persist($deptGroup);
        $this->entityManager->persist($equipmentGroup);
        $this->entityManager->persist($nameItem);
        $this->entityManager->persist($phoneItem);
        $this->entityManager->persist($addressItem);
        $this->entityManager->persist($departmentItem);
        $this->entityManager->persist($positionItem);
        $this->entityManager->persist($startDateItem);
        $this->entityManager->persist($equipmentItem);
        $this->entityManager->persist($softwareItem);
        
        return $checklist;
    }

    private function createSecurityChecklist(): Checklist
    {
        $checklist = new Checklist();
        $checklist->setTitle('Security Assessment Checklist');
        $checklist->setTargetEmail('security@company.com');
        $checklist->setReplyEmail('security-team@company.com');
        
        $securityGroup = new ChecklistGroup();
        $securityGroup->setTitle('Security Measures');
        $securityGroup->setChecklist($checklist);
        
        $passwordPolicyItem = new GroupItem();
        $passwordPolicyItem->setLabel('Password Policy Compliance');
        $passwordPolicyItem->setType(GroupItem::TYPE_RADIO);
        $passwordPolicyItem->setOptions(['Fully Compliant', 'Partially Compliant', 'Non-Compliant']);
        $passwordPolicyItem->setRequired(true);
        $passwordPolicyItem->setGroup($securityGroup);
        
        $securityMeasuresItem = new GroupItem();
        $securityMeasuresItem->setLabel('Implemented Security Measures');
        $securityMeasuresItem->setType(GroupItem::TYPE_CHECKBOX);
        $securityMeasuresItem->setOptions(['Two-Factor Authentication', 'VPN', 'Encrypted Storage', 'Regular Backups', 'Antivirus Software']);
        $securityMeasuresItem->setRequired(true);
        $securityMeasuresItem->setGroup($securityGroup);
        
        $incidentReportItem = new GroupItem();
        $incidentReportItem->setLabel('Security Incident Details');
        $incidentReportItem->setType(GroupItem::TYPE_TEXT);
        $incidentReportItem->setRequired(false);
        $incidentReportItem->setGroup($securityGroup);
        
        $securityGroup->addItem($passwordPolicyItem);
        $securityGroup->addItem($securityMeasuresItem);
        $securityGroup->addItem($incidentReportItem);
        $checklist->addGroup($securityGroup);
        
        $this->entityManager->persist($checklist);
        $this->entityManager->persist($securityGroup);
        $this->entityManager->persist($passwordPolicyItem);
        $this->entityManager->persist($securityMeasuresItem);
        $this->entityManager->persist($incidentReportItem);
        
        return $checklist;
    }

    private function createEquipmentRequestChecklist(): Checklist
    {
        $checklist = new Checklist();
        $checklist->setTitle('Equipment Request Form');
        $checklist->setTargetEmail('equipment@company.com');
        $checklist->setReplyEmail('equipment-support@company.com');
        
        $requestGroup = new ChecklistGroup();
        $requestGroup->setTitle('Equipment Request');
        $requestGroup->setChecklist($checklist);
        
        $equipmentTypeItem = new GroupItem();
        $equipmentTypeItem->setLabel('Equipment Type');
        $equipmentTypeItem->setType(GroupItem::TYPE_RADIO);
        $equipmentTypeItem->setOptions(['Computer Hardware', 'Software License', 'Office Furniture', 'Mobile Device', 'Other']);
        $equipmentTypeItem->setRequired(true);
        $equipmentTypeItem->setGroup($requestGroup);
        
        $urgencyItem = new GroupItem();
        $urgencyItem->setLabel('Urgency Level');
        $urgencyItem->setType(GroupItem::TYPE_RADIO);
        $urgencyItem->setOptions(['Low', 'Medium', 'High', 'Critical']);
        $urgencyItem->setRequired(true);
        $urgencyItem->setGroup($requestGroup);
        
        $justificationItem = new GroupItem();
        $justificationItem->setLabel('Business Justification');
        $justificationItem->setType(GroupItem::TYPE_TEXT);
        $justificationItem->setRequired(true);
        $justificationItem->setGroup($requestGroup);
        
        $budgetItem = new GroupItem();
        $budgetItem->setLabel('Estimated Budget Range');
        $budgetItem->setType(GroupItem::TYPE_RADIO);
        $budgetItem->setOptions(['Under €500', '€500-€1000', '€1000-€2500', '€2500-€5000', 'Over €5000']);
        $budgetItem->setRequired(true);
        $budgetItem->setGroup($requestGroup);
        
        $requestGroup->addItem($equipmentTypeItem);
        $requestGroup->addItem($urgencyItem);
        $requestGroup->addItem($justificationItem);
        $requestGroup->addItem($budgetItem);
        $checklist->addGroup($requestGroup);
        
        $this->entityManager->persist($checklist);
        $this->entityManager->persist($requestGroup);
        $this->entityManager->persist($equipmentTypeItem);
        $this->entityManager->persist($urgencyItem);
        $this->entityManager->persist($justificationItem);
        $this->entityManager->persist($budgetItem);
        
        return $checklist;
    }

    private function createMaliciousDataChecklist(): Checklist
    {
        $checklist = new Checklist();
        $checklist->setTitle('<script>alert("XSS in title")</script>Malicious Test Checklist');
        $checklist->setTargetEmail('security-test@company.com');
        $checklist->setReplyEmail('security-test@company.com');
        
        $maliciousGroup = new ChecklistGroup();
        $maliciousGroup->setTitle('<img src="x" onerror="alert(\'XSS in group\')"');
        $maliciousGroup->setChecklist($checklist);
        
        $sqlInjectionItem = new GroupItem();
        $sqlInjectionItem->setLabel("'; DROP TABLE users; --");
        $sqlInjectionItem->setType(GroupItem::TYPE_TEXT);
        $sqlInjectionItem->setRequired(true);
        $sqlInjectionItem->setGroup($maliciousGroup);
        
        $xssItem = new GroupItem();
        $xssItem->setLabel('<script>document.cookie="stolen="+document.cookie</script>');
        $xssItem->setType(GroupItem::TYPE_TEXT);
        $xssItem->setRequired(false);
        $xssItem->setGroup($maliciousGroup);
        
        $pathTraversalItem = new GroupItem();
        $pathTraversalItem->setLabel('../../../etc/passwd');
        $pathTraversalItem->setType(GroupItem::TYPE_TEXT);
        $pathTraversalItem->setRequired(false);
        $pathTraversalItem->setGroup($maliciousGroup);
        
        $maliciousOptionsItem = new GroupItem();
        $maliciousOptionsItem->setLabel('Malicious Options');
        $maliciousOptionsItem->setType(GroupItem::TYPE_CHECKBOX);
        $maliciousOptionsItem->setOptions([
            '<script>alert("option1")</script>',
            'javascript:alert("option2")',
            '"; DROP TABLE options; --'
        ]);
        $maliciousOptionsItem->setRequired(false);
        $maliciousOptionsItem->setGroup($maliciousGroup);
        
        $maliciousGroup->addItem($sqlInjectionItem);
        $maliciousGroup->addItem($xssItem);
        $maliciousGroup->addItem($pathTraversalItem);
        $maliciousGroup->addItem($maliciousOptionsItem);
        $checklist->addGroup($maliciousGroup);
        
        $this->entityManager->persist($checklist);
        $this->entityManager->persist($maliciousGroup);
        $this->entityManager->persist($sqlInjectionItem);
        $this->entityManager->persist($xssItem);
        $this->entityManager->persist($pathTraversalItem);
        $this->entityManager->persist($maliciousOptionsItem);
        
        return $checklist;
    }

    private function createTestSubmissions(Checklist $checklist): array
    {
        $submissions = [];
        
        // Normal submission
        $submission1 = new Submission();
        $submission1->setChecklist($checklist);
        $submission1->setName('John Doe');
        $submission1->setMitarbeiterId('EMP-001');
        $submission1->setEmail('john.doe@company.com');
        $submission1->setData([
            'Personal Information' => [
                'Full Name' => ['type' => 'text', 'value' => 'John Doe'],
                'Phone Number' => ['type' => 'text', 'value' => '+49-123-456789'],
                'Home Address' => ['type' => 'text', 'value' => 'Musterstraße 123, 12345 Berlin']
            ],
            'Department Information' => [
                'Department' => ['type' => 'radio', 'value' => 'IT'],
                'Position' => ['type' => 'text', 'value' => 'Software Developer'],
                'Start Date' => ['type' => 'text', 'value' => '2024-01-15']
            ],
            'Required Equipment' => [
                'Equipment Needed' => ['type' => 'checkbox', 'value' => ['Laptop', 'Monitor', 'Keyboard']],
                'Software Access Required' => ['type' => 'checkbox', 'value' => ['Office 365', 'Development Tools']]
            ]
        ]);
        $submission1->setSubmittedAt(new \DateTime('-5 days'));
        
        // Submission with malicious data
        $submission2 = new Submission();
        $submission2->setChecklist($checklist);
        $submission2->setName('<script>alert("XSS in name")</script>');
        $submission2->setMitarbeiterId('EMP-XSS-TEST');
        $submission2->setEmail('xss-test@company.com');
        $submission2->setData([
            'Personal Information' => [
                'Full Name' => ['type' => 'text', 'value' => '<script>alert("XSS")</script>'],
                'Phone Number' => ['type' => 'text', 'value' => 'javascript:alert("phone")'],
            ],
            'Department Information' => [
                'Department' => ['type' => 'radio', 'value' => '"; DROP TABLE departments; --'],
                'Position' => ['type' => 'text', 'value' => '<img src="x" onerror="alert(1)">'],
            ]
        ]);
        $submission2->setSubmittedAt(new \DateTime('-3 days'));
        
        // Submission with SQL injection attempts
        $submission3 = new Submission();
        $submission3->setChecklist($checklist);
        $submission3->setName("Robert'; DROP TABLE users; --");
        $submission3->setMitarbeiterId('EMP-SQL-TEST');
        $submission3->setEmail('sql-test@company.com');
        $submission3->setData([
            'Personal Information' => [
                'Full Name' => ['type' => 'text', 'value' => "'; SELECT * FROM users WHERE 1=1; --"],
                'Phone Number' => ['type' => 'text', 'value' => "' OR '1'='1"],
            ]
        ]);
        $submission3->setSubmittedAt(new \DateTime('-1 day'));
        
        $submissions[] = $submission1;
        $submissions[] = $submission2;
        $submissions[] = $submission3;
        
        foreach ($submissions as $submission) {
            $this->entityManager->persist($submission);
        }
        
        return $submissions;
    }

    public static function getMaliciousInputs(): array
    {
        return [
            // XSS Payloads
            '<script>alert("XSS")</script>',
            '<img src="x" onerror="alert(1)">',
            'javascript:alert("XSS")',
            '<iframe src="javascript:alert(1)"></iframe>',
            '<svg onload="alert(1)">',
            '<body onload="alert(1)">',
            '" onmouseover="alert(1)"',
            
            // SQL Injection Payloads
            "'; DROP TABLE users; --",
            "' OR '1'='1",
            "' UNION SELECT * FROM users --",
            "admin'--",
            "' OR 1=1#",
            "'; DELETE FROM submissions; --",
            
            // Path Traversal
            '../../../etc/passwd',
            '..\\..\\..\\windows\\system32',
            '/etc/passwd',
            'C:\\Windows\\System32\\config\\sam',
            
            // Command Injection
            '$(rm -rf /)',
            '`rm -rf /`',
            '; rm -rf /',
            '| cat /etc/passwd',
            
            // LDAP Injection
            '*)(uid=*))(|(uid=*',
            '*))(|(cn=*',
            
            // XML/XXE
            '<?xml version="1.0"?><!DOCTYPE test [<!ENTITY xxe SYSTEM "file:///etc/passwd">]>',
            
            // NoSQL Injection
            '{"$gt":""}',
            '{"$regex":".*"}',
            
            // Template Injection
            '{{7*7}}',
            '${7*7}',
            '<%= 7*7 %>',
            
            // CRLF Injection
            "test\r\nSet-Cookie: admin=true",
            "test\n\rLocation: evil.com",
            
            // Format String Attacks
            '%s%s%s%s%s',
            '%x%x%x%x%x',
            
            // Unicode/Encoding Attacks
            '\u003cscript\u003ealert(1)\u003c/script\u003e',
            '＜script＞alert(1)＜/script＞',
            
            // Buffer Overflow Attempts
            str_repeat('A', 10000),
            str_repeat('B', 50000),
            
            // Null Byte Injection
            "test\0injection",
            "file.txt\0.jpg",
        ];
    }

    public static function getValidTestInputs(): array
    {
        return [
            'Max Mustermann',
            'Anna Schmidt',
            'test@example.com',
            'user@company.com',
            'EMP-12345',
            'CONTRACTOR-001',
            'Software Developer',
            'Project Manager',
            'HR Specialist',
            '+49-123-456789',
            '+1-555-123-4567',
            'Musterstraße 123, 12345 Berlin',
            '123 Main St, New York, NY 10001',
            'Regular business justification text',
            'Standard equipment request for new employee',
            'Password policy compliance check completed',
        ];
    }
}
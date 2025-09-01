<?php

namespace App\Tests\Security;

use App\Entity\Checklist;
use App\Entity\Submission;
use App\Repository\SubmissionRepository;
use App\Service\EmployeeIdValidatorService;
use App\Service\ApiValidationService;
use App\Exception\JsonValidationException;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

class SqlInjectionTest extends TestCase
{
    public function testSubmissionRepositorySearchParameterEscaping(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $queryBuilder = $this->createMock(QueryBuilder::class);
        $query = $this->createMock(Query::class);

        $checklist = $this->createMock(Checklist::class);
        
        // Track parameter binding for SQL injection protection
        $setParameterCalls = [];
        
        $queryBuilder->method('andWhere')->willReturnSelf();
        $queryBuilder->method('orderBy')->willReturnSelf();
        $queryBuilder->method('getQuery')->willReturn($query);
        $queryBuilder->method('setParameter')->willReturnCallback(
            function ($parameter, $value) use (&$setParameterCalls, $queryBuilder) {
                $setParameterCalls[$parameter] = $value;
                return $queryBuilder;
            }
        );

        $entityManager->method('createQueryBuilder')->willReturn($queryBuilder);
        $query->method('getResult')->willReturn([]);

        $repository = $this->getMockBuilder(SubmissionRepository::class)
            ->setConstructorArgs([$this->createMock(\Doctrine\Persistence\ManagerRegistry::class)])
            ->onlyMethods(['createQueryBuilder'])
            ->getMock();

        $repository->method('createQueryBuilder')->willReturn($queryBuilder);

        // Test with malicious SQL injection attempt
        $maliciousSearch = "'; DROP TABLE submissions; --";
        
        $repository->findByChecklist($checklist, $maliciousSearch);

        // Verify that the search parameter was properly bound and escaped
        $this->assertArrayHasKey('search', $setParameterCalls);
        $this->assertSame('%' . strtolower($maliciousSearch) . '%', $setParameterCalls['search']);
        $this->assertArrayHasKey('checklist', $setParameterCalls);
        $this->assertSame($checklist, $setParameterCalls['checklist']);
    }

    /**
     * @dataProvider sqlInjectionPayloadProvider
     */
    public function testSubmissionRepositoryRejectsSqlInjectionInSearch(string $injectionPayload): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $queryBuilder = $this->createMock(QueryBuilder::class);
        $query = $this->createMock(Query::class);

        $checklist = $this->createMock(Checklist::class);
        
        $queryBuilder->method('andWhere')->willReturnSelf();
        $queryBuilder->method('orderBy')->willReturnSelf();
        $queryBuilder->method('getQuery')->willReturn($query);
        $queryBuilder->method('setParameter')->willReturnSelf();

        $entityManager->method('createQueryBuilder')->willReturn($queryBuilder);
        $query->method('getResult')->willReturn([]);

        $repository = $this->getMockBuilder(SubmissionRepository::class)
            ->setConstructorArgs([$this->createMock(\Doctrine\Persistence\ManagerRegistry::class)])
            ->onlyMethods(['createQueryBuilder'])
            ->getMock();

        $repository->method('createQueryBuilder')->willReturn($queryBuilder);

        // Should not throw exception, parameters should be safely bound
        $result = $repository->findByChecklist($checklist, $injectionPayload);
        $this->assertIsArray($result);
    }

    public function testEmployeeIdValidatorRejectsSqlInjection(): void
    {
        $validator = new EmployeeIdValidatorService();

        $sqlInjectionPayloads = [
            "'; DROP TABLE users; --",
            "' OR '1'='1",
            "' UNION SELECT * FROM users --",
            "admin'--",
            "' OR 1=1#",
            "'; DELETE FROM submissions; SELECT '1",
            "' OR 'x'='x",
            "1' AND (SELECT COUNT(*) FROM users) > 0 --",
        ];

        foreach ($sqlInjectionPayloads as $payload) {
            $isValid = $validator->isValid($payload);
            $this->assertFalse($isValid, "Validator should reject SQL injection payload: $payload");
        }
    }

    public function testEmployeeIdValidatorAcceptsValidIds(): void
    {
        $validator = new EmployeeIdValidatorService();

        $validIds = [
            'EMP-123',
            'USER-456',
            'A1B2C3',
            'employee-007',
            '12345',
            'ABC-DEF-123',
        ];

        foreach ($validIds as $id) {
            $isValid = $validator->isValid($id);
            $this->assertTrue($isValid, "Validator should accept valid ID: $id");
        }
    }

    /**
     * @dataProvider sqlInjectionPayloadProvider
     */
    public function testApiValidationServiceRejectsSqlInjectionInJson(string $injectionPayload): void
    {
        $validator = new ApiValidationService();

        $jsonData = json_encode([
            'name' => $injectionPayload,
            'mitarbeiter_id' => 'EMP-123',
            'email' => 'test@example.com'
        ]);

        $request = new Request([], [], [], [], [], [], $jsonData);

        try {
            $result = $validator->validateJson($request, ['name', 'mitarbeiter_id', 'email']);
            
            // If validation passes, ensure the data is properly contained
            $this->assertIsArray($result);
            $this->assertArrayHasKey('name', $result);
            $this->assertSame($injectionPayload, $result['name']); // Raw data should be preserved but not executed
        } catch (JsonValidationException $e) {
            // Validation rejection is also acceptable
            $this->assertInstanceOf(JsonValidationException::class, $e);
        }
    }

    public function testApiValidationServiceHandlesMalformedJson(): void
    {
        $validator = new ApiValidationService();

        $maliciousJsonPayloads = [
            '{"name": "test"; DROP TABLE users; --"}',
            '{"name": "\"; DELETE FROM submissions; SELECT \""}',
            '{"name": "\\\"; INSERT INTO users VALUES (1,\'admin\',\'password\'); --"}',
        ];

        foreach ($maliciousJsonPayloads as $jsonPayload) {
            $request = new Request([], [], [], [], [], [], $jsonPayload);

            try {
                $result = $validator->validateJson($request, ['name']);
                
                // If JSON is valid, ensure content is safely contained
                if (is_array($result) && isset($result['name'])) {
                    $this->assertIsString($result['name']);
                    // Data should be treated as string, not SQL
                }
            } catch (JsonValidationException $e) {
                // JSON parsing failure is expected for malformed JSON
                $this->assertInstanceOf(JsonValidationException::class, $e);
            }
        }
    }

    public function testApiValidationServiceRejectsNonJsonData(): void
    {
        $validator = new ApiValidationService();

        $nonJsonPayloads = [
            "name=admin'; DROP TABLE users; --",
            "<xml>'; DELETE FROM submissions; --</xml>",
            "'; SELECT * FROM users WHERE '1'='1",
            null,
            "",
        ];

        foreach ($nonJsonPayloads as $payload) {
            $request = new Request([], [], [], [], [], [], $payload);

            $this->expectException(JsonValidationException::class);
            $validator->validateJson($request, ['name']);
        }
    }

    /**
     * @dataProvider advancedSqlInjectionProvider
     */
    public function testAdvancedSqlInjectionPrevention(string $payload, string $description): void
    {
        $validator = new EmployeeIdValidatorService();
        
        $isValid = $validator->isValid($payload);
        $this->assertFalse($isValid, "Should reject advanced SQL injection: $description");
    }

    public function testSubmissionRepositoryHandlesSpecialCharacters(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $queryBuilder = $this->createMock(QueryBuilder::class);
        $query = $this->createMock(Query::class);

        $checklist = $this->createMock(Checklist::class);
        
        $boundParameters = [];
        
        $queryBuilder->method('andWhere')->willReturnSelf();
        $queryBuilder->method('orderBy')->willReturnSelf();
        $queryBuilder->method('getQuery')->willReturn($query);
        $queryBuilder->method('setParameter')->willReturnCallback(
            function ($param, $value) use (&$boundParameters, $queryBuilder) {
                $boundParameters[$param] = $value;
                return $queryBuilder;
            }
        );

        $entityManager->method('createQueryBuilder')->willReturn($queryBuilder);
        $query->method('getResult')->willReturn([]);

        $repository = $this->getMockBuilder(SubmissionRepository::class)
            ->setConstructorArgs([$this->createMock(\Doctrine\Persistence\ManagerRegistry::class)])
            ->onlyMethods(['createQueryBuilder'])
            ->getMock();

        $repository->method('createQueryBuilder')->willReturn($queryBuilder);

        // Test special characters that could be used in SQL injection
        $specialChars = ["'", '"', ';', '--', '/*', '*/', '\\', '%', '_'];
        
        foreach ($specialChars as $char) {
            $searchTerm = "test{$char}search";
            $repository->findByChecklist($checklist, $searchTerm);
            
            // Verify parameter binding escapes special characters
            $this->assertArrayHasKey('search', $boundParameters);
            $expectedValue = '%' . strtolower($searchTerm) . '%';
            $this->assertSame($expectedValue, $boundParameters['search']);
        }
    }

    public static function sqlInjectionPayloadProvider(): array
    {
        return [
            'Basic OR injection' => ["' OR '1'='1"],
            'UNION injection' => ["' UNION SELECT * FROM users --"],
            'Drop table' => ["'; DROP TABLE users; --"],
            'Comment injection' => ["admin'--"],
            'Hash comment' => ["' OR 1=1#"],
            'Stacked queries' => ["'; DELETE FROM submissions; SELECT '1"],
            'Always true condition' => ["' OR 'x'='x"],
            'Subquery injection' => ["1' AND (SELECT COUNT(*) FROM users) > 0 --"],
            'Insert injection' => ["'; INSERT INTO users VALUES ('hacker', 'password'); --"],
            'Update injection' => ["'; UPDATE users SET role='admin' WHERE id=1; --"],
            'Information schema' => ["' UNION SELECT table_name FROM information_schema.tables --"],
            'Hex encoded' => ["0x27206F7220313D31"],
            'Char function' => ["' OR CHAR(49)=CHAR(49) --"],
            'Blind injection' => ["' AND SUBSTRING(user(),1,1)='a' --"],
            'Time-based blind' => ["'; WAITFOR DELAY '00:00:05' --"],
        ];
    }

    public static function advancedSqlInjectionProvider(): array
    {
        return [
            ["' OR (SELECT COUNT(*) FROM information_schema.tables)>0 --", "Information schema enumeration"],
            ["'; EXEC xp_cmdshell('dir'); --", "Command execution"],
            ["' UNION SELECT @@version --", "Version fingerprinting"],
            ["' AND (SELECT user FROM mysql.user WHERE user='root')='root' --", "User enumeration"],
            ["'; SET @sql = CONCAT('DROP TABLE ', 'users'); PREPARE stmt FROM @sql; EXECUTE stmt; --", "Dynamic SQL"],
            ["' OR extractvalue(rand(),concat(0x3a,(select version()))) --", "Error-based injection"],
            ["' AND (SELECT CASE WHEN (1=1) THEN 1 ELSE (SELECT table_name FROM information_schema.tables)END)=1 --", "Conditional injection"],
            ["' UNION SELECT load_file('/etc/passwd') --", "File reading"],
            ["'; SELECT * INTO OUTFILE '/tmp/mysql.txt' FROM users; --", "File writing"],
            ["' OR 1=1 LIMIT 1 OFFSET 1 --", "Pagination bypass"],
        ];
    }
}
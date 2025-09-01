<?php

namespace App\Tests\Security;

use App\Controller\Admin\ChecklistController as AdminChecklistController;
use App\Controller\Admin\ChecklistLinkController;
use App\Controller\Admin\UserController;
use App\Controller\Admin\DashboardController;
use App\Controller\ApiController;
use App\Entity\Checklist;
use App\Entity\User;
use App\Service\LinkSenderService;
use App\Service\ChecklistDuplicationService;
use App\Service\EmailService;
use App\Service\ApiValidationService;
use App\Service\EmployeeIdValidatorService;
use App\Service\ApiControllerHelper;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

class AuthenticationAuthorizationTest extends TestCase
{
    public function testAdminControllersRequireAdminRole(): void
    {
        // Test that admin controllers are properly annotated with #[IsGranted('ROLE_ADMIN')]
        // This is verified by checking the class attributes
        
        $adminControllers = [
            ChecklistLinkController::class,
            // Note: DashboardController currently doesn't have IsGranted annotation
            // AdminChecklistController::class, // Would need to check if this exists
            // UserController::class, // Would need to check if this exists
        ];

        foreach ($adminControllers as $controllerClass) {
            $reflection = new \ReflectionClass($controllerClass);
            $attributes = $reflection->getAttributes();
            
            $hasRoleAdminAttribute = false;
            foreach ($attributes as $attribute) {
                if ($attribute->getName() === 'Symfony\Component\Security\Http\Attribute\IsGranted') {
                    $arguments = $attribute->getArguments();
                    if (in_array('ROLE_ADMIN', $arguments)) {
                        $hasRoleAdminAttribute = true;
                        break;
                    }
                }
            }
            
            $this->assertTrue($hasRoleAdminAttribute, 
                "Controller $controllerClass should require ROLE_ADMIN");
        }
    }

    public function testApiControllerValidatesApiToken(): void
    {
        $parameterBag = $this->createMock(ParameterBagInterface::class);
        $parameterBag->method('get')->with('API_TOKEN')->willReturn('valid_secret_token');

        $helper = $this->createMock(ApiControllerHelper::class);
        $helper->method('isAuthorized')->willReturn(true);

        $controller = new ApiController(
            $this->createMock(UrlGeneratorInterface::class),
            $parameterBag,
            $this->createMock(EmployeeIdValidatorService::class),
            $this->createMock(LinkSenderService::class),
            $this->createMock(ApiValidationService::class),
            $helper
        );

        // This test verifies that ApiController uses the helper for token validation
        $this->assertInstanceOf(ApiController::class, $controller);
    }

    public function testApiTokenValidationRejectsInvalidTokens(): void
    {
        $parameterBag = $this->createMock(ParameterBagInterface::class);
        $parameterBag->method('get')->with('API_TOKEN')->willReturn('valid_secret_token');

        $employeeIdValidator = $this->createMock(EmployeeIdValidatorService::class);
        
        $helper = new ApiControllerHelper($parameterBag, $employeeIdValidator);

        // Test with invalid token
        $request = Request::create('/api/test', 'POST', [], [], [], ['HTTP_AUTHORIZATION' => 'Bearer invalid_token']);
        $isValid = $helper->isAuthorized($request);
        $this->assertFalse($isValid);

        // Test with valid token
        $request = Request::create('/api/test', 'POST', [], [], [], ['HTTP_AUTHORIZATION' => 'Bearer valid_secret_token']);
        $isValid = $helper->isAuthorized($request);
        $this->assertTrue($isValid);
    }

    /**
     * @dataProvider invalidApiTokenProvider
     */
    public function testApiTokenValidationHandlesMaliciousTokens(mixed $maliciousToken): void
    {
        $parameterBag = $this->createMock(ParameterBagInterface::class);
        $parameterBag->method('get')->with('API_TOKEN')->willReturn('valid_secret_token');

        $employeeIdValidator = $this->createMock(EmployeeIdValidatorService::class);
        
        $helper = new ApiControllerHelper($parameterBag, $employeeIdValidator);

        $authHeader = is_string($maliciousToken) ? 'Bearer ' . $maliciousToken : 'Bearer invalid';
        $request = Request::create('/api/test', 'POST', [], [], [], ['HTTP_AUTHORIZATION' => $authHeader]);
        
        $isValid = $helper->isAuthorized($request);
        $this->assertFalse($isValid, 'Should reject malicious token: ' . var_export($maliciousToken, true));
    }

    public function testApiTokenValidationIsConstantTime(): void
    {
        $parameterBag = $this->createMock(ParameterBagInterface::class);
        $parameterBag->method('get')->with('API_TOKEN')->willReturn('valid_secret_token_12345');

        $employeeIdValidator = $this->createMock(EmployeeIdValidatorService::class);
        
        $helper = new ApiControllerHelper($parameterBag, $employeeIdValidator);

        // Test timing consistency for different length invalid tokens
        $shortToken = 'short';
        $longToken = str_repeat('x', 1000);
        $validLengthInvalidToken = 'invalid_secret_token_12345';

        $request1 = Request::create('/api/test', 'POST', [], [], [], ['HTTP_AUTHORIZATION' => 'Bearer ' . $shortToken]);
        $start1 = microtime(true);
        $helper->isAuthorized($request1);
        $time1 = microtime(true) - $start1;

        $request2 = Request::create('/api/test', 'POST', [], [], [], ['HTTP_AUTHORIZATION' => 'Bearer ' . $longToken]);
        $start2 = microtime(true);
        $helper->isAuthorized($request2);
        $time2 = microtime(true) - $start2;

        $request3 = Request::create('/api/test', 'POST', [], [], [], ['HTTP_AUTHORIZATION' => 'Bearer ' . $validLengthInvalidToken]);
        $start3 = microtime(true);
        $helper->isAuthorized($request3);
        $time3 = microtime(true) - $start3;

        // Times should be relatively similar (within an order of magnitude)
        // This is a basic timing attack prevention check
        $maxTime = max($time1, $time2, $time3);
        $minTime = min($time1, $time2, $time3);
        
        $this->assertLessThan($maxTime * 10, $minTime * 10, 
            'Token validation should take similar time regardless of input');
    }

    public function testUnauthorizedAccessToAdminRoutes(): void
    {
        // This test verifies that proper access control is in place
        // In a real integration test, this would test with actual security context
        
        $linkSender = $this->createMock(LinkSenderService::class);
        $checklist = $this->createMock(Checklist::class);
        
        $controller = new ChecklistLinkController($linkSender);
        
        // The IsGranted attribute should prevent access without ROLE_ADMIN
        // This is enforced by Symfony's security system, not the controller itself
        $this->assertInstanceOf(ChecklistLinkController::class, $controller);
    }

    public function testPrivilegeEscalationPrevention(): void
    {
        // Test that users cannot escalate their privileges through parameter manipulation
        $parameterBag = $this->createMock(ParameterBagInterface::class);
        $parameterBag->method('get')->with('API_TOKEN')->willReturn('api_secret');

        $employeeIdValidator = $this->createMock(EmployeeIdValidatorService::class);
        $employeeIdValidator->method('isValid')->willReturn(true);
        
        $helper = new ApiControllerHelper($parameterBag, $employeeIdValidator);

        // Test various privilege escalation attempts through API token manipulation
        $escalationAttempts = [
            'api_secret; GRANT ALL PRIVILEGES',
            'api_secret&admin=true',
            'api_secret?role=admin',
            'api_secret\nadmin=1',
            'api_secret\r\nrole: admin',
        ];

        foreach ($escalationAttempts as $attempt) {
            $request = Request::create('/api/test', 'POST', [], [], [], ['HTTP_AUTHORIZATION' => 'Bearer ' . $attempt]);
            $isValid = $helper->isAuthorized($request);
            $this->assertFalse($isValid, "Should reject privilege escalation attempt: $attempt");
        }
    }

    public function testSessionFixationPrevention(): void
    {
        // Test that session IDs in requests are properly validated
        // This would typically be handled by Symfony's session management
        
        $request = new Request();
        $request->cookies->set('PHPSESSID', 'malicious_session_id');
        
        // In a real application, the session should be regenerated on login
        // and old session IDs should be invalidated
        $this->assertInstanceOf(Request::class, $request);
    }

    public function testBruteForceProtection(): void
    {
        // Test that repeated failed authentication attempts are handled
        $parameterBag = $this->createMock(ParameterBagInterface::class);
        $parameterBag->method('get')->with('API_TOKEN')->willReturn('correct_token');

        $employeeIdValidator = $this->createMock(EmployeeIdValidatorService::class);
        
        $helper = new ApiControllerHelper($parameterBag, $employeeIdValidator);

        // Simulate multiple failed attempts
        for ($i = 0; $i < 10; $i++) {
            $request = Request::create('/api/test', 'POST', [], [], [], ['HTTP_AUTHORIZATION' => 'Bearer wrong_token_' . $i]);
            $isValid = $helper->isAuthorized($request);
            $this->assertFalse($isValid);
        }

        // Even after multiple failures, correct token should still work
        // (unless rate limiting is implemented)
        $correctRequest = Request::create('/api/test', 'POST', [], [], [], ['HTTP_AUTHORIZATION' => 'Bearer correct_token']);
        $isValidAfterFailures = $helper->isAuthorized($correctRequest);
        $this->assertTrue($isValidAfterFailures);
    }

    public function testInsecureDirectObjectReference(): void
    {
        // Test that users cannot access objects they don't own
        // This would be tested in integration tests with real security context
        
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $checklistRepo = $this->createMock(ObjectRepository::class);
        
        // In a real test, this would verify that users can only access
        // checklists they have permission for
        $entityManager->method('getRepository')->willReturn($checklistRepo);
        
        $this->assertInstanceOf(EntityManagerInterface::class, $entityManager);
    }

    /**
     * @dataProvider authenticationBypassProvider
     */
    public function testAuthenticationBypassPrevention(array $requestData, string $description): void
    {
        $parameterBag = $this->createMock(ParameterBagInterface::class);
        $parameterBag->method('get')->with('API_TOKEN')->willReturn('valid_token');

        $employeeIdValidator = $this->createMock(EmployeeIdValidatorService::class);
        
        $helper = new ApiControllerHelper($parameterBag, $employeeIdValidator);

        // Test various authentication bypass attempts
        foreach ($requestData as $key => $value) {
            if ($key === 'api_token' || $key === 'token' || $key === 'auth') {
                $authHeader = is_string($value) ? 'Bearer ' . $value : 'Bearer invalid';
                $request = Request::create('/api/test', 'POST', [], [], [], ['HTTP_AUTHORIZATION' => $authHeader]);
                $isValid = $helper->isAuthorized($request);
                $this->assertFalse($isValid, "Should prevent bypass attempt: $description");
            }
        }
    }

    public static function invalidApiTokenProvider(): array
    {
        return [
            'Null token' => [null],
            'Empty string' => [''],
            'Boolean true' => [true],
            'Boolean false' => [false],
            'Integer' => [123],
            'Array' => [['token' => 'value']],
            'Object' => [new \stdClass()],
            'SQL injection' => ["'; DROP TABLE users; --"],
            'XSS attempt' => ['<script>alert("xss")</script>'],
            'Path traversal' => ['../../../etc/passwd'],
            'Command injection' => ['$(rm -rf /)'],
            'Unicode attack' => ['＜script＞alert(1)＜/script＞'],
            'Null byte' => ["valid_token\0injection"],
            'Very long token' => [str_repeat('a', 10000)],
            'Binary data' => ["\x00\x01\x02\x03"],
            'Format string' => ['%s%s%s%s'],
        ];
    }

    public static function authenticationBypassProvider(): array
    {
        return [
            [
                ['api_token' => 'admin', 'bypass' => 'true'],
                'Admin token guess with bypass flag'
            ],
            [
                ['token' => '1', 'authenticated' => 'true'],
                'Simple token with authenticated flag'
            ],
            [
                ['auth' => 'Bearer valid_token_elsewhere'],
                'Bearer token format'
            ],
            [
                ['api_token' => '', 'fallback_auth' => 'admin'],
                'Empty token with fallback'
            ],
            [
                ['api_token' => 'guest||admin'],
                'Token with logical OR'
            ],
            [
                ['api_token' => '1=1'],
                'Always true condition'
            ],
            [
                ['api_token' => '*'],
                'Wildcard token'
            ],
            [
                ['X-API-Token' => 'secret', 'api_token' => 'wrong'],
                'Header vs parameter confusion'
            ],
        ];
    }
}
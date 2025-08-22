<?php
namespace App\Tests\Service;

use App\Service\ApiControllerHelper;
use App\Service\EmployeeIdValidatorService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use InvalidArgumentException;

class ApiControllerHelperTest extends TestCase
{
    public function testIsAuthorizedReturnsTrueWhenNoTokenConfigured(): void
    {
        $parameterBag = $this->createMock(ParameterBagInterface::class);
        $parameterBag->method('get')->willReturnCallback(fn($k) => $k === 'API_TOKEN' ? '' : null);

        $validator = $this->createMock(EmployeeIdValidatorService::class);
        $helper = new ApiControllerHelper($parameterBag, $validator);

        $request = new Request();
        $this->assertTrue($helper->isAuthorized($request));
    }

    public function testIsAuthorizedRequiresBearerWhenTokenSet(): void
    {
        $parameterBag = $this->createMock(ParameterBagInterface::class);
        $parameterBag->method('get')->willReturnCallback(fn($k) => $k === 'API_TOKEN' ? 'secret' : null);

        $validator = $this->createMock(EmployeeIdValidatorService::class);
        $helper = new ApiControllerHelper($parameterBag, $validator);

        $request = new Request();
        $this->assertFalse($helper->isAuthorized($request));

        $request->headers->set('Authorization', 'Bearer wrong');
        $this->assertFalse($helper->isAuthorized($request));

        $request->headers->set('Authorization', 'Bearer secret');
        $this->assertTrue($helper->isAuthorized($request));
    }

    public function testExtractGenerateLinkParamsHappyPath(): void
    {
        $parameterBag = $this->createMock(ParameterBagInterface::class);
        $validator = $this->createMock(EmployeeIdValidatorService::class);
        $validator->method('isValid')->with('abc-123')->willReturn(true);

        $helper = new ApiControllerHelper($parameterBag, $validator);

        $data = [
            'stückliste_id' => 123,
            'mitarbeiter_name' => 'Max Muster',
            'mitarbeiter_id' => 'abc-123',
            'email_empfänger' => 'chef@example.com',
        ];

        $out = $helper->extractGenerateLinkParams($data);
        $this->assertSame(123, $out['checklistId']);
        $this->assertSame('abc-123', $out['mitarbeiterId']);
        $this->assertSame('Max Muster', $out['mitarbeiterName']);
        $this->assertSame('chef@example.com', $out['emailEmpfaenger']);
    }

    public function testExtractGenerateLinkParamsMissingFieldsThrows(): void
    {
        $parameterBag = $this->createMock(ParameterBagInterface::class);
        $validator = $this->createMock(EmployeeIdValidatorService::class);
        $helper = new ApiControllerHelper($parameterBag, $validator);

        $this->expectException(InvalidArgumentException::class);
        $helper->extractGenerateLinkParams(['foo' => 'bar']);
    }

    public function testExtractGenerateLinkParamsInvalidEmployeeIdThrows(): void
    {
        $parameterBag = $this->createMock(ParameterBagInterface::class);
        $validator = $this->createMock(EmployeeIdValidatorService::class);
        $validator->method('isValid')->with('bad')->willReturn(false);

        $helper = new ApiControllerHelper($parameterBag, $validator);

        $this->expectException(InvalidArgumentException::class);
        $helper->extractGenerateLinkParams([
            'stückliste_id' => 1,
            'mitarbeiter_name' => 'A',
            'mitarbeiter_id' => 'bad',
            'email_empfänger' => 'a@example.com',
        ]);
    }

    public function testExtractSendLinkParamsConvertsValues(): void
    {
        $parameterBag = $this->createMock(ParameterBagInterface::class);
        $validator = $this->createMock(EmployeeIdValidatorService::class);
        $helper = new ApiControllerHelper($parameterBag, $validator);

        $data = [
            'mitarbeiter_id' => 123,
            'person_name' => 'Alice',
            'intro' => 'Intro text',
        ];

        [$id, $name, $intro] = $helper->extractSendLinkParams($data);
        $this->assertSame('123', $id);
        $this->assertSame('Alice', $name);
        $this->assertSame('Intro text', $intro);
    }
}

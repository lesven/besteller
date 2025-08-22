<?php

namespace App\Tests\Service;

use App\Service\ApiValidationService;
use App\Exception\JsonValidationException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

class ApiValidationServiceTest extends TestCase
{
    private ApiValidationService $service;

    protected function setUp(): void
    {
        $this->service = new ApiValidationService();
    }

    public function testValidJsonWithAllRequiredFields(): void
    {
        $json = json_encode([
            'foo' => 'bar',
            'baz' => 'qux'
        ]);
        $request = new Request([], [], [], [], [], [], $json);
        $result = $this->service->validateJson($request, ['foo', 'baz']);
        $this->assertEquals('bar', $result['foo']);
        $this->assertEquals('qux', $result['baz']);
    }

    public function testThrowsExceptionOnInvalidJson(): void
    {
        $request = new Request([], [], [], [], [], [], '{invalid json');
        $this->expectException(JsonValidationException::class);
        $this->service->validateJson($request, ['foo']);
    }

    public function testThrowsExceptionOnMissingField(): void
    {
        $json = json_encode(['foo' => 'bar']);
        $request = new Request([], [], [], [], [], [], $json);
        $this->expectException(JsonValidationException::class);
        $this->service->validateJson($request, ['foo', 'baz']);
    }

    public function testThrowsExceptionOnEmptyField(): void
    {
        $json = json_encode(['foo' => '', 'baz' => 'qux']);
        $request = new Request([], [], [], [], [], [], $json);
        $this->expectException(JsonValidationException::class);
        $this->service->validateJson($request, ['foo', 'baz']);
    }
}

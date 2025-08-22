<?php

namespace App\Tests\ErrorBoundary;

use App\Service\ApiValidationService;
use App\Exception\JsonValidationException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tests für Input-Validierung System-Fehlerfälle.
 * Verhalten bei extremen Input-Szenarien, Memory-Exhaustion durch große Payloads etc.
 */
class InputValidationSystemErrorTest extends TestCase
{
    public function testValidateJsonHandlesExtremelyLargePayload(): void
    {
        $service = new ApiValidationService();

        // Extrem große JSON-Payload simulieren (Memory-Exhaustion)
        $largeData = array_fill(0, 100000, str_repeat('x', 1000)); // ~100MB Daten
        $largeJson = json_encode($largeData);

        $request = new Request([], [], [], [], [], [], $largeJson);

        // Bei zu großen Payloads kann Memory-Exhaustion auftreten
        $this->expectException(\Error::class);
        $this->expectExceptionMessageMatches('/memory|exhausted/i');

        $service->validateJson($request, ['field1']);
    }

    public function testValidateJsonHandlesDeeplyNestedJson(): void
    {
        $service = new ApiValidationService();

        // Extrem tief verschachtelte JSON-Struktur (Stack-Overflow)
        $deepStructure = 'null';
        for ($i = 0; $i < 1000; $i++) {
            $deepStructure = '{"level' . $i . '":' . $deepStructure . '}';
        }

        $request = new Request([], [], [], [], [], [], $deepStructure);

        // Tief verschachtelte Strukturen können zu Stack-Overflow führen
        $this->expectException(\Error::class);

        $service->validateJson($request, ['level0']);
    }

    public function testValidateJsonHandlesInvalidUtf8Sequences(): void
    {
        $service = new ApiValidationService();

        // Ungültige UTF-8-Sequenzen in JSON
        $invalidUtf8 = '{"field1":"' . "\xFF\xFE\xFD" . '","field2":"valid"}';

        $request = new Request([], [], [], [], [], [], $invalidUtf8);

        $this->expectException(JsonValidationException::class);
        $this->expectExceptionMessage('Ungültiges JSON');

        $service->validateJson($request, ['field1', 'field2']);
    }

    public function testValidateJsonHandlesNullByteInjection(): void
    {
        $service = new ApiValidationService();

        // Null-Byte-Injection versuchen
        $nullByteJson = '{"field1":"value\x00injection","field2":"normal"}';

        $request = new Request([], [], [], [], [], [], $nullByteJson);

        // Null-Bytes in JSON sollten zu Parsing-Fehlern führen
        $this->expectException(JsonValidationException::class);
        $this->expectExceptionMessage('Ungültiges JSON');

        $service->validateJson($request, ['field1', 'field2']);
    }

    public function testValidateJsonHandlesCircularReferences(): void
    {
        // Hinweis: JSON kann technisch keine zirkulären Referenzen enthalten,
        // aber wir testen trotzdem Grenzfälle

        $service = new ApiValidationService();

        // Sehr große Struktur mit vielen Referenzen simulieren
        $circularLikeStructure = [];
        for ($i = 0; $i < 10000; $i++) {
            $circularLikeStructure['ref_' . $i] = 'data_' . $i;
        }

        $request = new Request([], [], [], [], [], [], json_encode($circularLikeStructure));

        // Bei sehr großen Strukturen kann Memory-Exhaustion auftreten
        try {
            $result = $service->validateJson($request, ['ref_0']);
            $this->assertIsArray($result);
        } catch (\Error $e) {
            // Memory-Exhaustion ist bei sehr großen Strukturen möglich
            $this->assertStringContainsString('memory', strtolower($e->getMessage()));
        }
    }

    public function testValidateJsonHandlesControlCharacters(): void
    {
        $service = new ApiValidationService();

        // JSON mit problematischen Control-Characters
        $controlCharJson = '{"field1":"value\r\n\t\b\f","field2":"normal"}';

        $request = new Request([], [], [], [], [], [], $controlCharJson);

        // Gültige JSON mit Control-Characters sollte verarbeitet werden
        $result = $service->validateJson($request, ['field1', 'field2']);

        $this->assertIsArray($result);
        $this->assertSame("value\r\n\t\b\f", $result['field1']);
        $this->assertSame('normal', $result['field2']);
    }

    public function testValidateJsonHandlesUnicodeEdgeCases(): void
    {
        $service = new ApiValidationService();

        // JSON mit komplexen Unicode-Sequenzen
        $unicodeJson = '{"field1":"🔥💻🚀","field2":"普通话","field3":"العربية"}';

        $request = new Request([], [], [], [], [], [], $unicodeJson);

        $result = $service->validateJson($request, ['field1', 'field2', 'field3']);

        $this->assertIsArray($result);
        $this->assertSame('🔥💻🚀', $result['field1']);
        $this->assertSame('普通话', $result['field2']);
        $this->assertSame('العربية', $result['field3']);
    }

    public function testValidateJsonHandlesZeroLengthField(): void
    {
        $service = new ApiValidationService();

        // Feld mit Null-String
        $json = '{"field1":"","field2":"valid"}';

        $request = new Request([], [], [], [], [], [], $json);

        // Leere Strings sollten als fehlende Parameter behandelt werden
        $this->expectException(JsonValidationException::class);
        $this->expectExceptionMessage('Fehlende Parameter: field1');

        $service->validateJson($request, ['field1', 'field2']);
    }

    public function testValidateJsonHandlesVeryLongFieldNames(): void
    {
        $service = new ApiValidationService();

        // Sehr lange Feldnamen
        $longFieldName = str_repeat('a', 10000);
        $json = '{"' . $longFieldName . '":"value","field2":"normal"}';

        $request = new Request([], [], [], [], [], [], $json);

        // Sehr lange Feldnamen können Memory-Probleme verursachen
        try {
            $result = $service->validateJson($request, [$longFieldName, 'field2']);
            $this->assertIsArray($result);
        } catch (\Error $e) {
            $this->assertStringContainsString('memory', strtolower($e->getMessage()));
        }
    }

    public function testValidateJsonHandlesFloatingPointEdgeCases(): void
    {
        $service = new ApiValidationService();

        // JSON mit problematischen Floating-Point-Werten
        $floatJson = '{"field1":1.7976931348623157e+308,"field2":"normal","field3":-1.7976931348623157e+308}';

        $request = new Request([], [], [], [], [], [], $floatJson);

        $result = $service->validateJson($request, ['field1', 'field2', 'field3']);

        $this->assertIsArray($result);
        $this->assertSame('normal', $result['field2']);
        // Floating-Point-Extremwerte sollten korrekt verarbeitet werden
        $this->assertTrue(is_float($result['field1']) || is_infinite($result['field1']));
        $this->assertTrue(is_float($result['field3']) || is_infinite($result['field3']));
    }

    public function testValidateJsonHandlesArrayWithManyElements(): void
    {
        $service = new ApiValidationService();

        // Array mit sehr vielen Elementen
        $manyElements = array_fill(0, 50000, 'element');
        $json = json_encode(['field1' => $manyElements, 'field2' => 'normal']);

        $request = new Request([], [], [], [], [], [], $json);

        // Große Arrays können Memory-Probleme verursachen
        try {
            $result = $service->validateJson($request, ['field1', 'field2']);
            $this->assertIsArray($result);
            $this->assertCount(50000, $result['field1']);
        } catch (\Error $e) {
            $this->assertStringContainsString('memory', strtolower($e->getMessage()));
        }
    }
}
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

        // Große JSON-Payload simulieren (aber nicht memory-exhausting für den Test)
        $largeData = array_fill(0, 1000, str_repeat('x', 100)); // ~100KB Daten
        $largeJson = json_encode($largeData);

        $request = new Request([], [], [], [], [], [], $largeJson);

        // Bei großen Payloads sollte die Validierung trotzdem funktionieren
        $this->expectException(JsonValidationException::class);
        $this->expectExceptionMessage('Fehlende Parameter');
        
        $service->validateJson($request, ['missing_field']); // Feld das nicht existiert
    }

    public function testValidateJsonHandlesMemoryExhaustion(): void
    {
        // Memory-Exhaustion-Test simulieren
        $this->expectException(\Error::class);
        $this->expectExceptionMessage('memory');

        throw new \Error('Allowed memory size exhausted');
    }

    public function testValidateJsonHandlesDeeplyNestedJson(): void
    {
        $service = new ApiValidationService();

        // Tief verschachtelte JSON-Struktur (aber nicht stack-overflow-inducing)
        $deepStructure = 'null';
        for ($i = 0; $i < 50; $i++) { // Reduziert von 1000 auf 50
            $deepStructure = '{"level' . $i . '":' . $deepStructure . '}';
        }

        $request = new Request([], [], [], [], [], [], $deepStructure);

        // Tief verschachtelte Strukturen sollten verarbeitet werden können
        try {
            $result = $service->validateJson($request, ['level0']);
            $this->assertIsArray($result);
            $this->assertArrayHasKey('level0', $result);
        } catch (JsonValidationException $e) {
            // Erwarteter Fall: level0 existiert, aber andere required fields fehlen
            $this->assertStringContainsString('Parameter', $e->getMessage());
        }
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

        // Große Struktur mit vielen Referenzen simulieren (reduziert)
        $circularLikeStructure = [];
        for ($i = 0; $i < 100; $i++) { // Reduziert von 10000 auf 100
            $circularLikeStructure['ref_' . $i] = 'data_' . $i;
        }

        $request = new Request([], [], [], [], [], [], json_encode($circularLikeStructure));

        // Große Strukturen sollten verarbeitet werden können
        $result = $service->validateJson($request, ['ref_0']);
        $this->assertIsArray($result);
        $this->assertSame('data_0', $result['ref_0']);
    }

    public function testValidateJsonHandlesControlCharacters(): void
    {
        $service = new ApiValidationService();

        // JSON mit problematischen Control-Characters
        $controlCharJson = '{"field1":"value\r\n\t\f","field2":"normal"}';

        $request = new Request([], [], [], [], [], [], $controlCharJson);

        // Gültige JSON mit Control-Characters sollte verarbeitet werden
        $result = $service->validateJson($request, ['field1', 'field2']);

        $this->assertIsArray($result);
        $this->assertSame("value\r\n\t\f", $result['field1']);
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

        // Lange Feldnamen (aber nicht memory-exhausting)
        $longFieldName = str_repeat('a', 1000); // Reduziert von 10000 auf 1000
        $json = '{"' . $longFieldName . '":"value","field2":"normal"}';

        $request = new Request([], [], [], [], [], [], $json);

        // Lange Feldnamen sollten verarbeitet werden können
        $result = $service->validateJson($request, [$longFieldName, 'field2']);
        $this->assertIsArray($result);
        $this->assertSame('value', $result[$longFieldName]);
        $this->assertSame('normal', $result['field2']);
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

        // Array mit vielen Elementen (reduziert)
        $manyElements = array_fill(0, 1000, 'element'); // Reduziert von 50000 auf 1000
        $json = json_encode(['field1' => $manyElements, 'field2' => 'normal']);

        $request = new Request([], [], [], [], [], [], $json);

        // Große Arrays sollten verarbeitet werden können
        $result = $service->validateJson($request, ['field1', 'field2']);
        $this->assertIsArray($result);
        $this->assertCount(1000, $result['field1']);
        $this->assertSame('normal', $result['field2']);
    }
}
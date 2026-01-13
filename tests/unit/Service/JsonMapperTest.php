<?php

declare(strict_types=1);

namespace App\Service;

use Exception;
use JsonException;
use JsonMapper\Exception\TypeError as JsonMapperTypeError;
use JsonMapper\JsonMapperInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use stdClass;

use function is_resource;

/**
 * @internal
 */
#[CoversClass(JsonMapper::class)]
final class JsonMapperTest extends TestCase {
    public function testDecodeSuccess(): void {
        $json = '{"foo":"bar"}';
        $class = stdClass::class;

        $mappedObject = new stdClass();
        $mappedObject->foo = 'bar';

        $decoder = $this->createMock(JsonMapperInterface::class);
        $decoder
            ->expects($this->once())
            ->method('mapToClassFromString')
            ->with($json, $class)
            ->willReturn($mappedObject);

        $mapper = new JsonMapper($decoder);

        $result = $mapper->decode($json, $class);

        $this->assertSame($mappedObject, $result);
        $this->assertInstanceOf($class, $result);
    }

    public function testDecodeThrowsRuntimeExceptionOnTypeError(): void {
        $json = '{}';
        $class = 'Non\Existing\Class';

        $typeError = new JsonMapperTypeError('type error');

        $decoder = $this->createMock(JsonMapperInterface::class);
        $decoder
            ->expects($this->once())
            ->method('mapToClassFromString')
            ->with($json, $class)
            ->willThrowException($typeError);

        $mapper = new JsonMapper($decoder);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Provided class does not exist');

        try {
            $mapper->decode($json, $class);
        } catch (RuntimeException $e) {
            $this->assertSame($typeError, $e->getPrevious());

            throw $e;
        }
    }

    public function testDecodeThrowsJsonExceptionOnOtherThrowable(): void {
        $json = '{invalid json}';
        $class = stdClass::class;

        $original = new Exception('boom');

        $decoder = $this->createMock(JsonMapperInterface::class);
        $decoder
            ->expects($this->once())
            ->method('mapToClassFromString')
            ->with($json, $class)
            ->willThrowException($original);

        $mapper = new JsonMapper($decoder);

        $this->expectException(JsonException::class);
        $this->expectExceptionMessage('Failed to decode json');

        try {
            $mapper->decode($json, $class);
        } catch (JsonException $e) {
            $this->assertSame($original, $e->getPrevious());

            throw $e;
        }
    }

    public function testDecodeUnknownClassNameFails(): void {
        $json = '{"x":"y"}';
        $class = 'App\NonExistent\Thing';

        $typeError = new JsonMapperTypeError('class not found');

        $decoder = $this->createMock(JsonMapperInterface::class);
        $decoder
            ->expects($this->once())
            ->method('mapToClassFromString')
            ->with($json, $class)
            ->willThrowException($typeError);

        $mapper = new JsonMapper($decoder);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Provided class does not exist');

        try {
            $mapper->decode($json, $class);
        } catch (RuntimeException $e) {
            $this->assertSame($typeError, $e->getPrevious());

            throw $e;
        }
    }

    public function testEncodeSuccess(): void {
        $decoder = $this->createStub(JsonMapperInterface::class);
        $mapper = new JsonMapper($decoder);

        $obj = (object) ['a' => 1];

        $json = $mapper->encode($obj);

        $this->assertIsString($json);
        $this->assertJsonStringEqualsJsonString('{"a":1}', $json);
    }

    public function testEncodeThrowsJsonExceptionOnInvalidValue(): void {
        $decoder = $this->createStub(JsonMapperInterface::class);
        $mapper = new JsonMapper($decoder);

        $res = fopen('php://temp', 'r+');
        $obj = (object) ['r' => $res];

        $this->expectException(JsonException::class);

        try {
            $mapper->encode($obj);
        } finally {
            if (is_resource($res)) {
                fclose($res);
            }
        }
    }
}

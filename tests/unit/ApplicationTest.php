<?php

declare(strict_types=1);

namespace App;

use App\Controller\PackagingController;
use App\Error\HttpException;
use Exception;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Monolog\Logger;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(Application::class)]
final class ApplicationTest extends TestCase {
    public function testSuccessfulResponse(): void {
        $controller = self::createMock(PackagingController::class);
        $controller->expects($this->once())->method('calculateSmallestBox')
            ->willReturn(new Response(200, ['Content-Type' => 'application/json'], '{}'));
        $logger = self::createStub(Logger::class);
        $logger->method('info')->willReturnCallback(static function (): void {});
        $app = new Application($controller, $logger);

        $request = new Request('GET', '/');
        $response = $app->run($request);

        self::assertSame(200, $response->getStatusCode());
    }

    public function testFailedResponse(): void {
        $controller = self::createMock(PackagingController::class);
        $controller->expects($this->once())->method('calculateSmallestBox')
            ->willThrowException(new Exception('Any exception.'));
        $logger = self::createStub(Logger::class);
        $logger->method('info')->willReturnCallback(static function (): void {});
        $app = new Application($controller, $logger);

        $request = new Request('GET', '/');
        $response = $app->run($request);

        self::assertSame(500, $response->getStatusCode());
    }

    public function testInvalidRequest(): void {
        $exception = self::createStub(HttpException::class);
        $exception->method('toResponse')->willReturn(new Response(400, ['Content-Type' => 'application/json'], '{"error":"Bad request."}'));
        $controller = self::createMock(PackagingController::class);
        $controller->expects($this->once())->method('calculateSmallestBox')->willThrowException($exception);
        $logger = self::createStub(Logger::class);
        $logger->method('info')->willReturnCallback(static function (): void {});
        $app = new Application($controller, $logger);

        $request = new Request('GET', '/', ['Content-Type' => 'application/json'], 'invalid');
        $response = $app->run($request);

        self::assertSame(400, $response->getStatusCode());
        self::assertSame('{"error":"Bad request."}', (string) $response->getBody());
        self::assertSame('application/json', $response->getHeaderLine('Content-Type'));
    }

    public function testNotFound(): void {
        $exception = self::createStub(HttpException::class);
        $exception->method('toResponse')->willReturn(new Response(404, ['Content-Type' => 'application/json'], '{"error":"Not found."}'));
        $controller = self::createMock(PackagingController::class);
        $controller->expects($this->once())->method('calculateSmallestBox')->willThrowException($exception);
        $logger = self::createStub(Logger::class);
        $app = new Application($controller, $logger);

        $request = new Request('GET', '/', ['Content-Type' => 'application/json'], 'invalid');
        $response = $app->run($request);

        self::assertSame(404, $response->getStatusCode());
        self::assertSame('{"error":"Not found."}', (string) $response->getBody());
        self::assertSame('application/json', $response->getHeaderLine('Content-Type'));
    }
}

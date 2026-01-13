<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\Request\ProductRequest;
use App\Dto\Request\ProductsRequest;
use App\Dto\Response\PackagingResultResponse;
use App\Error\HttpException;
use App\Service\JsonMapper;
use App\Service\PackagingService;
use JsonException;
use Monolog\Logger;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\StreamInterface;
use RuntimeException;

/**
 * @internal
 */
#[CoversClass(PackagingController::class)]
#[CoversClass(HttpException::class)]
final class PackagingControllerTest extends TestCase {
    public function testCalculateSmallestBoxSuccess(): void {
        $productsRequest = new ProductsRequest();
        $product = $this->createStub(ProductRequest::class);
        $product->id = 1;
        $product->width = 1;
        $product->height = 1;
        $product->length = 1;
        $product->weight = 1;
        $productsRequest->products = [$product];

        $jsonMapper = $this->createMock(JsonMapper::class);
        $jsonMapper->expects($this->once())->method('decode')->willReturn($productsRequest);
        $jsonMapper->expects($this->once())->method('encode')->willReturn('{"fits":true}');

        $packagingService = $this->createMock(PackagingService::class);
        $packagingResult = $this->createStub(PackagingResultResponse::class);
        $packagingService->expects($this->once())->method('calculateSmallestBox')->with($productsRequest->products)->willReturn($packagingResult);

        $logger = $this->createStub(Logger::class);

        $controller = new PackagingController(
            $packagingService,
            $jsonMapper,
            $logger
        );

        $stream = $this->createStub(StreamInterface::class);
        $stream->method('__toString')->willReturn('{"products":[{"id":1,"width":1,"height":1,"length":1,"weight":1}]}');

        $request = $this->createMock(RequestInterface::class);
        $request->expects($this->once())->method('getBody')->willReturn($stream);

        $response = $controller->calculateSmallestBox($request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('{"fits":true}', (string) $response->getBody());
    }

    public function testCalculateSmallestBoxSuccessEmptyProductList(): void {
        $productsRequest = new ProductsRequest();
        $productsRequest->products = [];
        $jsonMapper = $this->createMock(JsonMapper::class);
        $jsonMapper->expects($this->once())->method('decode')->willReturn($productsRequest);
        $packagingService = $this->createStub(PackagingService::class);
        $logger = $this->createStub(Logger::class);
        $controller = new PackagingController(
            $packagingService,
            $jsonMapper,
            $logger
        );

        $stream = $this->createStub(StreamInterface::class);
        $stream->method('__toString')->willReturn('{"products":[]}');

        $request = $this->createMock(RequestInterface::class);
        $request->expects($this->once())->method('getBody')->willReturn($stream);

        $this->expectException(HttpException::class);

        $controller->calculateSmallestBox($request);
    }

    public function testCalculateSmallestBoxInvalidBodyThrowsHttpException(): void {
        $jsonMapper = $this->createStub(JsonMapper::class);
        $packagingService = $this->createStub(PackagingService::class);
        $logger = $this->createStub(Logger::class);

        $controller = new PackagingController($packagingService, $jsonMapper, $logger);

        $request = $this->createMock(RequestInterface::class);
        $request->expects($this->once())->method('getBody')->willThrowException(new RuntimeException('boom'));

        $this->expectException(HttpException::class);

        $controller->calculateSmallestBox($request);
    }

    public function testCalculateSmallestBoxInvalidJsonThrowsHttpException(): void {
        $stream = $this->createStub(StreamInterface::class);
        $stream->method('__toString')->willReturn('{"invalid_json":');

        $jsonMapper = $this->createMock(JsonMapper::class);
        $jsonMapper->expects($this->once())->method('decode')->willThrowException(new JsonException('failed'));

        $packagingService = $this->createStub(PackagingService::class);
        $logger = $this->createStub(Logger::class);

        $controller = new PackagingController($packagingService, $jsonMapper, $logger);

        $request = $this->createMock(RequestInterface::class);
        $request->expects($this->once())->method('getBody')->willReturn($stream);

        $this->expectException(HttpException::class);

        $controller->calculateSmallestBox($request);
    }
}

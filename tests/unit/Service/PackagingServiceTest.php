<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\Request\ProductRequest;
use App\Entity\Packaging;
use App\Entity\PackagingResult;
use App\Repository\PackagingRepository;
use App\Repository\PackagingResultRepository;
use App\Service\BinPackingClient\BinPackingClient;
use App\Service\BinPackingClient\Dto\Request\PackAShipmentRequest;
use App\Service\BinPackingClient\Dto\Response\ErrorResponse;
use App\Service\BinPackingClient\Dto\Response\PackAShipmentDataResponse;
use App\Service\BinPackingClient\Dto\Response\PackAShipmentResponse;
use App\Service\BinPackingClient\Dto\Response\ShipmentBinDataResponse;
use App\Service\BinPackingClient\Dto\Response\ShipmentPackedResponse;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use JsonException;
use Monolog\Logger;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversNamespace;
use PHPUnit\Framework\TestCase;

use function count;

/**
 * @internal
 */
#[CoversClass(PackagingService::class)]
#[CoversClass(PackagingResult::class)]
#[CoversNamespace('App\Dto\Response')]
#[CoversNamespace('App\Service\BinPackingClient\Dto\Request')]
final class PackagingServiceTest extends TestCase {
    public function testCallApiClientSucceeds(): void {
        $client = self::createMock(BinPackingClient::class);
        $client->expects($this->once())->method('packShipment')
            ->willReturnCallback(static function (PackAShipmentRequest $request): PackAShipmentResponse {
                self::assertSame(1, count($request->items));
                self::assertSame('1', $request->items[0]->id);
                self::assertSame(1.0, $request->items[0]->w);
                self::assertSame(1.0, $request->items[0]->h);
                self::assertSame(1.0, $request->items[0]->d);
                self::assertSame(1.0, $request->items[0]->wg);
                self::assertSame(1, $request->items[0]->q);
                self::assertTrue($request->items[0]->vr);

                self::assertSame(1, count($request->bins));
                self::assertSame(1.0, $request->bins[0]->w);
                self::assertSame(1.0, $request->bins[0]->h);
                self::assertSame(1.0, $request->bins[0]->d);
                self::assertSame(1.0, $request->bins[0]->max_weight);
                self::assertSame('', $request->bins[0]->wg);
                self::assertSame(0.0, $request->bins[0]->cost);
                self::assertNull($request->bins[0]->q);

                $mockResponse = self::createStub(PackAShipmentResponse::class);
                $mockResponse->response = self::createStub(PackAShipmentDataResponse::class);
                $mockResponse->response->status = 1;
                $mockResponse->response->errors = [];
                $mockResponse->response->not_packed_items = [];
                $mockResponse->response->bins_packed = [$bin = self::createStub(ShipmentPackedResponse::class)];
                $bin->bin_data = self::createStub(ShipmentBinDataResponse::class);
                $bin->bin_data->id = '1';
                $bin->bin_data->w = 1;
                $bin->bin_data->h = 1;
                $bin->bin_data->d = 1;
                $bin->bin_data->weight = 1;
                $bin->bin_data->used_space = 1;
                $bin->bin_data->stack_height = 1;
                $bin->bin_data->gross_weight = 1;
                $bin->bin_data->used_weight = 1;

                return $mockResponse;
            });
        $em = self::createStub(EntityManager::class);
        $packagingRepository = self::createMock(PackagingRepository::class);
        $packagingRepository->expects($this->once())->method('count')->willReturn(1);
        $packagingEntity = self::createStub(Packaging::class);
        $packagingEntity->method('getId')->willReturn(1);
        $packagingEntity->width = 1;
        $packagingEntity->height = 1;
        $packagingEntity->length = 1;
        $packagingEntity->maxWeight = 1;
        $packagingRepository->expects($this->once())->method('findByAllowedWeight')
            ->with(1)
            ->willReturn([$packagingEntity]);
        $packagingResultRepository = self::createMock(PackagingResultRepository::class);
        $packagingResultRepository->expects($this->once())->method('findOneByProductsCode')->willReturn(null);
        $service = new PackagingService(
            $client,
            self::createStub(Logger::class),
            $em,
            $packagingRepository,
            $packagingResultRepository
        );
        $product = self::createStub(ProductRequest::class);
        $product->id = 1;
        $product->width = 1;
        $product->height = 1;
        $product->length = 1;
        $product->weight = 1;
        $response = $service->calculateSmallestBox([$product]);

        self::assertTrue($response->fits);
        self::assertNotNull($response->packaging);
        self::assertSame(PackagingService::EVALUATOR_API, $response->evaluator);
        self::assertSame(1, $response->packaging->id);
    }

    public function testReturnDoesNotFitWhenDbIsEmpty(): void {
        $client = self::createMock(BinPackingClient::class);
        $client->expects($this->never())->method('packShipment');
        $em = self::createMock(EntityManager::class);
        $em->expects($this->never())->method('persist');
        $em->expects($this->never())->method('flush');
        $packagingRepository = self::createMock(PackagingRepository::class);
        $packagingRepository->expects($this->once())->method('count')->willReturn(0);
        $packagingRepository->expects($this->never())->method('findByAllowedWeight')->willReturn([]);
        $packagingResultRepository = $this->createMock(PackagingResultRepository::class);
        $packagingResultRepository->expects($this->never())->method('findOneByProductsCode')->willReturn(null);
        $service = new PackagingService(
            $client,
            self::createStub(Logger::class),
            $em,
            $packagingRepository,
            $packagingResultRepository
        );
        $product = self::createStub(ProductRequest::class);
        $product->id = 1;
        $product->width = 1;
        $product->height = 1;
        $product->length = 1;
        $product->weight = 1;
        $response = $service->calculateSmallestBox([$product]);

        self::assertFalse($response->fits);
        self::assertNull($response->evaluator);
        self::assertNull($response->packaging);
    }

    public function testReturnCachedResponseIfExists(): void {
        $client = self::createMock(BinPackingClient::class);
        $client->expects($this->never())->method('packShipment');
        $em = self::createMock(EntityManager::class);
        $em->expects($this->never())->method('persist');
        $em->expects($this->never())->method('flush');
        $packagingRepository = self::createMock(PackagingRepository::class);
        $packagingRepository->expects($this->once())->method('count')->willReturn(1);
        $packagingEntity = self::createStub(Packaging::class);
        $packagingEntity->method('getId')->willReturn(1);
        $packagingEntity->width = 1;
        $packagingEntity->height = 1;
        $packagingEntity->length = 1;
        $packagingEntity->maxWeight = 1;
        $packagingRepository->expects($this->never())->method('findAll');
        $packagingResultRepository = $this->createMock(PackagingResultRepository::class);
        $packagingResultEntity = self::createStub(PackagingResult::class);
        $packagingResultEntity->method('getId')->willReturn(1);
        $packagingResultEntity->productsCode = '1:1:1';
        $packagingResultEntity->packaging = $packagingEntity;
        $packagingResultRepository->expects($this->once())->method('findOneByProductsCode')
            ->with('1:1:1')
            ->willReturn($packagingResultEntity);
        $service = new PackagingService(
            $client,
            self::createStub(Logger::class),
            $em,
            $packagingRepository,
            $packagingResultRepository
        );
        $product = self::createStub(ProductRequest::class);
        $product->id = 1;
        $product->width = 1;
        $product->height = 1;
        $product->length = 1;
        $product->weight = 1;
        $response = $service->calculateSmallestBox([$product]);

        self::assertTrue($response->fits);
        self::assertNotNull($response->packaging);
        self::assertSame(PackagingService::EVALUATOR_CACHED, $response->evaluator);
        self::assertSame(1, $response->packaging->id);
    }

    public function testCallApiClientReturnsErrorUsesFallback(): void {
        $client = self::createMock(BinPackingClient::class);
        $client->expects($this->once())->method('packShipment')
            ->willReturnCallback(static function (PackAShipmentRequest $request): PackAShipmentResponse {
                self::assertEmpty($request->items);

                $mockResponse = self::createStub(PackAShipmentResponse::class);
                $mockResponse->response = self::createStub(PackAShipmentDataResponse::class);
                $mockResponse->response->status = -1;
                $mockResponse->response->errors = [
                    self::createStub(ErrorResponse::class),
                ];

                return $mockResponse;
            });
        $em = self::createStub(EntityManager::class);
        $packagingRepository = self::createMock(PackagingRepository::class);
        $packagingRepository->expects($this->once())->method('count')->willReturn(1);
        $packagingEntity = self::createStub(Packaging::class);
        $packagingEntity->method('getId')->willReturn(1);
        $packagingEntity->width = 1;
        $packagingEntity->height = 1;
        $packagingEntity->length = 1;
        $packagingEntity->maxWeight = 1;
        $packagingRepository->expects($this->once())->method('findByAllowedWeight')
            ->with(0)
            ->willReturn([
                $packagingEntity,
            ]);
        $packagingResultRepository = self::createMock(PackagingResultRepository::class);
        $packagingResultRepository->expects($this->once())->method('findOneByProductsCode')->willReturn(null);
        $service = new PackagingService(
            $client,
            self::createStub(Logger::class),
            $em,
            $packagingRepository,
            $packagingResultRepository
        );
        $response = $service->calculateSmallestBox([]);

        self::assertTrue($response->fits);
        self::assertNotNull($response->packaging);
        self::assertSame(PackagingService::EVALUATOR_FALLBACK, $response->evaluator);
        self::assertSame(1, $response->packaging->id);
    }

    public function testCallApiClientReturnsUnexpectedErrorUsesFallback(): void {
        $client = self::createMock(BinPackingClient::class);
        $client->expects($this->once())->method('packShipment')
            ->willThrowException(new BadResponseException('Server returned status code 500', new Request('POST', '/'), new Response(500)));
        $em = self::createStub(EntityManager::class);
        $packagingRepository = self::createMock(PackagingRepository::class);
        $packagingRepository->expects($this->once())->method('count')->willReturn(1);
        $packagingEntity = self::createStub(Packaging::class);
        $packagingEntity->method('getId')->willReturn(1);
        $packagingEntity->width = 1;
        $packagingEntity->height = 1;
        $packagingEntity->length = 1;
        $packagingEntity->maxWeight = 1;
        $packagingRepository->expects($this->once())->method('findByAllowedWeight')
            ->with(1)
            ->willReturn([
                $packagingEntity,
            ]);
        $packagingResultRepository = self::createMock(PackagingResultRepository::class);
        $packagingResultRepository->expects($this->once())->method('findOneByProductsCode')->willReturn(null);
        $service = new PackagingService(
            $client,
            self::createStub(Logger::class),
            $em,
            $packagingRepository,
            $packagingResultRepository
        );
        $product = self::createStub(ProductRequest::class);
        $product->id = 1;
        $product->width = 1;
        $product->height = 1;
        $product->length = 1;
        $product->weight = 1;

        $response = $service->calculateSmallestBox([$product]);

        self::assertTrue($response->fits);
        self::assertNotNull($response->packaging);
        self::assertSame(PackagingService::EVALUATOR_FALLBACK, $response->evaluator);
        self::assertSame(1, $response->packaging->id);
    }

    public function testCallApiClientFailsToParseJsonUsesFallback(): void {
        $client = self::createMock(BinPackingClient::class);
        $client->expects($this->once())->method('packShipment')
            ->willThrowException(new JsonException('Failed to parse JSON response'));
        $em = self::createStub(EntityManager::class);
        $packagingRepository = self::createMock(PackagingRepository::class);
        $packagingRepository->expects($this->once())->method('count')->willReturn(1);
        $packagingEntity = self::createStub(Packaging::class);
        $packagingEntity->method('getId')->willReturn(1);
        $packagingEntity->width = 1;
        $packagingEntity->height = 1;
        $packagingEntity->length = 1;
        $packagingEntity->maxWeight = 1;
        $packagingRepository->expects($this->once())->method('findByAllowedWeight')
            ->with(1)
            ->willReturn([
                $packagingEntity,
            ]);
        $packagingResultRepository = self::createMock(PackagingResultRepository::class);
        $packagingResultRepository->expects($this->once())->method('findOneByProductsCode')->willReturn(null);
        $service = new PackagingService(
            $client,
            self::createStub(Logger::class),
            $em,
            $packagingRepository,
            $packagingResultRepository
        );
        $product = self::createStub(ProductRequest::class);
        $product->id = 1;
        $product->width = 1;
        $product->height = 1;
        $product->length = 1;
        $product->weight = 1;

        $response = $service->calculateSmallestBox([$product]);

        self::assertTrue($response->fits);
        self::assertNotNull($response->packaging);
        self::assertSame(PackagingService::EVALUATOR_FALLBACK, $response->evaluator);
        self::assertSame(1, $response->packaging->id);
    }

    public function testReturnDoesNotFitWhenWeightExceedsAllPackages(): void {
        $client = self::createMock(BinPackingClient::class);
        $client->expects($this->never())->method('packShipment');
        $em = self::createMock(EntityManager::class);
        $em->expects($this->never())->method('persist');
        $em->expects($this->never())->method('flush');
        $packagingRepository = self::createMock(PackagingRepository::class);
        $packagingRepository->expects($this->once())->method('count')->willReturn(1);
        $packagingRepository->expects($this->once())->method('findByAllowedWeight')
            ->with(100.0)
            ->willReturn([]);
        $packagingResultRepository = self::createMock(PackagingResultRepository::class);
        $packagingResultRepository->expects($this->once())->method('findOneByProductsCode')->willReturn(null);
        $service = new PackagingService(
            $client,
            self::createStub(Logger::class),
            $em,
            $packagingRepository,
            $packagingResultRepository
        );

        $product = self::createStub(ProductRequest::class);
        $product->id = 1;
        $product->width = 1;
        $product->height = 1;
        $product->length = 1;
        $product->weight = 100; // heavy item exceeding any packaging's allowed weight

        $response = $service->calculateSmallestBox([$product]);

        self::assertFalse($response->fits);
        self::assertNull($response->evaluator);
        self::assertNull($response->packaging);
    }

    public function testReturnDoesNotFitWhenDbResultHasNoPackaging(): void {
        $client = self::createMock(BinPackingClient::class);
        $client->expects($this->never())->method('packShipment');

        $em = self::createStub(EntityManager::class);

        $packagingRepository = self::createMock(PackagingRepository::class);
        $packagingRepository->expects($this->once())->method('count')->willReturn(1);
        // findByAllowedWeight should not be called because DB has a cached result
        $packagingRepository->expects($this->never())->method('findByAllowedWeight');

        $packagingResultRepository = self::createMock(PackagingResultRepository::class);
        $packagingResultEntity = self::createStub(PackagingResult::class);
        $packagingResultEntity->packaging = null; // indicates does-not-fit cached result
        $packagingResultEntity->productsCode = '1:1:1';
        $packagingResultRepository->expects($this->once())
            ->method('findOneByProductsCode')
            ->with('1:1:1')
            ->willReturn($packagingResultEntity);

        $service = new PackagingService(
            $client,
            self::createStub(Logger::class),
            $em,
            $packagingRepository,
            $packagingResultRepository
        );

        $product = self::createStub(ProductRequest::class);
        $product->id = 1;
        $product->width = 1;
        $product->height = 1;
        $product->length = 1;
        $product->weight = 1;

        $response = $service->calculateSmallestBox([$product]);

        self::assertFalse($response->fits);
        self::assertNull($response->evaluator);
        self::assertNull($response->packaging);
    }

    public function testCallApiClientReturnsNoBinsUsesFallback(): void {
        $client = self::createMock(BinPackingClient::class);
        $client->expects($this->once())->method('packShipment')
            ->willReturnCallback(static function (PackAShipmentRequest $request): PackAShipmentResponse {
                $mockResponse = self::createStub(PackAShipmentResponse::class);
                $mockResponse->response = self::createStub(PackAShipmentDataResponse::class);
                $mockResponse->response->status = 1;
                $mockResponse->response->errors = [];
                $mockResponse->response->not_packed_items = [];
                $mockResponse->response->bins_packed = [];

                return $mockResponse;
            });

        $em = self::createStub(EntityManager::class);
        $packagingRepository = self::createMock(PackagingRepository::class);
        $packagingRepository->expects($this->once())->method('count')->willReturn(1);
        $packagingEntity = self::createStub(Packaging::class);
        $packagingEntity->method('getId')->willReturn(7);
        $packagingEntity->width = 10;
        $packagingEntity->height = 10;
        $packagingEntity->length = 10;
        $packagingEntity->maxWeight = 100;
        $packagingRepository->expects($this->once())->method('findByAllowedWeight')
            ->with(1)
            ->willReturn([$packagingEntity]);

        $packagingResultRepository = self::createMock(PackagingResultRepository::class);
        $packagingResultRepository->expects($this->once())->method('findOneByProductsCode')->willReturn(null);

        $service = new PackagingService(
            $client,
            self::createStub(Logger::class),
            $em,
            $packagingRepository,
            $packagingResultRepository
        );

        $product = self::createStub(ProductRequest::class);
        $product->id = 1;
        $product->width = 1;
        $product->height = 1;
        $product->length = 1;
        $product->weight = 1;

        $response = $service->calculateSmallestBox([$product]);

        self::assertTrue($response->fits);
        self::assertNotNull($response->packaging);
        self::assertSame(PackagingService::EVALUATOR_FALLBACK, $response->evaluator);
        self::assertSame(7, $response->packaging->id);
    }

    public function testCallApiClientReturnsMultipleBinsDoesNotFitAndSavesResult(): void {
        $client = self::createMock(BinPackingClient::class);
        $client->expects($this->once())->method('packShipment')
            ->willReturnCallback(static function (PackAShipmentRequest $request): PackAShipmentResponse {
                $mockResponse = self::createStub(PackAShipmentResponse::class);
                $mockResponse->response = self::createStub(PackAShipmentDataResponse::class);
                $mockResponse->response->status = 1;
                $mockResponse->response->errors = [];
                $mockResponse->response->not_packed_items = [];

                $binA = self::createStub(ShipmentPackedResponse::class);
                $binA->bin_data = self::createStub(ShipmentBinDataResponse::class);
                $binA->bin_data->id = 'a';

                $binB = self::createStub(ShipmentPackedResponse::class);
                $binB->bin_data = self::createStub(ShipmentBinDataResponse::class);
                $binB->bin_data->id = 'b';

                $mockResponse->response->bins_packed = [$binA, $binB];

                return $mockResponse;
            });

        $em = self::createMock(EntityManager::class);
        $em->expects($this->once())->method('persist');
        $em->expects($this->once())->method('flush');

        $packagingRepository = self::createMock(PackagingRepository::class);
        $packagingRepository->expects($this->once())->method('count')->willReturn(1);
        $packagingEntity = self::createStub(Packaging::class);
        $packagingEntity->method('getId')->willReturn(5);
        $packagingEntity->width = 10;
        $packagingEntity->height = 10;
        $packagingEntity->length = 10;
        $packagingEntity->maxWeight = 100;
        $packagingRepository->expects($this->once())->method('findByAllowedWeight')
            ->with(1)
            ->willReturn([$packagingEntity]);

        $packagingResultRepository = self::createMock(PackagingResultRepository::class);
        $packagingResultRepository->expects($this->once())->method('findOneByProductsCode')->willReturn(null);

        $service = new PackagingService(
            $client,
            self::createStub(Logger::class),
            $em,
            $packagingRepository,
            $packagingResultRepository
        );

        $product = self::createStub(ProductRequest::class);
        $product->id = 1;
        $product->width = 1;
        $product->height = 1;
        $product->length = 1;
        $product->weight = 1;

        $response = $service->calculateSmallestBox([$product]);

        self::assertFalse($response->fits);
        self::assertNull($response->evaluator);
        self::assertNull($response->packaging);
    }

    public function testCallApiClientSucceedsPersistsResult(): void {
        $client = self::createMock(BinPackingClient::class);
        $client->expects($this->once())->method('packShipment')
            ->willReturnCallback(static function (PackAShipmentRequest $request): PackAShipmentResponse {
                $mockResponse = self::createStub(PackAShipmentResponse::class);
                $mockResponse->response = self::createStub(PackAShipmentDataResponse::class);
                $mockResponse->response->status = 1;
                $mockResponse->response->errors = [];
                $mockResponse->response->not_packed_items = [];

                $bin = self::createStub(ShipmentPackedResponse::class);
                $bin->bin_data = self::createStub(ShipmentBinDataResponse::class);
                $bin->bin_data->id = '9';
                $bin->bin_data->w = 3;
                $bin->bin_data->h = 3;
                $bin->bin_data->d = 3;
                $bin->bin_data->weight = 3;
                $bin->bin_data->used_space = 1;
                $bin->bin_data->stack_height = 1;
                $bin->bin_data->gross_weight = 3;
                $bin->bin_data->used_weight = 3;

                $mockResponse->response->bins_packed = [$bin];

                return $mockResponse;
            });

        $em = self::createMock(EntityManager::class);
        $em->expects($this->once())->method('persist');
        $em->expects($this->once())->method('flush');

        $packagingRepository = self::createMock(PackagingRepository::class);
        $packagingRepository->expects($this->once())->method('count')->willReturn(1);
        $packagingEntity = self::createStub(Packaging::class);
        $packagingEntity->method('getId')->willReturn(9);
        $packagingEntity->width = 3;
        $packagingEntity->height = 3;
        $packagingEntity->length = 3;
        $packagingEntity->maxWeight = 10;
        $packagingRepository->expects($this->once())->method('findByAllowedWeight')
            ->with(1)
            ->willReturn([$packagingEntity]);

        $packagingResultRepository = self::createMock(PackagingResultRepository::class);
        $packagingResultRepository->expects($this->once())->method('findOneByProductsCode')->willReturn(null);

        $service = new PackagingService(
            $client,
            self::createStub(Logger::class),
            $em,
            $packagingRepository,
            $packagingResultRepository
        );

        $product = self::createStub(ProductRequest::class);
        $product->id = 1;
        $product->width = 1;
        $product->height = 1;
        $product->length = 1;
        $product->weight = 1;

        $response = $service->calculateSmallestBox([$product]);

        self::assertTrue($response->fits);
        self::assertNotNull($response->packaging);
        self::assertSame(PackagingService::EVALUATOR_API, $response->evaluator);
        self::assertSame(9, $response->packaging->id);
    }

    public function testSaveResultToDbHandlesPersistException(): void {
        $client = self::createMock(BinPackingClient::class);
        $client->expects($this->once())->method('packShipment')
            ->willReturnCallback(static function (PackAShipmentRequest $request): PackAShipmentResponse {
                $mockResponse = self::createStub(PackAShipmentResponse::class);
                $mockResponse->response = self::createStub(PackAShipmentDataResponse::class);
                $mockResponse->response->status = 1;
                $mockResponse->response->errors = [];
                $mockResponse->response->not_packed_items = [];

                $bin = self::createStub(ShipmentPackedResponse::class);
                $bin->bin_data = self::createStub(ShipmentBinDataResponse::class);
                $bin->bin_data->id = '99';
                $bin->bin_data->w = 2;
                $bin->bin_data->h = 2;
                $bin->bin_data->d = 2;
                $bin->bin_data->weight = 2;
                $bin->bin_data->used_space = 1;
                $bin->bin_data->stack_height = 1;
                $bin->bin_data->gross_weight = 2;
                $bin->bin_data->used_weight = 2;

                $mockResponse->response->bins_packed = [$bin];

                return $mockResponse;
            });

        $em = self::createMock(EntityManager::class);
        $em->expects($this->once())->method('persist')
            ->willThrowException(new OptimisticLockException('flush failed', null));
        // flush should not be called when persist throws
        $em->expects($this->never())->method('flush');

        $packagingRepository = self::createMock(PackagingRepository::class);
        $packagingRepository->expects($this->once())->method('count')->willReturn(1);
        $packagingEntity = self::createStub(Packaging::class);
        $packagingEntity->method('getId')->willReturn(3);
        $packagingEntity->width = 2;
        $packagingEntity->height = 2;
        $packagingEntity->length = 2;
        $packagingEntity->maxWeight = 10;
        $packagingRepository->expects($this->once())->method('findByAllowedWeight')
            ->with(1)
            ->willReturn([$packagingEntity]);

        $packagingResultRepository = self::createMock(PackagingResultRepository::class);
        $packagingResultRepository->expects($this->once())->method('findOneByProductsCode')->willReturn(null);

        $service = new PackagingService(
            $client,
            self::createStub(Logger::class),
            $em,
            $packagingRepository,
            $packagingResultRepository
        );

        $product = self::createStub(ProductRequest::class);
        $product->id = 1;
        $product->width = 1;
        $product->height = 1;
        $product->length = 1;
        $product->weight = 1;

        // Should not throw despite persist throwing ORMException
        $response = $service->calculateSmallestBox([$product]);

        self::assertTrue($response->fits);
        self::assertNotNull($response->packaging);
        self::assertSame(PackagingService::EVALUATOR_API, $response->evaluator);
        self::assertSame(99, $response->packaging->id);
    }

    public function testSaveResultToDbHandlesFlushException(): void {
        $client = self::createMock(BinPackingClient::class);
        $client->expects($this->once())->method('packShipment')
            ->willReturnCallback(static function (PackAShipmentRequest $request): PackAShipmentResponse {
                $mockResponse = self::createStub(PackAShipmentResponse::class);
                $mockResponse->response = self::createStub(PackAShipmentDataResponse::class);
                $mockResponse->response->status = 1;
                $mockResponse->response->errors = [];
                $mockResponse->response->not_packed_items = [];

                $bin = self::createStub(ShipmentPackedResponse::class);
                $bin->bin_data = self::createStub(ShipmentBinDataResponse::class);
                $bin->bin_data->id = '100';
                $bin->bin_data->w = 4;
                $bin->bin_data->h = 4;
                $bin->bin_data->d = 4;
                $bin->bin_data->weight = 4;
                $bin->bin_data->used_space = 1;
                $bin->bin_data->stack_height = 1;
                $bin->bin_data->gross_weight = 4;
                $bin->bin_data->used_weight = 4;

                $mockResponse->response->bins_packed = [$bin];

                return $mockResponse;
            });

        $em = self::createMock(EntityManager::class);
        $em->expects($this->once())->method('persist');
        $em->expects($this->once())->method('flush')
            ->willThrowException(new OptimisticLockException('flush failed', null));

        $packagingRepository = self::createMock(PackagingRepository::class);
        $packagingRepository->expects($this->once())->method('count')->willReturn(1);
        $packagingEntity = self::createStub(Packaging::class);
        $packagingEntity->method('getId')->willReturn(4);
        $packagingEntity->width = 4;
        $packagingEntity->height = 4;
        $packagingEntity->length = 4;
        $packagingEntity->maxWeight = 20;
        $packagingRepository->expects($this->once())->method('findByAllowedWeight')
            ->with(1)
            ->willReturn([$packagingEntity]);

        $packagingResultRepository = self::createMock(PackagingResultRepository::class);
        $packagingResultRepository->expects($this->once())->method('findOneByProductsCode')->willReturn(null);

        $service = new PackagingService(
            $client,
            self::createStub(Logger::class),
            $em,
            $packagingRepository,
            $packagingResultRepository
        );

        $product = self::createStub(ProductRequest::class);
        $product->id = 1;
        $product->width = 1;
        $product->height = 1;
        $product->length = 1;
        $product->weight = 1;

        // Should not throw despite flush throwing ORMException
        $response = $service->calculateSmallestBox([$product]);

        self::assertTrue($response->fits);
        self::assertNotNull($response->packaging);
        self::assertSame(PackagingService::EVALUATOR_API, $response->evaluator);
        self::assertSame(100, $response->packaging->id);
    }
}

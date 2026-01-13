<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\Request\ProductRequest;
use App\Dto\Response\PackagingResponse;
use App\Dto\Response\PackagingResultResponse;
use App\Entity\Packaging;
use App\Entity\PackagingResult;
use App\Repository\PackagingRepository;
use App\Repository\PackagingResultRepository;
use App\Service\BinPackingClient\BinPackingClient;
use App\Service\BinPackingClient\Dto\Request\PackAShipmentRequest;
use App\Service\BinPackingClient\Dto\Request\ShipmentBinRequest;
use App\Service\BinPackingClient\Dto\Request\ShipmentItemRequest;
use App\Service\BinPackingClient\Dto\Request\ShipmentParamsRequest;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Exception\ORMException;
use GuzzleHttp\Exception\GuzzleException;
use JsonException;
use Monolog\Logger;

use function count;

readonly class PackagingService {
    public const string EVALUATOR_API = 'api-service';
    public const string EVALUATOR_CACHED = 'cached';
    public const string EVALUATOR_FALLBACK = 'local-fallback';

    public function __construct(
        private BinPackingClient $binPackingClient,
        private Logger $logger,
        private EntityManager $entityManager,
        private PackagingRepository $packagingRepository,
        private PackagingResultRepository $packagingResultRepository,
    ) {}

    /**
     * @param non-empty-array<ProductRequest> $products
     */
    public function calculateSmallestBox(array $products): PackagingResultResponse {
        $this->logger->info('Calculating smallest box for products.', ['products' => $products]);
        $packagingCount = $this->packagingRepository->count();
        if ($packagingCount === 0) {
            $this->logger->error('No packagings found.');

            return PackagingResultResponse::createDoesNotFit();
        }
        $productsCode = $this->constructProductsCode($packagingCount, $products);
        $resultFromDb = $this->getResultFromDb($productsCode);
        if ($resultFromDb !== null) {
            $this->logger->info('Result found in database.', ['result' => $resultFromDb]);

            return $resultFromDb;
        }
        $totalWeight = array_sum(array_map(static fn (ProductRequest $dto) => $dto->weight, $products));
        $packagings = $this->packagingRepository->findByAllowedWeight($totalWeight);
        if (empty($packagings)) {
            return PackagingResultResponse::createDoesNotFit();
        }
        $res = $this->calculateBestBin($products, $packagings);
        if ($res !== null) {
            $this->logger->info('Result calculated.', ['result' => $res]);
            $packaging = $res->packaging?->id === null ? null : array_find($packagings, static fn (Packaging $entity) => $res->packaging->id === $entity->getId());
            $this->saveResultToDb($productsCode, $packaging);

            return $res;
        }

        $this->logger->info('Falling back to default calculation method.');

        return $this->calculateFallback($products, $packagings);
    }

    private function saveResultToDb(string $productsCode, ?Packaging $packaging): void {
        $result = new PackagingResult($productsCode, $packaging);

        try {
            $this->entityManager->persist($result);
            $this->entityManager->flush();
        } catch (ORMException $e) {
            $this->logger->error('Failed to save packaging result to database.', ['exception' => $e]);
        }
    }

    private function getResultFromDb(string $productsCode): ?PackagingResultResponse {
        $dbResult = $this->packagingResultRepository->findOneByProductsCode($productsCode);
        if ($dbResult === null) {
            return null;
        }
        if ($dbResult->packaging === null) {
            return PackagingResultResponse::createDoesNotFit();
        }

        return PackagingResultResponse::createFits(self::EVALUATOR_CACHED, PackagingResponse::fromPackagingEntity($dbResult->packaging));
    }

    /**
     * @param non-empty-array<ProductRequest> $products
     */
    private function constructProductsCode(int $packingCount, array $products): string {
        return '1:'.$packingCount.':'.implode('-', array_map(static fn (ProductRequest $dto) => (string) $dto->id, $products));
    }

    /**
     * @param non-empty-array<ProductRequest> $products
     * @param non-empty-array<Packaging>      $packagings
     *
     * @return null|PackagingResultResponse returns null if the service is unavailable or returned invalid response
     */
    private function calculateBestBin(array $products, array $packagings): ?PackagingResultResponse {
        $items = array_map(static fn (ProductRequest $dto) => new ShipmentItemRequest(
            id: (string) $dto->id,
            w: $dto->width,
            h: $dto->height,
            d: $dto->length,
            wg: $dto->weight,
        ), $products);
        $bins = array_map(static fn (Packaging $entity) => new ShipmentBinRequest(
            id: (string) $entity->id,
            w: $entity->width,
            h: $entity->height,
            d: $entity->length,
            max_weight: $entity->maxWeight,
        ), $packagings);

        try {
            $response = $this->binPackingClient->packShipment(new PackAShipmentRequest(
                items: $items,
                bins: $bins,
                params: new ShipmentParamsRequest(optimization_mode: 'bins_number')
            ))->response;
        } catch (JsonException $e) {
            $this->logger->error('Bin packing service received invalid request or returned invalid response.', ['exception' => $e]);

            return null;
        } catch (GuzzleException $e) {
            $this->logger->error('Bin packing service is unavailable.', ['exception' => $e]);

            return null;
        }

        if ($response->status !== 1) {
            $this->logger->error('Bin packing service returned an error.', ['status' => $response->status, 'errors' => $response->errors]);

            return null;
        }

        if (empty($response->bins_packed)) {
            $this->logger->error('Bin packing service returned no bins.', ['response' => $response]);

            return null;
        }

        if (count($response->bins_packed) > 1) {
            return PackagingResultResponse::createDoesNotFit();
        }

        $packaging = $response->bins_packed[0]->bin_data;

        return PackagingResultResponse::createFits(self::EVALUATOR_API, new PackagingResponse(
            (int) $packaging->id,
            $packaging->w,
            $packaging->h,
            $packaging->d,
            $packaging->weight
        ));
    }

    /**
     * @param non-empty-array<ProductRequest> $products
     * @param non-empty-array<Packaging>      $packagings
     */
    private function calculateFallback(array $products, array $packagings): PackagingResultResponse {
        $maxWidth = max(array_map(static fn (ProductRequest $dto) => $dto->width, $products));
        $maxHeight = max(array_map(static fn (ProductRequest $dto) => $dto->height, $products));
        $maxLength = max(array_map(static fn (ProductRequest $dto) => $dto->length, $products));
        $packagings = array_filter(
            $packagings,
            static fn (Packaging $entity) => $entity->width >= $maxWidth
                && $entity->height >= $maxHeight
                && $entity->length >= $maxLength
        );
        $totalVolume = array_sum(array_map(static fn (ProductRequest $dto) => $dto->width * $dto->height * $dto->length, $products));
        $packagings = array_filter($packagings, static fn (Packaging $entity) => $entity->getVolume() >= $totalVolume);
        if (empty($packagings)) {
            return PackagingResultResponse::createDoesNotFit();
        }

        usort($packagings, static fn (Packaging $a, Packaging $b) => $a->getVolume() <=> $b->getVolume());

        $totalWidth = array_sum(array_map(static fn (ProductRequest $dto) => $dto->width, $products));
        $totalHeight = array_sum(array_map(static fn (ProductRequest $dto) => $dto->height, $products));
        $totalLength = array_sum(array_map(static fn (ProductRequest $dto) => $dto->length, $products));
        foreach ($packagings as $packaging) {
            if ($packaging->width >= $totalWidth
                || $packaging->height >= $totalHeight
                || $packaging->length >= $totalLength) {
                return PackagingResultResponse::createFits(self::EVALUATOR_FALLBACK, PackagingResponse::fromPackagingEntity($packaging));
            }
        }

        return PackagingResultResponse::createDoesNotFit();
    }
}

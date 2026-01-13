<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\Request\ProductsRequest;
use App\Error\HttpException;
use App\Service\JsonMapper;
use App\Service\PackagingService;
use GuzzleHttp\Psr7\Response;
use JsonException;
use Monolog\Logger;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Throwable;

readonly class PackagingController {
    public function __construct(
        private PackagingService $packagingService,
        private JsonMapper $jsonMapper,
        private Logger $logger,
    ) {}

    /**
     * @throws HttpException
     * @throws JsonException
     */
    public function calculateSmallestBox(RequestInterface $request): ResponseInterface {
        try {
            $body = (string) $request->getBody();
        } catch (Throwable $exception) {
            $this->logger->warning('Invalid request body.', ['exception' => $exception]);

            throw new HttpException(400, 'Invalid request body.');
        }

        try {
            $products = $this->jsonMapper->decode($body, ProductsRequest::class);
        } catch (JsonException $exception) {
            $this->logger->warning('Invalid request body.', ['exception' => $exception]);

            throw new HttpException(400, 'Invalid request body.');
        }
        if (empty($products->products)) {
            throw new HttpException(400, 'Products list is empty.');
        }
        $result = $this->packagingService->calculateSmallestBox($products->products);

        return new Response(200, body: $this->jsonMapper->encode($result));
    }
}

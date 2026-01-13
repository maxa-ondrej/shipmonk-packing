<?php

declare(strict_types=1);

namespace App;

use App\Controller\PackagingController;
use App\Error\HttpException;
use GuzzleHttp\Psr7\Message;
use GuzzleHttp\Psr7\Response;
use Monolog\Logger;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Throwable;

readonly class Application {
    public function __construct(
        private PackagingController $packagingController,
        private Logger $logger,
    ) {}

    public function run(RequestInterface $request): ResponseInterface {
        try {
            $this->logger->info('Request received', ['request' => Message::toString($request)]);
            $response = $this->packagingController->calculateSmallestBox($request);
            $this->logger->info('Response sent', ['response' => Message::toString($response)]);

            return $response;
        } catch (HttpException $e) {
            $this->logger->info('Response sent'.$e->getMessage(), ['exception' => $e]);

            return $e->toResponse();
        } catch (Throwable $e) {
            $this->logger->critical('Internal server error', ['exception' => $e]);

            return new Response(500);
        }
    }
}

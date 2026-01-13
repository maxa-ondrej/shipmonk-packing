<?php

declare(strict_types=1);

namespace App\Service\BinPackingClient;

use App\Service\BinPackingClient\Dto\Request\PackAShipmentRequest;
use App\Service\BinPackingClient\Dto\Response\PackAShipmentResponse;
use App\Service\JsonMapper;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Psr7\Request;
use JsonException;
use Monolog\Logger;
use Throwable;

readonly class BinPackingClient {
    public function __construct(
        private BinPackingConfig $config,
        private JsonMapper $jsonMapper,
        private Client $client,
        private Logger $logger,
    ) {}

    /**
     * @throws GuzzleException
     * @throws JsonException
     */
    public function packShipment(
        PackAShipmentRequest $request
    ): PackAShipmentResponse {
        $request->username ??= $this->config->username;
        $request->api_key ??= $this->config->apiKey;
        $reqData = $this->jsonMapper->encode($request);
        $req = new Request('POST', $this->config->apiUrl.'/packer/packIntoMany', headers: [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ], body: $reqData);
        $res = $this->client->send($req);
        if ($res->getStatusCode() < 200 || $res->getStatusCode() >= 300) {
            throw new BadResponseException("Server returned status code {$res->getStatusCode()}", $req, $res);
        }

        try {
            $resData = (string) $res->getBody();
            $this->logger->debug('Response received', ['response' => $resData]);
        } catch (Throwable $e) {
            throw new BadResponseException('Failed to decode response body', $req, $res, $e);
        }

        return $this->jsonMapper->decode($resData, PackAShipmentResponse::class);
    }
}

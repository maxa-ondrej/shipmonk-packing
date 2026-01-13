<?php

declare(strict_types=1);

namespace App\Service\BinPackingClient;

use App\Service\BinPackingClient\Dto\Request\PackAShipmentRequest;
use App\Service\BinPackingClient\Dto\Response\PackAShipmentResponse;
use App\Service\JsonMapper;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\FnStream;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Monolog\Logger;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * @internal
 */
#[CoversClass(BinPackingClient::class)]
final class BinPackingClientTest extends TestCase {
    public function testPackShipment(): void {
        $jsonMapper = self::createMock(JsonMapper::class);
        $mockResponse = self::createStub(PackAShipmentResponse::class);
        $jsonMapper->expects($this->once())->method('decode')
            ->with('api-response', PackAShipmentResponse::class)
            ->willReturn($mockResponse);
        $guzzle = $this->createMock(Client::class);
        $guzzle->expects($this->once())->method('send')
            ->willReturnCallback(static function (Request $request) {
                self::assertSame('POST', $request->getMethod());
                self::assertSame('application/json', $request->getHeaderLine('Content-Type'));
                self::assertSame('application/json', $request->getHeaderLine('Accept'));
                self::assertSame('http://localhost/api/packer/packIntoMany', $request->getUri()->__toString());
                self::assertSame('api-request', (string) $request->getBody());

                return new Response(200, body: 'api-response');
            });
        $client = $this->createClient($guzzle, $jsonMapper);

        $response = $client->packShipment(self::createStub(PackAShipmentRequest::class));
        self::assertSame($mockResponse, $response);
    }

    public function testReturnUnreadableResponse(): void {
        $jsonMapper = self::createMock(JsonMapper::class);
        $jsonMapper->expects($this->never())->method('decode');
        $guzzle = $this->createMock(Client::class);
        $guzzle->expects($this->once())->method('send')
            ->willReturnCallback(static function (Request $request) {
                self::assertSame('POST', $request->getMethod());
                self::assertSame('application/json', $request->getHeaderLine('Content-Type'));
                self::assertSame('application/json', $request->getHeaderLine('Accept'));
                self::assertSame('http://localhost/api/packer/packIntoMany', $request->getUri()->__toString());
                self::assertSame('api-request', (string) $request->getBody());

                return new Response(200, body: new FnStream([
                    '__toString' => static fn () => throw new RuntimeException('Failed to read response body.'),
                ]));
            });
        $client = $this->createClient($guzzle, $jsonMapper);

        $this->expectException(GuzzleException::class);
        $client->packShipment(self::createStub(PackAShipmentRequest::class));
    }

    public function testServiceNotAvailable(): void {
        $jsonMapper = self::createMock(JsonMapper::class);
        $jsonMapper->expects($this->never())->method('decode');
        $guzzle = $this->createMock(Client::class);
        $guzzle->expects($this->once())->method('send')
            ->willReturnCallback(static function (Request $request): void {
                self::assertSame('POST', $request->getMethod());
                self::assertSame('application/json', $request->getHeaderLine('Content-Type'));
                self::assertSame('application/json', $request->getHeaderLine('Accept'));
                self::assertSame('http://localhost/api/packer/packIntoMany', $request->getUri()->__toString());
                self::assertSame('api-request', (string) $request->getBody());

                throw new RequestException('Error Communicating with Server', $request);
            });
        $client = $this->createClient($guzzle, $jsonMapper);

        $this->expectException(GuzzleException::class);
        $client->packShipment(self::createStub(PackAShipmentRequest::class));
    }

    public function testReturnServerError(): void {
        $jsonMapper = self::createMock(JsonMapper::class);
        $jsonMapper->expects($this->never())->method('decode');
        $guzzle = $this->createMock(Client::class);
        $guzzle->expects($this->once())->method('send')
            ->willReturnCallback(static function (Request $request) {
                self::assertSame('POST', $request->getMethod());
                self::assertSame('application/json', $request->getHeaderLine('Content-Type'));
                self::assertSame('application/json', $request->getHeaderLine('Accept'));
                self::assertSame('http://localhost/api/packer/packIntoMany', $request->getUri()->__toString());
                self::assertSame('api-request', (string) $request->getBody());

                return new Response(500);
            });
        $client = $this->createClient($guzzle, $jsonMapper);

        $this->expectException(GuzzleException::class);
        $client->packShipment(self::createStub(PackAShipmentRequest::class));
    }

    private function createClient(Client $guzzle, JsonMapper&MockObject $jsonMapper): BinPackingClient {
        $config = self::createStub(BinPackingConfig::class);
        $config->username = 'username';
        $config->apiKey = 'api';
        $config->apiUrl = 'http://localhost/api';

        $jsonMapper->expects($this->once())->method('encode')
            ->willReturnCallback(static function (PackAShipmentRequest $request) {
                self::assertSame('username', $request->username);
                self::assertSame('api', $request->api_key);

                return 'api-request';
            });

        return new BinPackingClient(
            $config,
            $jsonMapper,
            $guzzle,
            self::createStub(Logger::class)
        );
    }
}

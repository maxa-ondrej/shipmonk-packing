<?php

declare(strict_types=1);

namespace Integration;

use App\Service\BinPackingClient\BinPackingClient;
use App\Service\BinPackingClient\BinPackingConfig;
use App\Service\BinPackingClient\Dto\Request\PackAShipmentRequest;
use App\Service\BinPackingClient\Dto\Request\ShipmentBinRequest;
use App\Service\BinPackingClient\Dto\Request\ShipmentItemRequest;
use App\Service\JsonMapper;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\FnStream;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use JsonMapper\JsonMapperFactory;
use Monolog\Logger;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversNamespace;
use RuntimeException;
use Tests\IntegrationTest;

/**
 * @internal
 */
#[CoversClass(JsonMapper::class)]
#[CoversNamespace('App\Service\BinPackingClient')]
final class BinPackingClientTest extends IntegrationTest {
    public function testPackShipment(): void {
        $handler = new MockHandler([
            new Response(200, body: self::loadResponse('fits-box-1-items-1_2.json')),
        ]);
        $guzzle = new Client(['handler' => $handler]);
        $client = new BinPackingClient(
            new BinPackingConfig('username', 'api', 'http://localhost/api'),
            new JsonMapper(new JsonMapperFactory()->bestFit()),
            $guzzle,
            self::createStub(Logger::class)
        );

        $response = $client->packShipment(new PackAShipmentRequest(items: [
            new ShipmentItemRequest('1', 3.4, 2.1, 3, wg: 4),
            new ShipmentItemRequest('2', 4.9, 1, 2.4, wg: 9.9),
        ], bins: [
            new ShipmentBinRequest('4', 5.5, 6, 7.5, max_weight: 13.9),
        ]));

        $request = $handler->getLastRequest();
        self::assertNotNull($request);
        self::assertSame('POST', $request->getMethod());
        self::assertSame('application/json', $request->getHeaderLine('Content-Type'));
        self::assertSame('application/json', $request->getHeaderLine('Accept'));
        self::assertSame('http://localhost/api/packer/packIntoMany', $request->getUri()->__toString());
        $requestData = json_decode((string) $request->getBody(), true, 512, JSON_THROW_ON_ERROR);
        self::assertEquals([
            'username' => 'username',
            'api_key' => 'api',
            'items' => [
                ['id' => '1', 'w' => 3.4, 'h' => 2.1, 'd' => 3, 'wg' => 4, 'q' => 1, 'vr' => true],
                ['id' => '2', 'w' => 4.9, 'h' => 1, 'd' => 2.4, 'wg' => 9.9, 'q' => 1, 'vr' => true],
            ],
            'bins' => [
                ['id' => '4', 'w' => 5.5, 'h' => 6, 'd' => 7.5, 'max_weight' => 13.9, 'wg' => '', 'q' => null, 'cost' => 0],
            ],
            'params' => [
                'optimization_mode' => 'bins_number',
                'stats' => 0,
                'item_coordinates' => 0,
            ],
        ], $requestData);

        self::assertSame(1, $response->response->status);
    }

    public function testReturnKnownError(): void {
        $handler = new MockHandler([
            new Response(200, body: self::loadResponse('no-bins-to-pack.json')),
        ]);
        $guzzle = new Client(['handler' => $handler]);
        $client = new BinPackingClient(
            new BinPackingConfig('username', 'api', 'http://localhost/api'),
            new JsonMapper(new JsonMapperFactory()->bestFit()),
            $guzzle,
            self::createStub(Logger::class)
        );

        $response = $client->packShipment(new PackAShipmentRequest(items: [], bins: []));

        $request = $handler->getLastRequest();
        self::assertNotNull($request);
        self::assertSame('POST', $request->getMethod());
        self::assertSame('application/json', $request->getHeaderLine('Content-Type'));
        self::assertSame('application/json', $request->getHeaderLine('Accept'));
        self::assertSame('http://localhost/api/packer/packIntoMany', $request->getUri()->__toString());
        $requestData = json_decode((string) $request->getBody(), true, 512, JSON_THROW_ON_ERROR);
        self::assertEquals([
            'username' => 'username',
            'api_key' => 'api',
            'items' => [],
            'bins' => [],
            'params' => [
                'optimization_mode' => 'bins_number',
                'stats' => 0,
                'item_coordinates' => 0,
            ],
        ], $requestData);

        self::assertSame(-1, $response->response->status);
    }

    public function testReturnUnreadableResponse(): void {
        $handler = new MockHandler([
            new Response(200, body: new FnStream([
                '__toString' => static fn () => throw new RuntimeException('Failed to read response body.'),
            ])),
        ]);
        $guzzle = new Client(['handler' => $handler]);
        $client = new BinPackingClient(
            new BinPackingConfig('username', 'api', 'http://localhost/api'),
            new JsonMapper(new JsonMapperFactory()->bestFit()),
            $guzzle,
            self::createStub(Logger::class)
        );

        self::expectException(GuzzleException::class);
        $client->packShipment(new PackAShipmentRequest(items: [
            new ShipmentItemRequest('1', 3.4, 2.1, 3, wg: 4),
            new ShipmentItemRequest('2', 4.9, 1, 2.4, wg: 9.9),
        ], bins: [
            new ShipmentBinRequest('4', 5.5, 6, 7.5, max_weight: 13.9),
        ]));

        $request = $handler->getLastRequest();
        self::assertNotNull($request);
        self::assertSame('POST', $request->getMethod());
        self::assertSame('application/json', $request->getHeaderLine('Content-Type'));
        self::assertSame('application/json', $request->getHeaderLine('Accept'));
        self::assertSame('http://localhost/api/packer/packIntoMany', $request->getUri()->__toString());
        $requestData = json_decode((string) $request->getBody(), true, 512, JSON_THROW_ON_ERROR);
        self::assertEquals([
            'username' => 'username',
            'api_key' => 'api',
            'items' => [
                ['id' => '1', 'w' => 3.4, 'h' => 2.1, 'd' => 3, 'wg' => 4, 'q' => 1, 'vr' => true],
                ['id' => '2', 'w' => 4.9, 'h' => 1, 'd' => 2.4, 'wg' => 9.9, 'q' => 1, 'vr' => true],
            ],
            'bins' => [
                ['id' => '4', 'w' => 5.5, 'h' => 6, 'd' => 7.5, 'max_weight' => 13.9, 'wg' => '', 'q' => null, 'cost' => 0],
            ],
            'params' => [
                'optimization_mode' => 'bins_number',
                'stats' => 0,
                'item_coordinates' => 0,
            ],
        ], $requestData);
    }

    public function testServiceNotAvailable(): void {
        $handler = new MockHandler([
            new RequestException('Error Communicating with Server', new Request('POST', 'http://localhost/api/packer/packIntoMany')),
        ]);
        $guzzle = new Client(['handler' => $handler]);
        $client = new BinPackingClient(
            new BinPackingConfig('username', 'api', 'http://localhost/api'),
            new JsonMapper(new JsonMapperFactory()->bestFit()),
            $guzzle,
            self::createStub(Logger::class)
        );

        self::expectException(GuzzleException::class);
        $client->packShipment(new PackAShipmentRequest(items: [
            new ShipmentItemRequest('1', 3.4, 2.1, 3, wg: 4),
            new ShipmentItemRequest('2', 4.9, 1, 2.4, wg: 9.9),
        ], bins: [
            new ShipmentBinRequest('4', 5.5, 6, 7.5, max_weight: 13.9),
        ]));
    }

    public function testReturnServerError(): void {
        $handler = new MockHandler([
            new Response(500),
        ]);
        $guzzle = new Client(['handler' => $handler]);
        $client = new BinPackingClient(
            new BinPackingConfig('username', 'api', 'http://localhost/api'),
            new JsonMapper(new JsonMapperFactory()->bestFit()),
            $guzzle,
            self::createStub(Logger::class)
        );

        self::expectException(GuzzleException::class);
        $client->packShipment(new PackAShipmentRequest(items: [
            new ShipmentItemRequest('1', 3.4, 2.1, 3, wg: 4),
            new ShipmentItemRequest('2', 4.9, 1, 2.4, wg: 9.9),
        ], bins: [
            new ShipmentBinRequest('4', 5.5, 6, 7.5, max_weight: 13.9),
        ]));
    }
}

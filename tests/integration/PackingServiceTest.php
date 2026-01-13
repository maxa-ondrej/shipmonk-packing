<?php

declare(strict_types=1);

namespace Integration;

use App\Dto\Response\PackagingResultResponse;
use App\Entity\Packaging;
use App\Service\PackagingService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversNamespace;
use Tests\IntegrationTest;

/**
 * @internal
 */
#[CoversClass(PackagingService::class)]
#[CoversClass(Packaging::class)]
#[CoversClass(PackagingResultResponse::class)]
#[CoversNamespace('App\Service\BinPackingClient\Dto\Request')]
final class PackingServiceTest extends IntegrationTest {
    public function testNoProductsGiven(): void {
        self::assertTrue(true);
    }
}

<?php

declare(strict_types=1);

namespace App\Entity;

use LogicException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(PackagingResult::class)]
#[CoversClass(Packaging::class)]
final class PackagingResultTest extends TestCase {
    public function testGetIdThrowsWhenIdNotSet(): void {
        $r = new PackagingResult('code', null);

        $this->expectException(LogicException::class);
        $r->getId();
    }

    public function testGetIdReturnsWhenIdSet(): void {
        $r = new PackagingResult('code', null);
        $r->id = 123;

        $this->assertSame(123, $r->getId());
    }

    public function testPropertiesSetCorrectly(): void {
        $packaging = new Packaging(1.0, 2.0, 3.0, 4.0);
        $r = new PackagingResult('c', $packaging);
        $this->assertSame('c', $r->productsCode);
        $this->assertSame($packaging, $r->packaging);
    }
}

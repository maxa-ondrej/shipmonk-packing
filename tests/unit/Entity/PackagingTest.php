<?php

declare(strict_types=1);

namespace App\Entity;

use LogicException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(Packaging::class)]
final class PackagingTest extends TestCase {
    public function testGetIdThrowsWhenIdNotSet(): void {
        $p = new Packaging(1.0, 2.0, 3.0, 4.0);

        $this->expectException(LogicException::class);
        $p->getId();
    }

    public function testGetIdReturnsWhenIdSet(): void {
        $p = new Packaging(1.0, 2.0, 3.0, 4.0);
        $p->id = 123;

        $this->assertSame(123, $p->getId());
    }

    public function testGetVolumeCalculatedCorrectly(): void {
        $p = new Packaging(1.0, 2.0, 3.0, 4.0);

        $this->assertSame(6.0, $p->getVolume());
        $this->assertSame(6.0, $p->getVolume());
    }

    public function testPropertiesSetCorrectly(): void {
        $p = new Packaging(1.1, 2.2, 3.3, 4.4);
        $this->assertSame(1.1, $p->width);
        $this->assertSame(2.2, $p->height);
        $this->assertSame(3.3, $p->length);
        $this->assertSame(4.4, $p->maxWeight);
    }
}

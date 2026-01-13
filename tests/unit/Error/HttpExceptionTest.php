<?php

declare(strict_types=1);

namespace App\Error;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(HttpException::class)]
final class HttpExceptionTest extends TestCase {
    public function testToResponse(): void {
        $e = new HttpException(418, 'I am a teapot');
        $res = $e->toResponse();

        $this->assertSame(418, $res->getStatusCode());
        $this->assertSame('I am a teapot', (string) $res->getBody());
    }
}

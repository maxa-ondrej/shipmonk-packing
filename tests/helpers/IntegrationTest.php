<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use RuntimeException;

abstract class IntegrationTest extends TestCase {
    protected static function loadFixture(string $filename): string {
        $response = file_get_contents(__DIR__.'/../fixtures/'.$filename);
        if ($response === false) {
            throw new RuntimeException('Failed to load fixture file.');
        }

        return $response;
    }

    protected static function loadResponse(string $filename): string {
        return self::loadFixture('response/'.$filename);
    }
}

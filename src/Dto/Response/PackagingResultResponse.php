<?php

declare(strict_types=1);

namespace App\Dto\Response;

readonly class PackagingResultResponse {
    private function __construct(
        public bool $fits,
        public ?string $evaluator = null,
        public ?PackagingResponse $packaging = null,
    ) {}

    public static function createFits(string $evaluator, PackagingResponse $packaging): self {
        return new self(true, $evaluator, $packaging);
    }

    public static function createDoesNotFit(): self {
        return new self(false);
    }
}

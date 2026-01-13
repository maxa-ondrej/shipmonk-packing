<?php

declare(strict_types=1);

namespace App\Dto\Response;

use App\Entity\Packaging;

readonly class PackagingResponse {
    public function __construct(
        public int $id,
        public float $width,
        public float $height,
        public float $length,
        public float $maxWeight,
    ) {}

    public static function fromPackagingEntity(Packaging $packaging): self {
        return new self(
            id: $packaging->getId(),
            width: $packaging->width,
            height: $packaging->height,
            length: $packaging->length,
            maxWeight: $packaging->maxWeight,
        );
    }
}

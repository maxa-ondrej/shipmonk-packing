<?php

declare(strict_types=1);

namespace App\Service\BinPackingClient\Dto\Request;

class ShipmentBinRequest {
    /**
     * @param ''|float $wg
     * @param ''|float $max_weight
     */
    public function __construct(
        public string $id,
        public float $w,
        public float $h,
        public float $d,
        public float|string $wg = '',
        public float|string $max_weight = '',
        public ?int $q = null,
        public float $cost = 0.0,
    ) {}
}

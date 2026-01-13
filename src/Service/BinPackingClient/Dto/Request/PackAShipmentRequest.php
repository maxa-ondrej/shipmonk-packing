<?php

declare(strict_types=1);

namespace App\Service\BinPackingClient\Dto\Request;

class PackAShipmentRequest {
    /**
     * @param non-empty-array<ShipmentItemRequest> $items
     * @param non-empty-array<ShipmentBinRequest>  $bins
     */
    public function __construct(
        public array $items,
        public array $bins,
        public ShipmentParamsRequest $params = new ShipmentParamsRequest(),
        public ?string $username = null,
        public ?string $api_key = null,
    ) {}
}

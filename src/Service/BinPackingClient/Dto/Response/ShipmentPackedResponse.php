<?php

declare(strict_types=1);

namespace App\Service\BinPackingClient\Dto\Response;

class ShipmentPackedResponse {
    public string $image_complete;
    public ShipmentBinDataResponse $bin_data;

    /**
     * @var array<ShipmentItemsResponse>
     */
    public array $items;
}

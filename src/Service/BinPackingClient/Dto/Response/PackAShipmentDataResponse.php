<?php

declare(strict_types=1);

namespace App\Service\BinPackingClient\Dto\Response;

class PackAShipmentDataResponse {
    public int $status;

    /**
     * @var array<ErrorResponse>
     */
    public array $errors = [];

    /**
     * @var array<ShipmentNotPackedResponse>
     */
    public array $not_packed_items = [];

    /**
     * @var array<ShipmentPackedResponse>
     */
    public array $bins_packed = [];
}

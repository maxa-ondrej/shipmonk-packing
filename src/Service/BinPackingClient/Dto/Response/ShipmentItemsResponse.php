<?php

declare(strict_types=1);

namespace App\Service\BinPackingClient\Dto\Response;

class ShipmentItemsResponse {
    public string $id;
    public float $w;
    public float $h;
    public float $d;
    public float $wg;
    public string $image_sbs;
    public ShipmentCoordinatesResponse $coordinates;
}

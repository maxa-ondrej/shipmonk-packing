<?php

declare(strict_types=1);

namespace App\Service\BinPackingClient\Dto\Response;

class ShipmentNotPackedResponse {
    public string $id;
    public float $q;
    public float $w;
    public float $h;
    public float $d;
    public float $wg;
}

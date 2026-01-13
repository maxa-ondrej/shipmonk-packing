<?php

declare(strict_types=1);

namespace App\Service\BinPackingClient\Dto\Response;

class ShipmentBinDataResponse {
    public string $id;
    public float $w;
    public float $h;
    public float $d;
    public float $used_space;
    public float $weight;
    public float $gross_weight;
    public float $used_weight;
    public float $stack_height;
}

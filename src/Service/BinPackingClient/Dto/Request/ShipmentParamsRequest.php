<?php

declare(strict_types=1);

namespace App\Service\BinPackingClient\Dto\Request;

class ShipmentParamsRequest {
    /**
     * @param int<0,1>                                $stats             0 [default] - means that the statistics of a packing process will not be returned, 1 - means that the statistics of a packing process will be returned
     * @param int<0,1>                                $item_coordinates  0 [default] - means that coordinates of the placement of each item will not be returned, 1 - that coordinates of the placement of each item will be returned
     * @param 'bins_number'|'bins_utilization'|'cost' $optimization_mode
     */
    public function __construct(
        public string $optimization_mode = 'bins_number',
        public int $stats = 0,
        public int $item_coordinates = 0,
    ) {}
}

<?php

declare(strict_types=1);

namespace App\Service\BinPackingClient\Dto\Request;

class ShipmentItemRequest {
    /**
     * @param string   $id Item ID/SKU. The number or code you use to identify what is being packed.
     * @param float    $w  The width of the item
     * @param float    $h  The height of the item
     * @param float    $d  The depth or length of the item
     * @param ''|float $wg The weight of the item
     * @param bool     $vr Vertical rotation. The information if the item can be rotated vertically.
     * @param int      $q  The number of the same items to pack
     */
    public function __construct(
        public string $id,
        public float $w,
        public float $h,
        public float $d,
        public int $q = 1,
        public float|string $wg = '',
        public bool $vr = true,
    ) {}
}

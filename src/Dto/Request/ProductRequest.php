<?php

declare(strict_types=1);

namespace App\Dto\Request;

class ProductRequest {
    public int $id;
    public float $width;
    public float $height;
    public float $length;
    public float $weight;
}

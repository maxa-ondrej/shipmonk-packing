<?php

declare(strict_types=1);

namespace App\Service\BinPackingClient;

class BinPackingConfig {
    public function __construct(
        public string $username,
        public string $apiKey,
        public string $apiUrl,
    ) {}
}

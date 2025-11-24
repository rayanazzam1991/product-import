<?php

namespace App\Http\Integrations\YallaBuy\InventoryManagementRequests\DTO;

class YallaBuyInventoryManagementRequestDTO
{
    public function __construct(
        public string $apiKey
    ) {}
}

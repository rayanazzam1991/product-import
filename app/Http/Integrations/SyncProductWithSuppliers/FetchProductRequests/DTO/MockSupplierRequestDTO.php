<?php

namespace App\Http\Integrations\SyncProductWithSuppliers\FetchProductRequests\DTO;

class MockSupplierRequestDTO
{
    public function __construct(
        public string $apiKey
    ) {}
}

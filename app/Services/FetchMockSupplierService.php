<?php

namespace App\Services;

use App\Contracts\FetchProductsServiceInterface;
use App\Http\Integrations\SyncProductWithSuppliers\FetchProductConnector\MockSupplierConnector;
use App\Http\Integrations\SyncProductWithSuppliers\FetchProductRequests\DTO\MockSupplierRequestDTO;
use App\Http\Integrations\SyncProductWithSuppliers\FetchProductRequests\FetchMockSupplierProductsRequest;
use Illuminate\Support\Facades\Config;
use Saloon\Exceptions\Request\FatalRequestException;
use Saloon\Exceptions\Request\RequestException;

readonly class FetchMockSupplierService implements FetchProductsServiceInterface
{
    public function __construct(
        private MockSupplierConnector $connector
    ) {}

    /**
     * @throws FatalRequestException
     * @throws RequestException
     * @throws \JsonException
     */
    public function fetch()
    {
        $requestDTO = new MockSupplierRequestDTO(Config::get('product_suppliers.mock_supplier'));
        $request = new FetchMockSupplierProductsRequest($requestDTO);
        $response = $this->connector->send($request);

        return $response->json();
    }
}

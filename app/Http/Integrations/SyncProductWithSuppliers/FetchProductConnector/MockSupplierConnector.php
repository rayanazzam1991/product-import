<?php

namespace App\Http\Integrations\SyncProductWithSuppliers\FetchProductConnector;

use Saloon\Exceptions\SaloonException;
use Saloon\Http\Connector;
use Saloon\Http\Response;
use Saloon\Traits\Plugins\AcceptsJson;

class MockSupplierConnector extends Connector
{
    use AcceptsJson;

    /**
     * The Base URL of the API
     */
    public function resolveBaseUrl(): string
    {
        return 'https://5fc7a13cf3c77600165d89a8.mockapi.io/api/v5/products';
    }

    /**
     * Default headers for every request
     */
    protected function defaultHeaders(): array
    {
        return [
            'Accept' => 'application/json',
        ];
    }

    /**
     * Default HTTP client options
     */
    protected function defaultConfig(): array
    {
        return [
            'timeout' => 3600,
        ];
    }

    /**
     * @throws SaloonException
     */
    public function hasRequestFailed(Response $response): ?bool
    {
        $res = json_decode($response->body());
        if ($response->status() == 200) {
            return false;
        }
        throw new SaloonException($res->message, $response->status());
    }
}

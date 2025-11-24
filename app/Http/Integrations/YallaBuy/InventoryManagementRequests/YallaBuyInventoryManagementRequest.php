<?php

namespace App\Http\Integrations\YallaBuy\InventoryManagementRequests;

use App\Http\Integrations\YallaBuy\InventoryManagementRequests\DTO\YallaBuyInventoryManagementRequestDTO;
use Saloon\Enums\Method;
use Saloon\Http\Request;

class YallaBuyInventoryManagementRequest extends Request
{
    public function __construct(private readonly YallaBuyInventoryManagementRequestDTO $DTO) {}

    /**
     * The HTTP method of the request
     */
    protected Method $method = Method::GET;

    /**
     * The endpoint for the request
     */
    public function resolveEndpoint(): string
    {
        return '';
    }

    protected function defaultQuery(): array
    {
        return [
            'api-key' => $this->DTO->apiKey, // I put this to let you know the place to add the apiKEy
        ];
    }

    public function defaultConfig(): array
    {
        return [
            'timeout' => 30,
        ];
    }
}

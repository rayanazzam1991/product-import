<?php

namespace App\DTOs;

class NormalizedProductDTO
{
    public int $id;

    public string $name;

    public float $price;

    public string $sku;

    public string $status;

    public array $variations;

    public array $warehouses = [];

    public function __construct(array $data)
    {
        $this->id = $data['id'];
        $this->name = $data['name'];
        $this->price = $data['price'];
        $this->sku = $data['sku'];
        $this->status = $data['status'];
        $this->variations = $data['variations'];
        $this->warehouses = $data['warehouses'] ?? [];
    }

    public function toImportArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'sku' => $this->sku,
            'price' => $this->price,
            'currency' => 'USD',
            'status' => $this->status,
            'variations' => json_encode($this->variations),
            'warehouses' => json_encode($this->warehouses),
        ];
    }

    public function toDatabaseRow(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'sku' => $this->sku,
            'price' => $this->price,
            'currency' => 'USD',
            'status' => $this->status,
            'variations' => json_encode($this->variations),
        ];
    }
}

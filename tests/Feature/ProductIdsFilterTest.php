<?php

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function catalogueProduct(string $name): Product
{
    return Product::create(['name' => $name, 'sku' => $name, 'regular_price' => 10, 'is_active' => true, 'in_stock' => true]);
}

it('filters the catalogue to a comma-separated list of product ids', function () {
    $first = catalogueProduct('Alpha');
    catalogueProduct('Bravo');
    $third = catalogueProduct('Charlie');

    $this->getJson("/api/products?ids={$third->id},{$first->id},abc")
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.id', $first->id)
        ->assertJsonPath('data.1.id', $third->id);
});

it('never falls back to the whole catalogue for an empty or malformed id list', function (string $query) {
    catalogueProduct('Alpha');

    $this->getJson("/api/products?{$query}")
        ->assertOk()
        ->assertJsonCount(0, 'data');
})->with([
    'empty list' => 'ids=',
    'no numeric ids' => 'ids=abc',
    'array syntax' => 'ids[]=1',
]);

<?php

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\assertDatabaseCount;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\assertSoftDeleted;
use function Pest\Laravel\deleteJson;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;
use function Pest\Laravel\putJson;

uses(RefreshDatabase::class)->group('natural-idempotency');

it('creates product with POST (not naturally idempotent)', function () {
    $productData = [
        'name' => 'Notebook Dell',
        'description' => 'Notebook Dell Inspiron 15',
        'price' => 3500.00,
        'stock' => 10,
        'active' => true,
    ];

    $firstResponse = postJson('/api/natural-idempotency/products', $productData);
    
    expect($firstResponse->status())->toBe(201)
        ->and($firstResponse->json('product.name'))->toBe('Notebook Dell');

    $firstProductUuid = $firstResponse->json('product.uuid');

        $secondResponse = postJson('/api/natural-idempotency/products', $productData);
    
    expect($secondResponse->status())->toBe(201);
    $secondProductUuid = $secondResponse->json('product.uuid');

    // POST is not idempotent: creates two different products with different UUIDs
    expect($firstProductUuid)->not->toBe($secondProductUuid);
    assertDatabaseCount('products', 2);
});

it('updates product with PUT (naturally idempotent)', function () {
    $product = Product::create([
        'name' => 'Mouse Logitech',
        'description' => 'Mouse wireless',
        'price' => 150.00,
        'stock' => 50,
        'active' => true,
    ]);

    $updateData = [
        'name' => 'Mouse Logitech MX Master',
        'description' => 'Mouse wireless premium',
        'price' => 450.00,
        'stock' => 30,
        'active' => true,
    ];

    $firstResponse = putJson("/api/natural-idempotency/products/{$product->uuid}", $updateData);
    
    expect($firstResponse->status())->toBe(200)
        ->and($firstResponse->json('product.name'))->toBe('Mouse Logitech MX Master')
        ->and($firstResponse->json('product.price'))->toBe('450.00');

    $secondResponse = putJson("/api/natural-idempotency/products/{$product->uuid}", $updateData);
    
    expect($secondResponse->status())->toBe(200)
        ->and($secondResponse->json('product.name'))->toBe('Mouse Logitech MX Master')
        ->and($secondResponse->json('product.price'))->toBe('450.00');

    assertDatabaseCount('products', 1);
    assertDatabaseHas('products', [
        'uuid' => $product->uuid,
        'name' => 'Mouse Logitech MX Master',
        'price' => 450.00,
    ]);
});

it('executes PUT multiple times with same result (idempotent)', function () {
    $product = Product::create([
        'name' => 'Teclado Mecânico',
        'price' => 500.00,
        'stock' => 20,
    ]);

    $updateData = [
        'name' => 'Teclado Mecânico RGB',
        'description' => 'Teclado com iluminação RGB',
        'price' => 650.00,
        'stock' => 15,
        'active' => true,
    ];

    // Executes PUT 5 times with the same data using UUID
    for ($i = 0; $i < 5; $i++) {
        $response = putJson("/api/natural-idempotency/products/{$product->uuid}", $updateData);
        expect($response->status())->toBe(200);
    }

    $product->refresh();

    // Only one product exists with the final updated state with same values
    expect($product->name)->toBe('Teclado Mecânico RGB')
        ->and($product->price)->toBe('650.00')
        ->and($product->stock)->toBe(15);

    assertDatabaseCount('products', 1);
});

it('returns 404 when trying to PUT non-existent product', function () {
    $nonExistentId = \Illuminate\Support\Str::uuid()->toString();

    $productData = [
        'name' => 'Monitor LG 27"',
        'description' => 'Monitor UltraWide',
        'price' => 1800.00,
        'stock' => 5,
        'active' => true,
    ];

    $response = putJson("/api/natural-idempotency/products/{$nonExistentId}", $productData);
    
    expect($response->status())->toBe(404)
        ->and($response->json('error'))->toBe('product_not_found');
});

it('deletes product with DELETE (naturally idempotent)', function () {
    $product = Product::create([
        'name' => 'Webcam',
        'price' => 300.00,
        'stock' => 8,
    ]);

    $firstResponse = deleteJson("/api/natural-idempotency/products/{$product->uuid}");
    
    expect($firstResponse->status())->toBe(200);

    assertSoftDeleted('products', ['uuid' => $product->uuid]);

    $secondResponse = deleteJson("/api/natural-idempotency/products/{$product->uuid}");
    
    // Return 404 not found (consistent state)
    expect($secondResponse->status())->toBe(404);
});

it('executes DELETE multiple times on non-existent product (idempotent)', function () {
    $nonExistentUuid = \Illuminate\Support\Str::uuid()->toString();

    // Executa DELETE 3 vezes em produto que não existe
    for ($i = 0; $i < 3; $i++) {
        $response = deleteJson("/api/natural-idempotency/products/{$nonExistentUuid}");

        // Always returns 404 (consistent state)
        expect($response->status())->toBe(404);
    }

    // Final state: product does not exist
    assertDatabaseCount('products', 0);
});

it('GET product returns current state (safe and idempotent)', function () {
    $product = Product::create([
        'name' => 'SSD 1TB',
        'price' => 600.00,
        'stock' => 25,
    ]);

    for ($i = 0; $i < 5; $i++) {
        $response = getJson("/api/natural-idempotency/products/{$product->uuid}");
        
        expect($response->status())->toBe(200)
            ->and($response->json('product.name'))->toBe('SSD 1TB')
            ->and($response->json('product.price'))->toBe('600.00');
    }

    // State remains unchanged
    $product->refresh();
    expect($product->name)->toBe('SSD 1TB')
        ->and($product->price)->toBe('600.00');
});

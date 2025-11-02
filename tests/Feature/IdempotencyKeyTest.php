<?php

use App\Models\IdempotencyKey;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

use function Pest\Laravel\assertDatabaseCount;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\postJson;

uses(RefreshDatabase::class)->group('idempotency');

it('creates order with idempotency key from header', function () {
  $idempotencyKey = Str::uuid()->toString();

  $response = postJson('/api/idempotency-key/order', [
    'description' => 'Pedido de 3 notebooks',
    'amount' => 4500.00,
  ], [
    'Idempotency-Key' => $idempotencyKey,
  ]);

  expect($response->status())->toBe(201)
    ->and($response->json())->toHaveKeys([
      'message',
      'order_id',
      'description',
      'amount',
      'is_from_cache',
      'timestamp',
    ])
    ->and($response->json('is_from_cache'))->toBeFalse()
    ->and($response->json('amount'))->toBe(4500);

  expect($response->headers->get('X-Idempotency-Status'))->toBe('MISS')
    ->and($response->headers->get('Idempotency-Key'))->toBe($idempotencyKey);

  assertDatabaseHas('idempotency_keys', [
    'key' => $idempotencyKey,
  ]);
});


it('returns cached result for duplicate requests', function () {
  $idempotencyKey = Str::uuid()->toString();

  // First response
  $firstResponse = postJson('/api/idempotency-key/order', [
    'description' => 'Pedido de teste',
    'amount' => 100.00,
  ], [
    'Idempotency-Key' => $idempotencyKey,
  ]);

  expect($firstResponse->status())->toBe(201);
  $firstOrderId = $firstResponse->json('order_id');

  // Second Request with same Idempotency Key
  $secondResponse = postJson('/api/idempotency-key/order', [
    'description' => 'Pedido de teste',
    'amount' => 100.00,
  ], [
    'Idempotency-Key' => $idempotencyKey,
  ]);

  expect($secondResponse->status())->toBe(201)
    ->and($secondResponse->json('order_id'))->toBe($firstOrderId)
    ->and($secondResponse->json('is_from_cache'))->toBeTrue();

  expect($secondResponse->headers->get('X-Idempotency-Status'))->toBe('HIT');

  // Verify that only one record exists in the database
  assertDatabaseCount('idempotency_keys', 1);
});


it('creates different orders with different keys', function () {
  $firstKey = Str::uuid()->toString();
  $secondKey = Str::uuid()->toString();

  $firstResponse = postJson('/api/idempotency-key/order', [
    'description' => 'Primeiro pedido',
    'amount' => 100.00,
  ], [
    'Idempotency-Key' => $firstKey,
  ]);

  $secondResponse = postJson('/api/idempotency-key/order', [
    'description' => 'Segundo pedido',
    'amount' => 200.00,
  ], [
    'Idempotency-Key' => $secondKey,
  ]);

  $firstOrderId = $firstResponse->json('order_id');
  $secondOrderId = $secondResponse->json('order_id');

  // Order IDs must be different
  expect($firstOrderId)->not->toBe($secondOrderId);

  // Both must be MISS (first time)
  expect($firstResponse->headers->get('X-Idempotency-Status'))->toBe('MISS')
    ->and($secondResponse->headers->get('X-Idempotency-Status'))->toBe('MISS');

  // Must have two records in the database 
  assertDatabaseCount('idempotency_keys', 2);
});

it('prevents double charge with payment idempotency', function () {
  $idempotencyKey = Str::uuid()->toString();

  // Simulate first payment attempt
  $firstPayment = postJson('/api/idempotency-key/payment', [
    'description' => 'Pagamento R$ 100,00',
    'amount' => 100.00,
  ], [
    'Idempotency-Key' => $idempotencyKey,
  ]);

  expect($firstPayment->status())->toBe(201);
  $firstPaymentId = $firstPayment->json('payment_id');

  // Simulate retry (network failed, client tries again)
  $retryPayment = postJson('/api/idempotency-key/payment', [
    'description' => 'Pagamento R$ 100,00',
    'amount' => 100.00,
  ], [
    'Idempotency-Key' => $idempotencyKey,
  ]);

  expect($retryPayment->status())->toBe(201)
    ->and($retryPayment->json('payment_id'))->toBe($firstPaymentId)
    ->and($retryPayment->json('is_from_cache'))->toBeTrue();

  expect($retryPayment->headers->get('X-Idempotency-Status'))->toBe('HIT');

  // Must guarantee that it was only processed once
  assertDatabaseCount('idempotency_keys', 1);
});


it('fails when idempotency key header is missing', function () {
  $response = postJson('/api/idempotency-key/order', [
    'description' => 'Pedido sem chave',
    'amount' => 100.00,
  ]);

  expect($response->status())->toBe(400)
    ->and($response->json('error'))->toBe('missing_idempotency_key')
    ->and($response->json('message'))->toContain('Idempotency-Key');
});

it('does not return expired keys', function () {
  $idempotencyKey = Str::uuid()->toString();

  $firstResponse = postJson('/api/idempotency-key/order', [
    'description' => 'Pedido que vai expirar',
    'amount' => 50.00,
  ], [
    'Idempotency-Key' => $idempotencyKey,
  ]);

  expect($firstResponse->status())->toBe(201);
  $firstOrderId = $firstResponse->json('order_id');

  // Forces expiration manually in the database
  IdempotencyKey::where('key', $idempotencyKey)
    ->update(['expires_at' => now()->subSecond()]);

  // Try to use the same key again after expiration
  $secondResponse = postJson('/api/idempotency-key/order', [
    'description' => 'Novo pedido após expiração',
    'amount' => 100.00,
  ], [
    'Idempotency-Key' => $idempotencyKey,
  ]);

  // Should create a new result (not reuse the expired one)
  expect($secondResponse->status())->toBe(201)
    ->and($secondResponse->headers->get('X-Idempotency-Status'))->toBe('MISS');

  $newOrderId = $secondResponse->json('order_id');
  expect($newOrderId)->not->toBe($firstOrderId);
});

it('removes expired keys via cleanup command', function () {

  // Expired key 1
  IdempotencyKey::create([
    'key' => 'expired-1',
    'result' => ['order_id' => 'test-1', 'description' => 'test', 'amount' => 100],
    'status_code' => 200,
    'expires_at' => now()->subDay(),
  ]);

  // Expired key 2
  IdempotencyKey::create([
    'key' => 'expired-2',
    'result' => ['order_id' => 'test-2', 'description' => 'test', 'amount' => 200],
    'status_code' => 200,
    'expires_at' => now()->subHour(),
  ]);

  // Valid key
  IdempotencyKey::create([
    'key' => 'valid-key',
    'result' => ['order_id' => 'test-3', 'description' => 'test', 'amount' => 300],
    'status_code' => 200,
    'expires_at' => now()->addDay(),
  ]);

  assertDatabaseCount('idempotency_keys', 3);

  // Execute cleanup command
  $this->artisan('idempotency:cleanup')
    ->expectsConfirmation('Deseja remover 2 chave(s)?', 'yes')
    ->assertSuccessful();

  // Should have only the valid key remaining
  assertDatabaseCount('idempotency_keys', 1);
  assertDatabaseHas('idempotency_keys', ['key' => 'valid-key']);
});

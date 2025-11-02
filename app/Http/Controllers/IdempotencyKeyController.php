<?php

namespace App\Http\Controllers;

use App\Http\Requests\IdempotencyRequest;
use App\Services\IdempotencyKeyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class IdempotencyKeyController extends Controller
{
  public function __construct(
    private IdempotencyKeyService $idempotencyService
  ) {}

  public function createOrder(IdempotencyRequest $request): JsonResponse
  {
    $idempotencyKey = $request->getIdempotencyKey();

    // Validate presence of Idempotency-Key header
    if (!$idempotencyKey) {
      return response()->json([
        'message' => 'Header Idempotency-Key is required',
        'error' => 'missing_idempotency_key'
      ], 400);
    }

    // Verifica se a chave já existe
    $check = $this->idempotencyService->checkKey($idempotencyKey);

    if ($check['exists']) {
      $cachedResult = $check['result'];

      $response = array_merge($cachedResult->result, [
        'is_from_cache' => true,
      ]);

      return response()
        ->json($response, $cachedResult->status_code)
        ->header('X-Idempotency-Status', 'HIT')
        ->header('Idempotency-Key', $idempotencyKey);
    }

    // Simulate order processing
    sleep(0);

    $result = [
      'message' => 'Order created successfully',
      'order_id' => 'ORD-' . strtoupper(Str::random(8)),
      'description' => $request->input('description'),
      'amount' => $request->input('amount'),
      'is_from_cache' => false,
      'timestamp' => now()->toIso8601String(),
    ];

    $this->idempotencyService->storeResult($idempotencyKey, $result, 201);

    return response()
      ->json($result, 201)
      ->header('X-Idempotency-Status', 'MISS')
      ->header('Idempotency-Key', $idempotencyKey);
  }

  public function processPayment(IdempotencyRequest $request): JsonResponse
  {
    $idempotencyKey = $request->getIdempotencyKey();

    if (!$idempotencyKey) {
      return response()->json([
        'message' => 'Header Idempotency-Key é obrigatório',
        'error' => 'missing_idempotency_key'
      ], 400);
    }

    $check = $this->idempotencyService->checkKey($idempotencyKey);

    if ($check['exists']) {
      $cachedResult = $check['result'];

      $response = array_merge($cachedResult->result, [
        'is_from_cache' => true,
      ]);

      return response()
        ->json($response, $cachedResult->status_code)
        ->header('X-Idempotency-Status', 'HIT')
        ->header('Idempotency-Key', $idempotencyKey);
    }

    // In production, this should integrate with a payment gateway
    sleep(0);

    $result = [
      'message' => 'Pagamento processado com sucesso',
      'payment_id' => 'PAY-' . strtoupper(Str::random(8)),
      'transaction_id' => 'TXN-' . strtoupper(Str::random(12)),
      'description' => $request->input('description'),
      'amount' => $request->input('amount'),
      'status' => 'approved',
      'is_from_cache' => false,
      'timestamp' => now()->toIso8601String(),
    ];

    $this->idempotencyService->storeResult($idempotencyKey, $result, 201);

    return response()
      ->json($result, 201)
      ->header('X-Idempotency-Status', 'MISS')
      ->header('Idempotency-Key', $idempotencyKey);
  }

  public function cleanup(): JsonResponse
  {
    $count = $this->idempotencyService->cleanupExpiredKeys();

    return response()->json([
      'message' => 'Limpeza realizada com sucesso',
      'removed_keys' => $count,
    ]);
  }
}

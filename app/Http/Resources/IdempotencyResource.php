<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class IdempotencyResource extends JsonResource
{
  /**
   * Transform the resource into an array.
   *
   * @return array<string, mixed>
   */
  public function toArray(Request $request): array
  {
    return [
      'message' => $this->resource['message'] ?? '',
      'process_id' => $this->resource['process_id'] ?? '',
      'is_from_cache' => $this->resource['is_from_cache'] ?? false,
      'timestamp' => $this->resource['timestamp'] ?? now()->toIso8601String(),
    ];
  }

  public function withResponse($request, $response)
  {
    $response->header(
      'X-Idempotency-Status',
      $this->resource['is_from_cache'] ? 'HIT' : 'MISS'
    );

    if (isset($this->resource['idempotency_key'])) {
      $response->header('Idempotency-Key', $this->resource['idempotency_key']);
    }
  }
}

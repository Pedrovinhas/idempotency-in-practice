<?php

namespace App\Services;

use App\Models\IdempotencyKey;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Serviço para gerenciar Padrão 1: Chave de Idempotência
 * 
 * Este serviço implementa o padrão de idempotência baseado em chaves,
 * onde o cliente gera uma chave única e o servidor a armazena
 * para evitar processamento duplicado.
 */
class IdempotencyKeyService
{
    private int $defaultTTLInSeconds = 86400;

    /**
     * Verify if an idempotency key exists and is not expired
     * 
     * @param string $key The idempotency key
     * @return array{exists: bool, result: IdempotencyKey|null}
     */
    public function checkKey(string $key): array
    {
        Log::info("Verificando chave de idempotência", ['key' => $key]);

        $idempotencyKey = IdempotencyKey::where('key', $key)
            ->notExpired()
            ->first();

        if ($idempotencyKey) {
            Log::info("Chave de idempotência encontrada", [
                'key' => $key,
                'created_at' => $idempotencyKey->created_at
            ]);

            return [
                'exists' => true,
                'result' => $idempotencyKey
            ];
        }

        Log::info("Chave de idempotência não encontrada", ['key' => $key]);

        return [
            'exists' => false,
            'result' => null
        ];
    }

    public function storeResult(
        string $key,
        array $result,
        int $statusCode = 201,
        ?int $ttlInSeconds = null
    ): IdempotencyKey {
        $ttl = $ttlInSeconds ?? $this->defaultTTLInSeconds;
        $expiresAt = Carbon::now()->addSeconds($ttl);

        // Remove chaves expiradas com a mesma key antes de criar uma nova
        IdempotencyKey::where('key', $key)
            ->where('expires_at', '<', Carbon::now())
            ->delete();

        $idempotencyKey = IdempotencyKey::create([
            'key' => $key,
            'result' => $result,
            'status_code' => $statusCode,
            'expires_at' => $expiresAt,
        ]);

        Log::info("Resultado armazenado para chave de idempotência", [
            'key' => $key,
            'status_code' => $statusCode,
            'expires_at' => $expiresAt->toIso8601String()
        ]);

        return $idempotencyKey;
    }

    /**
     * Remove expired idempotency keys from the database
     * Can be called by a scheduled job
     * 
     * @return int Number of keys removed
     */
    public function cleanupExpiredKeys(): int
    {
        $count = IdempotencyKey::expired()->delete();

        Log::info("Limpeza de chaves expiradas", ['removed_count' => $count]);

        return $count;
    }

    public function setDefaultTTL(int $seconds): void
    {
        $this->defaultTTLInSeconds = $seconds;
    }

    public function getDefaultTTL(): int
    {
        return $this->defaultTTLInSeconds;
    }
}

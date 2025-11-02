<?php

use App\Http\Controllers\IdempotencyKeyController;
use App\Http\Controllers\NaturalIdempotencyController;
use App\Http\Controllers\TokenBasedController;
use App\Http\Controllers\VersionBasedController;
use Illuminate\Support\Facades\Route;

// Padrão 1: Idempotency Key-Based
Route::prefix('idempotency-key')->group(function () {
    Route::post('/order', [IdempotencyKeyController::class, 'createOrder']);
    Route::post('/payment', [IdempotencyKeyController::class, 'processPayment']);    
    Route::delete('/cleanup', [IdempotencyKeyController::class, 'cleanup']);
});

// Padrão 2: Natural Idempotency (PUT e DELETE são naturalmente idempotentes)
Route::prefix('natural-idempotency')->group(function () {
    Route::post('/products', [NaturalIdempotencyController::class, 'createProduct']);
    Route::get('/products/{product}', [NaturalIdempotencyController::class, 'getProduct']);
    Route::put('/products/{uuid}', [NaturalIdempotencyController::class, 'updateProduct']);
    Route::delete('/products/{uuid}', [NaturalIdempotencyController::class, 'deleteProduct']);
});

// Padrão 3: Version-Based (Optimistic Locking)
Route::prefix('version-based')->group(function () {
    Route::get('/configurations', [VersionBasedController::class, 'index']);
    Route::get('/configurations/{key}', [VersionBasedController::class, 'show']);
    Route::post('/configurations', [VersionBasedController::class, 'store']);
    Route::put('/configurations/{key}', [VersionBasedController::class, 'update']);
    Route::delete('/configurations/{key}', [VersionBasedController::class, 'delete']);
});

// Padrão 4: Token-Based Idempotency
Route::prefix('token-based')->group(function () {
    Route::post('/tokens', [TokenBasedController::class, 'generateToken']);
    Route::post('/submit-form', [TokenBasedController::class, 'submitForm']);
    Route::post('/orders', [TokenBasedController::class, 'createOrder']);
    Route::delete('/cleanup', [TokenBasedController::class, 'cleanupExpiredTokens']);
});

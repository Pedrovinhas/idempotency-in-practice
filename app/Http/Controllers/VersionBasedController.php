<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreConfigurationRequest;
use App\Http\Requests\UpdateConfigurationRequest;
use App\Models\Configuration;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class VersionBasedController extends Controller
{
  public function index(): JsonResponse
  {
    $configurations = Configuration::all();

    return response()->json([
      'configurations' => $configurations
    ], 200);
  }

  public function show(string $key): JsonResponse
  {
    $config = Configuration::where('key', $key)->first();

    if (!$config) {
      return response()->json([
        'error' => 'configuration_not_found',
        'message' => 'Configuration not found'
      ], 404);
    }

    return response()->json([
      'configuration' => $config
    ], 200);
  }

  public function store(StoreConfigurationRequest $request): JsonResponse
  {
    $config = Configuration::create([
      'key' => $request->key,
      'value' => $request->value,
      'updated_by' => $request->updated_by,
      'version' => 1,
    ]);

    Log::info('Configuration created', [
      'key' => $config->key,
      'version' => $config->version,
      'updated_by' => $config->updated_by,
    ]);

    return response()->json([
      'message' => 'Configuration created successfully',
      'configuration' => $config
    ], 201);
  }

  public function update(UpdateConfigurationRequest $request, string $key): JsonResponse
  {
    $config = Configuration::where('key', $key)->first();

    if (!$config) {
      return response()->json([
        'error' => 'configuration_not_found',
        'message' => 'Configuration not found'
      ], 404);
    }

    // Check if version matches (Optimistic Locking)
    if ($config->version !== $request->version) {
      Log::warning('Version conflict detected', [
        'key' => $key,
        'expected_version' => $request->version,
        'current_version' => $config->version,
        'updated_by' => $request->updated_by,
      ]);

      return response()->json([
        'error' => 'version_conflict',
        'message' => 'Version conflict detected. The configuration was modified by another user.',
        'current_version' => $config->version,
        'expected_version' => $request->version,
        'current_value' => $config->value,
      ], 409);
    }

    // Update config and increment version
    $config->value = $request->value;
    $config->updated_by = $request->updated_by;
    $config->version = $config->version + 1;
    $config->save();

    Log::info('Configuration updated', [
      'key' => $config->key,
      'old_version' => $request->version,
      'new_version' => $config->version,
      'updated_by' => $config->updated_by,
    ]);

    return response()->json([
      'message' => 'Configuração atualizada com sucesso',
      'configuration' => $config
    ], 200);
  }

  public function delete(string $key): JsonResponse
  {
    $config = Configuration::where('key', $key)->first();

    if (!$config) {
      return response()->json([
        'error' => 'configuration_not_found',
        'message' => 'Configuration not found'
      ], 404);
    }

    Log::info('Configuration deleted', [
      'key' => $config->key,
      'version' => $config->version,
    ]);

    $config->delete();

    return response()->json([
      'message' => 'Configuration deleted successfully'
    ], 200);
  }
}

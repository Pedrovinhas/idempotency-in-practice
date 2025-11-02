<?php

use App\Models\Configuration;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\assertDatabaseCount;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\deleteJson;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;
use function Pest\Laravel\putJson;

uses(RefreshDatabase::class)->group('version-based');

it('creates a new configuration with version 1', function () {
    $response = postJson('/api/version-based/configurations', [
        'key' => 'site_name',
        'value' => 'My Application',
        'updated_by' => 'user1',
    ]);

    expect($response->status())->toBe(201)
        ->and($response->json('configuration.key'))->toBe('site_name')
        ->and($response->json('configuration.value'))->toBe('My Application')
        ->and($response->json('configuration.version'))->toBe(1)
        ->and($response->json('configuration.updated_by'))->toBe('user1');

    assertDatabaseHas('configurations', [
        'key' => 'site_name',
        'value' => 'My Application',
        'version' => 1,
    ]);
});

it('lists all configurations', function () {
    Configuration::create([
        'key' => 'app_name',
        'value' => 'Laravel App',
        'version' => 1,
    ]);

    Configuration::create([
        'key' => 'app_env',
        'value' => 'production',
        'version' => 1,
    ]);

    $response = getJson('/api/version-based/configurations');

    expect($response->status())->toBe(200)
        ->and($response->json('configurations'))->toHaveCount(2);
});

it('shows a specific configuration', function () {
    $config = Configuration::create([
        'key' => 'max_users',
        'value' => '1000',
        'version' => 1,
    ]);

    $response = getJson("/api/version-based/configurations/{$config->key}");

    expect($response->status())->toBe(200)
        ->and($response->json('configuration.key'))->toBe('max_users')
        ->and($response->json('configuration.value'))->toBe('1000')
        ->and($response->json('configuration.version'))->toBe(1);
});

it('updates configuration with correct version (optimistic locking success)', function () {
    $config = Configuration::create([
        'key' => 'theme',
        'value' => 'light',
        'version' => 1,
        'updated_by' => 'user1',
    ]);

    $response = putJson("/api/version-based/configurations/{$config->key}", [
        'value' => 'dark',
        'version' => 1,
        'updated_by' => 'user2',
    ]);

    expect($response->status())->toBe(200)
        ->and($response->json('configuration.value'))->toBe('dark')
        ->and($response->json('configuration.version'))->toBe(2)
        ->and($response->json('configuration.updated_by'))->toBe('user2');

    assertDatabaseHas('configurations', [
        'key' => 'theme',
        'value' => 'dark',
        'version' => 2,
    ]);
});

it('returns 409 conflict when version does not match (optimistic locking)', function () {
    $config = Configuration::create([
        'key' => 'api_timeout',
        'value' => '30',
        'version' => 3,
        'updated_by' => 'user1',
    ]);

    // Try to update with old version
    $response = putJson("/api/version-based/configurations/{$config->key}", [
        'value' => '60',
        'version' => 1, // Incorrect version
        'updated_by' => 'user2',
    ]);

    expect($response->status())->toBe(409)
        ->and($response->json('error'))->toBe('version_conflict')
        ->and($response->json('current_version'))->toBe(3)
        ->and($response->json('expected_version'))->toBe(1)
        ->and($response->json('current_value'))->toBe('30');

    // Value should not have changed
    assertDatabaseHas('configurations', [
        'key' => 'api_timeout',
        'value' => '30',
        'version' => 3,
    ]);
});

it('handles concurrent updates correctly (race condition)', function () {
    $config = Configuration::create([
        'key' => 'counter',
        'value' => '0',
        'version' => 1,
    ]);

    // User 1 updates first
    $response1 = putJson("/api/version-based/configurations/{$config->key}", [
        'value' => '10',
        'version' => 1,
        'updated_by' => 'user1',
    ]);

    expect($response1->status())->toBe(200)
        ->and($response1->json('configuration.version'))->toBe(2);

    // User 2 tries to update with old version (conflict)
    $response2 = putJson("/api/version-based/configurations/{$config->key}", [
        'value' => '20',
        'version' => 1, // Outdated version
        'updated_by' => 'user2',
    ]);

    expect($response2->status())->toBe(409)
        ->and($response2->json('error'))->toBe('version_conflict');

    // Value should be the one from user 1
    $config->refresh();
    expect($config->value)->toBe('10')
        ->and($config->version)->toBe(2);
});

it('increments version on each successful update', function () {
    $config = Configuration::create([
        'key' => 'status',
        'value' => 'active',
        'version' => 1,
    ]);

    putJson("/api/version-based/configurations/{$config->key}", [
        'value' => 'inactive',
        'version' => 1,
    ]);

    $config->refresh();
    expect($config->version)->toBe(2);

    putJson("/api/version-based/configurations/{$config->key}", [
        'value' => 'active',
        'version' => 2,
    ]);

    $config->refresh();
    expect($config->version)->toBe(3);

    putJson("/api/version-based/configurations/{$config->key}", [
        'value' => 'maintenance',
        'version' => 3,
    ]);

    $config->refresh();
    expect($config->version)->toBe(4)
        ->and($config->value)->toBe('maintenance');
});

it('deletes configuration', function () {
    $config = Configuration::create([
        'key' => 'temp_setting',
        'value' => 'test',
        'version' => 1,
    ]);

    $response = deleteJson("/api/version-based/configurations/{$config->key}");

    expect($response->status())->toBe(200);

    assertDatabaseCount('configurations', 0);
});

it('returns 404 when configuration not found', function () {
    $response = getJson('/api/version-based/configurations/nonexistent');

    expect($response->status())->toBe(404)
        ->and($response->json('error'))->toBe('configuration_not_found');
});

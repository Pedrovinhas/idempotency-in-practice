<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
  use HasFactory, HasUuids, SoftDeletes;

  public function uniqueIds(): array
  {
    return ['uuid'];
  }

  /**
   * Defines the route key name for model binding
   */
  public function getRouteKeyName(): string
  {
    return 'uuid';
  }

  protected $fillable = [
    'name',
    'description',
    'price',
    'stock',
    'active',
  ];

  protected $casts = [
    'price' => 'decimal:2',
    'stock' => 'integer',
    'active' => 'boolean',
    'deleted_at' => 'datetime',
  ];

  /**
   * Hides internal ID from JSON serialization (uses for joins, indexes, foreign keys)
   * Exposes only UUID publicly
   */
  protected $hidden = [
    'id',
  ];
}

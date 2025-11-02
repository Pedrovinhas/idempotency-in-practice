<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  /**
   * Run the migrations.
   */
  public function up(): void
  {
    Schema::create('configurations', function (Blueprint $table) {
      $table->id();
      $table->string('key')->unique();
      $table->text('value');
      $table->unsignedBigInteger('version')->default(1);
      $table->string('updated_by')->nullable();
      $table->timestamps();

      $table->index(['key', 'version']);
    });
  }

  /**
   * Reverse the migrations.
   */
  public function down(): void
  {
    Schema::dropIfExists('configurations');
  }
};

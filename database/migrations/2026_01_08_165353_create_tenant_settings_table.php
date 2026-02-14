<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void
  {
    Schema::create('tenant_settings', function (Blueprint $table) {
      $table->id();

      $table->foreignId('tenant_id')->unique()->constrained('tenants')->cascadeOnDelete();
      $table->json('settings')->nullable(); // currency, email, seo, etc.

      $table->timestamp('created_at')->useCurrent();
      $table->timestamp('updated_at')->useCurrentOnUpdate();
      $table->softDeletes();
    });
  }

  public function down(): void
  {
    Schema::dropIfExists('tenant_settings');
  }
};

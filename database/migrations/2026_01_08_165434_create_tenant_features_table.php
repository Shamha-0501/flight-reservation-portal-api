<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void
  {
    Schema::create('tenant_features', function (Blueprint $table) {
      $table->id();

      $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
      $table->string('feature_key', 80);        // "chat", "cms", "payments"
      $table->boolean('enabled')->default(true);
      $table->json('config')->nullable();       // per-feature config

      $table->timestamp('created_at')->useCurrent();
      $table->timestamp('updated_at')->useCurrentOnUpdate();
      $table->softDeletes();

      $table->unique(['tenant_id', 'feature_key']);
      $table->index(['tenant_id', 'enabled']);
    });
  }

  public function down(): void
  {
    Schema::dropIfExists('tenant_features');
  }
};

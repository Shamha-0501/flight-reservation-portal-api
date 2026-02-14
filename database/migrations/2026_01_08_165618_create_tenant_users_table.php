<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void
  {
    Schema::create('tenant_user', function (Blueprint $table) {
      $table->id();

      $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
      $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

      $table->string('role', 30)->default('customer'); // owner|admin|member
      $table->string('status', 20)->default('active'); // active|invited|disabled

      $table->timestamp('joined_at')->nullable();
      $table->timestamp('last_seen_at')->nullable();
      $table->foreignId('invited_by_user_id')->nullable()->constrained('users')->nullOnDelete();

      $table->timestamp('created_at')->useCurrent();
      $table->timestamp('updated_at')->useCurrentOnUpdate();
      $table->softDeletes();

      $table->unique(['tenant_id', 'user_id']);
      $table->index(['user_id', 'status']);
      $table->index(['tenant_id', 'role']);
    });
  }

  public function down(): void
  {
    Schema::dropIfExists('tenant_user');
  }
};

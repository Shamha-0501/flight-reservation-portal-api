<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    Schema::create('tenants', function (Blueprint $table) {
      $table->id();

      $table->string('key', 80)->unique();      // "acme"
      $table->string('name', 160);              // "Acme Pvt Ltd"

      $table->string('status', 20)->default('active'); // active|suspended|archived
      $table->string('timezone', 64)->default('UTC');
      $table->string('locale', 16)->default('en');

      $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();

      // SaaS lifecycle / billing hooks (optional but common)
      $table->timestamp('trial_ends_at')->nullable();
      $table->timestamp('suspended_at')->nullable();

      $table->json('meta')->nullable(); // industry, contact info, etc.

      $table->timestamp('created_at')->useCurrent();
      $table->timestamp('updated_at')->useCurrentOnUpdate();
      $table->softDeletes();
    });
  }

  public function down(): void {
    Schema::dropIfExists('tenants');
  }
};

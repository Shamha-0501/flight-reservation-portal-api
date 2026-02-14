<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();

            $table->string('kind', 30)->default('image'); // image|file
            $table->string('disk', 40)->default('public');
            $table->text('path');
            $table->string('mime', 120)->nullable();
            $table->unsignedBigInteger('size')->nullable();

            $table->string('label', 120)->nullable(); // "logo_light", "home_hero_bg"
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrentOnUpdate();
            $table->softDeletes();

            $table->index(['tenant_id', 'label']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_assets');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agency_markup_settings', function (Blueprint $table) {
            $table->id();

            $table->foreignId('tenant_id')
                ->constrained('tenants')
                ->cascadeOnDelete();

            $table->boolean('is_enabled')->default(false);
            $table->string('markup_mode', 20)->default('percentage');
            $table->decimal('markup_value', 12, 2)->default(0);
            $table->char('currency', 3)->default('LKR');
            $table->string('display_label')->nullable();
            $table->timestamps();

            $table->unique('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agency_markup_settings');
    }
};

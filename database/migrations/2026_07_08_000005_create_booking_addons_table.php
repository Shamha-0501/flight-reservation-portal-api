<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_addons', function (Blueprint $table) {
            $table->id();

            $table->foreignId('tenant_id')
                ->constrained('tenants')
                ->cascadeOnDelete();

            $table->foreignId('order_id')
                ->constrained('orders')
                ->cascadeOnDelete();

            $table->foreignId('addon_id')
                ->constrained('addons')
                ->restrictOnDelete();

            $table->string('addon_code');
            $table->string('addon_name');
            $table->decimal('price', 12, 2)->default(0);
            $table->char('currency', 3)->default('LKR');
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['order_id', 'addon_id']);
            $table->index(['tenant_id', 'order_id']);
            $table->index(['addon_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_addons');
    }
};

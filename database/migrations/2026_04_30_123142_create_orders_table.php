<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();

            $table->foreignId('tenant_id')
                ->constrained('tenants')
                ->cascadeOnDelete();

            // Duffel identifiers
            $table->string('duffel_order_id')->unique(); // ord_xxx
            $table->string('booking_reference', 32)->nullable();

            // Optional local/customer reference
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            // Order state
            $table->string('type', 20)->nullable(); // instant|hold
            $table->string('status', 40)->nullable();

            // Money
            $table->decimal('base_amount', 12, 2)->nullable();
            $table->char('base_currency', 3)->nullable();

            $table->decimal('tax_amount', 12, 2)->nullable();
            $table->char('tax_currency', 3)->nullable();

            $table->decimal('total_amount', 12, 2);
            $table->char('total_currency', 3);

            // Duffel sync/lifecycle
            $table->timestamp('synced_at')->nullable();
            $table->timestamp('void_window_ends_at')->nullable();

            // Keep only small useful extras, not full huge payload unless needed
            $table->json('meta')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'booking_reference']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
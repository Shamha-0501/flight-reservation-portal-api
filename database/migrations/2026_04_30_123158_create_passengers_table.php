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
        Schema::create('passengers', function (Blueprint $table) {
            $table->id();

            $table->foreignId('tenant_id')
                ->constrained('tenants')
                ->cascadeOnDelete();

            $table->foreignId('order_id')
                ->constrained('orders')
                ->cascadeOnDelete();

            // Duffel passenger reference
            $table->string('duffel_passenger_id')->nullable(); // "pas_xxx"

            // Basic info
            $table->string('type', 30); // adult | child | infant_without_seat
            $table->string('title', 10);
            $table->string('given_name');
            $table->string('family_name');

            $table->date('dob');
            $table->string('gender', 20);

            // Contact (optional)
            $table->string('email')->nullable();
            $table->string('phone_number')->nullable();

            // Relationships
            $table->foreignId('infant_passenger_id')
                ->nullable()
                ->constrained('passengers')
                ->nullOnDelete();

            // Flexible extras (loyalty, documents etc)
            $table->json('meta')->nullable();

            $table->string('status', 40)->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'order_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('passengers');
    }
};

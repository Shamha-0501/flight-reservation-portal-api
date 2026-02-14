<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_domains', function (Blueprint $table) {
            $table->id();

            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();

            $table->string('host', 191)->unique(); // acme.yourapp.com OR acme.com
            $table->string('type', 20)->default('subdomain'); // subdomain|custom
            $table->boolean('is_primary')->default(true);

            // For custom domain verification/ops:
            $table->string('dns_token', 80)->nullable();       // verification token
            $table->timestamp('verified_at')->nullable();
            $table->string('ssl_status', 30)->nullable();      // pending|active|failed

            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrentOnUpdate();

            $table->index(['tenant_id', 'type'], 'tenant_type_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_domains');
    }
};

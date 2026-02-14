<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_page_components', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('page_id')->constrained('tenant_pages')->cascadeOnDelete();

            $table->string('component_type', 60); // HERO, FEATURES, TESTIMONIALS, CTA_BANNER...
            $table->string('variant', 60)->nullable(); // optional: "centered", "split", etc.
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_enabled')->default(true);

            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrentOnUpdate();
            $table->softDeletes();

            $table->index(['page_id', 'sort_order']);
            $table->index(['tenant_id', 'component_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_page_components');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_pages', function (Blueprint $table) {
            $table->id();

            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();

            $table->string('slug', 120);         // "home", "about", "contact"
            $table->string('title', 191);
            $table->json('schema')->nullable();  // your JSON-driven page builder schema
            $table->json('seo')->nullable();     // meta title/desc, og tags, etc.

            $table->string('status', 20)->default('published'); // draft|published
            $table->string('meta_title', 191)->nullable();
            $table->text('meta_description')->nullable();
            $table->foreignId('og_asset_id')->nullable()->constrained('tenant_assets')->nullOnDelete();

            $table->timestamp('published_at')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrentOnUpdate();
            $table->softDeletes();

            $table->unique(['tenant_id', 'slug']);
            $table->index(['tenant_id', 'status', 'published_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_pages');
    }
};

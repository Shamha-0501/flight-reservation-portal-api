<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_branding', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->unique()->constrained('tenants')->cascadeOnDelete();

            $table->string('brand_name', 160);
            $table->string('site_title', 191)->nullable();

            $table->foreignId('logo_light_asset_id')->nullable()->constrained('tenant_assets')->nullOnDelete();
            $table->foreignId('logo_dark_asset_id')->nullable()->constrained('tenant_assets')->nullOnDelete();
            $table->foreignId('favicon_asset_id')->nullable()->constrained('tenant_assets')->nullOnDelete();
            $table->foreignId('default_og_asset_id')->nullable()->constrained('tenant_assets')->nullOnDelete();

            $table->string('primary_color', 32)->nullable();
            $table->string('secondary_color', 32)->nullable();
            $table->string('accent_color', 32)->nullable();
            $table->string('font_family', 120)->nullable();

            $table->string('support_email', 190)->nullable();
            $table->string('support_phone', 40)->nullable();

            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrentOnUpdate();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_branding');
    }
};

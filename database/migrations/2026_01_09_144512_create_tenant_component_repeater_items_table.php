<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_component_repeater_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('component_id')->constrained('tenant_page_components')->cascadeOnDelete();

            $table->string('repeater_key', 60);      // features, testimonials, faqs
            $table->unsignedInteger('sort_order')->default(0);

            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrentOnUpdate();
            $table->softDeletes();

            $table->index(['component_id', 'repeater_key', 'sort_order'], 'tcri_comp_rep_sort_idx');
        });

        Schema::create('tenant_repeater_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('repeater_item_id')->constrained('tenant_component_repeater_items')->cascadeOnDelete();

            $table->string('field_key', 80);       // label, desc, icon, avatar, etc.
            $table->string('field_type', 20);      // string|text|int|bool|decimal|asset|url

            $table->string('value_string', 191)->nullable();
            $table->longText('value_text')->nullable();
            $table->bigInteger('value_int')->nullable();
            $table->boolean('value_bool')->nullable();
            $table->decimal('value_decimal', 14, 4)->nullable();
            $table->foreignId('value_asset_id')->nullable()->constrained('tenant_assets')->nullOnDelete();

            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrentOnUpdate();
            $table->softDeletes();

            $table->unique(['repeater_item_id', 'field_key']);
            $table->index(['repeater_item_id', 'field_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_repeater_fields');
        Schema::dropIfExists('tenant_component_repeater_items');
    }
};

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
        Schema::create('tenant_addon_settings', function (Blueprint $table) {
            $table->id();

            $table->foreignId('tenant_id')
                ->constrained('tenants')
                ->cascadeOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Cancellation & Changes
            |--------------------------------------------------------------------------
            */

            $table->boolean('cancellation_guarantee_enabled')->default(false);
            $table->decimal('cancellation_guarantee_price', 12, 2)->nullable();
            $table->string('cancellation_guarantee_type')->nullable();
            // 50_percent | 80_percent | 100_percent | voucher

            $table->boolean('flexible_change_enabled')->default(false);
            $table->decimal('flexible_change_price', 12, 2)->nullable();

            $table->boolean('rebooking_assistance_enabled')->default(false);
            $table->decimal('rebooking_assistance_price', 12, 2)->nullable();

            $table->boolean('name_correction_enabled')->default(false);
            $table->decimal('name_correction_price', 12, 2)->nullable();

            /*
            |--------------------------------------------------------------------------
            | Protection & Add-ons
            |--------------------------------------------------------------------------
            */

            $table->boolean('travel_insurance_enabled')->default(false);
            $table->decimal('travel_insurance_price', 12, 2)->nullable();

            $table->boolean('priority_support_enabled')->default(false);
            $table->decimal('priority_support_price', 12, 2)->nullable();

            $table->boolean('sms_alert_enabled')->default(false);
            $table->decimal('sms_alert_price', 12, 2)->nullable();

            $table->boolean('whatsapp_alert_enabled')->default(false);
            $table->decimal('whatsapp_alert_price', 12, 2)->nullable();

            $table->boolean('airport_assistance_enabled')->default(false);
            $table->decimal('airport_assistance_price', 12, 2)->nullable();

            $table->boolean('checkin_assistance_enabled')->default(false);
            $table->decimal('checkin_assistance_price', 12, 2)->nullable();

            $table->boolean('baggage_protection_enabled')->default(false);
            $table->decimal('baggage_protection_price', 12, 2)->nullable();

            $table->boolean('disruption_support_enabled')->default(false);
            $table->decimal('disruption_support_price', 12, 2)->nullable();

            /*
            |--------------------------------------------------------------------------
            | Premium Services
            |--------------------------------------------------------------------------
            */

            $table->boolean('lounge_access_enabled')->default(false);
            $table->decimal('lounge_access_price', 12, 2)->nullable();

            $table->boolean('fast_track_enabled')->default(false);
            $table->decimal('fast_track_price', 12, 2)->nullable();

            $table->boolean('priority_boarding_enabled')->default(false);
            $table->decimal('priority_boarding_price', 12, 2)->nullable();

            /*
            |--------------------------------------------------------------------------
            | General
            |--------------------------------------------------------------------------
            */

            $table->char('currency', 3)->default('USD');

            $table->timestamps();

            $table->unique('tenant_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenant_addon_settings');
    }
};

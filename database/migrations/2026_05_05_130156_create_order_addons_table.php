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
        Schema::create('order_addons', function (Blueprint $table) {
            $table->id();

            $table->foreignId('tenant_id')
                ->constrained('tenants')
                ->cascadeOnDelete();

            $table->foreignId('order_id')
                ->constrained('orders')
                ->cascadeOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Duffel-supported ancillaries
            |--------------------------------------------------------------------------
            */

            // Extra baggage
            $table->boolean('duffel_baggage_enabled')->default(false);
            $table->unsignedSmallInteger('duffel_baggage_count')->default(0);
            $table->decimal('duffel_baggage_amount', 12, 2)->nullable();
            $table->char('duffel_baggage_currency', 3)->nullable();

            // Seat selection
            $table->boolean('duffel_seat_enabled')->default(false);
            $table->unsignedSmallInteger('duffel_seat_count')->default(0);
            $table->decimal('duffel_seat_amount', 12, 2)->nullable();
            $table->char('duffel_seat_currency', 3)->nullable();

            /*
            |--------------------------------------------------------------------------
            | Agency-controlled cancellation/change services
            |--------------------------------------------------------------------------
            */

            $table->boolean('cancellation_guarantee_enabled')->default(false);
            $table->string('cancellation_guarantee_type')->nullable();
            // 50_percent | 80_percent | 100_percent | cancel_for_any_reason | voucher

            $table->boolean('flexible_date_change_enabled')->default(false);
            $table->string('flexible_date_change_type')->nullable();
            // one_free_change | reduced_fee | fare_difference_only

            $table->boolean('rebooking_assistance_enabled')->default(false);
            $table->boolean('name_correction_support_enabled')->default(false);
            $table->boolean('schedule_change_support_enabled')->default(false);

            /*
            |--------------------------------------------------------------------------
            | Agency-controlled protection/add-ons
            |--------------------------------------------------------------------------
            */

            $table->boolean('travel_insurance_enabled')->default(false);
            $table->string('travel_insurance_plan')->nullable();

            $table->boolean('notification_alerts_enabled')->default(false);
            $table->boolean('sms_alerts_enabled')->default(false);
            $table->boolean('whatsapp_alerts_enabled')->default(false);

            $table->boolean('priority_support_enabled')->default(false);
            $table->string('priority_support_type')->nullable();
            // chat | call | 24_7 | dedicated_agent

            $table->boolean('disruption_compensation_support_enabled')->default(false);
            $table->boolean('baggage_protection_enabled')->default(false);
            $table->boolean('airport_assistance_enabled')->default(false);
            $table->boolean('checkin_assistance_enabled')->default(false);

            $table->boolean('travel_connectivity_enabled')->default(false);
            $table->string('travel_connectivity_type')->nullable();
            // esim | roaming | destination_sim

            $table->boolean('premium_airport_services_enabled')->default(false);
            $table->string('premium_airport_service_type')->nullable();
            // lounge | fast_track_security | priority_boarding

            /*
            |--------------------------------------------------------------------------
            | Pricing summary
            |--------------------------------------------------------------------------
            */

            $table->decimal('agency_addons_amount', 12, 2)->default(0);
            $table->decimal('duffel_addons_amount', 12, 2)->default(0);
            $table->decimal('total_addons_amount', 12, 2)->default(0);
            $table->char('currency', 3)->nullable();

            $table->timestamps();

            $table->unique('order_id');
            $table->index(['tenant_id', 'order_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_addons');
    }
};

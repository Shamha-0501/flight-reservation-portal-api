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
        Schema::create('pbac_policy_context', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained();
            $table->foreignId('policy_id')->constrained('pbac_policy');
            $table->string('scope', 50);
            $table->string('left_operand', 120);
            $table->string('operator', 20);
            $table->string('right_type', 10);
            $table->string('right_ref', 120)->nullable();
            $table->string('right_value_string')->nullable();
            $table->bigInteger('right_value_int')->nullable();
            $table->boolean('right_value_bool')->nullable();
            $table->decimal('right_value_decimal', 14, 4)->nullable();   
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrentOnUpdate();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('policy_context');
    }
};

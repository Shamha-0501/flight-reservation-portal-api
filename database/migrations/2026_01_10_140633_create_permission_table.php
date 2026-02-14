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
        Schema::create('pbac_permission', function (Blueprint $table) {
            $table->id();
            $table->foreignId('action_id')->constrained('pbac_actions');
            $table->foreignId('resource_id')->constrained('pbac_resources');
            $table->string('key', 140)->unique();
            $table->string('label', 160);
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrentOnUpdate();
            $table->softDeletes();

            $table->unique(['action_id', 'resource_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('permission');
    }
};

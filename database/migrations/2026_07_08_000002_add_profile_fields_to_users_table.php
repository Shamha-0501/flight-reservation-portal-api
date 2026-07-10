<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone', 40)->nullable()->after('email');
            $table->string('country', 120)->nullable()->after('phone');
            $table->string('city', 120)->nullable()->after('country');
            $table->string('postal_code', 40)->nullable()->after('city');
            $table->string('address', 255)->nullable()->after('postal_code');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['phone', 'country', 'city', 'postal_code', 'address']);
        });
    }
};

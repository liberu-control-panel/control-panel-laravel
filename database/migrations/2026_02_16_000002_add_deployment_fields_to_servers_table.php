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
        Schema::table('servers', function (Blueprint $table) {
            $table->string('deployment_mode')->nullable()->after('type');
            $table->string('cloud_provider')->nullable()->after('deployment_mode');
            $table->boolean('auto_scaling_enabled')->default(false)->after('cloud_provider');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            $table->dropColumn(['deployment_mode', 'cloud_provider', 'auto_scaling_enabled']);
        });
    }
};

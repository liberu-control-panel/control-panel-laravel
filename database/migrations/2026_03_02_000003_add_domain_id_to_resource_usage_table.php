<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Add optional domain_id to resource_usage so we can track bandwidth and
     * disk usage at the per-domain level (as Virtualmin does), in addition to
     * the existing per-user aggregation.
     */
    public function up(): void
    {
        Schema::table('resource_usage', function (Blueprint $table) {
            $table->unsignedBigInteger('domain_id')->nullable()->after('user_id');
            $table->integer('cpu_usage')->nullable()->after('bandwidth_usage');    // percentage * 100
            $table->integer('memory_usage')->nullable()->after('cpu_usage');       // MB

            $table->foreign('domain_id')->references('id')->on('domains')->onDelete('cascade');
            $table->index(['domain_id', 'year', 'month']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('resource_usage', function (Blueprint $table) {
            $table->dropForeign(['domain_id']);
            $table->dropIndex(['domain_id', 'year', 'month']);
            $table->dropColumn(['domain_id', 'cpu_usage', 'memory_usage']);
        });
    }
};

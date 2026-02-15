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
        Schema::create('website_performance_metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('website_id')->constrained()->onDelete('cascade');
            $table->integer('response_time_ms')->default(0);
            $table->integer('status_code')->default(200);
            $table->boolean('uptime_status')->default(true);
            $table->decimal('cpu_usage', 5, 2)->nullable()->comment('CPU usage percentage');
            $table->decimal('memory_usage', 5, 2)->nullable()->comment('Memory usage percentage');
            $table->decimal('disk_usage', 10, 2)->nullable()->comment('Disk usage in MB');
            $table->bigInteger('bandwidth_used')->default(0)->comment('Bandwidth used in bytes');
            $table->integer('visitors_count')->default(0);
            $table->timestamp('checked_at');
            $table->timestamps();

            $table->index(['website_id', 'checked_at']);
            $table->index('uptime_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('website_performance_metrics');
    }
};

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
        Schema::create('websites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('server_id')->nullable()->constrained()->onDelete('set null');
            $table->string('name');
            $table->string('domain')->unique();
            $table->text('description')->nullable();
            $table->string('platform')->default('custom');
            $table->string('php_version')->default('8.3');
            $table->string('database_type')->default('mysql');
            $table->string('document_root')->default('/var/www/html');
            $table->string('status')->default('pending');
            $table->boolean('ssl_enabled')->default(false);
            $table->boolean('auto_ssl')->default(true);
            
            // Performance metrics
            $table->decimal('uptime_percentage', 5, 2)->default(100.00);
            $table->timestamp('last_checked_at')->nullable();
            $table->integer('average_response_time')->default(0)->comment('Average response time in ms');
            $table->bigInteger('monthly_bandwidth')->default(0)->comment('Monthly bandwidth in bytes');
            $table->integer('monthly_visitors')->default(0);
            $table->decimal('disk_usage_mb', 10, 2)->default(0);
            
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index('domain');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('websites');
    }
};

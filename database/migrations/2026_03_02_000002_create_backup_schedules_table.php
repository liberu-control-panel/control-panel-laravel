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
        Schema::create('backup_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('domain_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('name');
            $table->string('type')->default('full');                     // full, files, database, email
            $table->string('frequency')->default('daily');               // daily, weekly, monthly
            $table->time('schedule_time')->default('02:00');             // time of day to run
            $table->foreignId('destination_id')
                  ->nullable()
                  ->constrained('backup_destinations')
                  ->onDelete('set null');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('retention_days')->default(30);      // keep backups for N days
            $table->timestamp('last_run_at')->nullable();
            $table->timestamps();

            $table->index(['domain_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('backup_schedules');
    }
};

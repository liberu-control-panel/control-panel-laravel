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
        Schema::create('kubernetes_nodes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('server_id')->constrained()->onDelete('cascade');
            $table->string('name')->unique();
            $table->string('uid')->unique()->nullable();
            $table->string('kubernetes_version')->nullable();
            $table->string('container_runtime')->nullable();
            $table->string('os_image')->nullable();
            $table->string('kernel_version')->nullable();
            $table->string('architecture')->default('amd64');
            $table->enum('status', ['Ready', 'NotReady', 'Unknown', 'SchedulingDisabled'])->default('Unknown');
            $table->boolean('schedulable')->default(true);
            $table->json('labels')->nullable();
            $table->json('annotations')->nullable();
            $table->json('taints')->nullable();
            $table->json('addresses')->nullable();
            $table->json('capacity')->nullable();
            $table->json('allocatable')->nullable();
            $table->json('conditions')->nullable();
            $table->timestamp('last_heartbeat_time')->nullable();
            $table->text('status_message')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            $table->index('server_id');
            $table->index('status');
            $table->index('schedulable');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kubernetes_nodes');
    }
};

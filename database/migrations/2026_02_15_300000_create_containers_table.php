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
        Schema::create('containers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('domain_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('name');
            $table->string('type')->default('web');
            $table->string('image');
            $table->string('container_name')->nullable();
            $table->string('status')->default('stopped');
            $table->json('ports')->nullable();
            $table->json('environment')->nullable();
            $table->json('volumes')->nullable();
            $table->string('cpu_limit')->nullable();
            $table->string('memory_limit')->nullable();
            $table->string('restart_policy')->default('unless-stopped');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('containers');
    }
};

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
        Schema::create('servers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('hostname');
            $table->integer('port')->default(22);
            $table->string('ip_address');
            $table->enum('type', ['kubernetes', 'docker', 'standalone'])->default('kubernetes');
            $table->enum('status', ['active', 'inactive', 'maintenance', 'error'])->default('active');
            $table->text('description')->nullable();
            $table->json('metadata')->nullable();
            $table->boolean('is_default')->default(false);
            $table->integer('max_domains')->default(100);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('servers');
    }
};

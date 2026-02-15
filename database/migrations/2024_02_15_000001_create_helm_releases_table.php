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
        Schema::create('helm_releases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('server_id')->constrained()->onDelete('cascade');
            $table->string('release_name');
            $table->string('chart_name');
            $table->string('chart_version')->nullable();
            $table->string('namespace')->default('default');
            $table->string('status')->default('pending'); // pending, deployed, failed, uninstalled
            $table->json('values')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('installed_at')->nullable();
            $table->timestamps();

            $table->unique(['server_id', 'release_name', 'namespace']);
            $table->index(['server_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('helm_releases');
    }
};

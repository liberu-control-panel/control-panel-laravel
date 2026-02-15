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
        Schema::create('laravel_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('domain_id')->constrained()->onDelete('cascade');
            $table->foreignId('database_id')->nullable()->constrained()->onDelete('set null');
            $table->string('repository_slug'); // e.g., 'accounting', 'crm', 'ecommerce'
            $table->string('repository_name'); // e.g., 'Accounting', 'CRM'
            $table->string('repository_url'); // e.g., 'liberu-accounting/accounting-laravel'
            $table->string('version')->nullable();
            $table->string('php_version')->default('8.2');
            $table->string('install_path')->default('/public_html');
            $table->string('app_url');
            $table->enum('status', ['pending', 'installing', 'installed', 'failed', 'updating'])->default('pending');
            $table->text('installation_log')->nullable();
            $table->timestamp('installed_at')->nullable();
            $table->timestamp('last_update_check')->nullable();
            $table->timestamps();

            // Add index for better query performance
            $table->index(['repository_slug', 'status']);
            $table->index('domain_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('laravel_applications');
    }
};

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
        Schema::create('wordpress_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('domain_id')->constrained()->onDelete('cascade');
            $table->foreignId('database_id')->nullable()->constrained()->onDelete('set null');
            $table->string('version')->nullable();
            $table->string('php_version')->default('8.2');
            $table->string('admin_username');
            $table->string('admin_email');
            $table->string('admin_password')->nullable();
            $table->string('site_title');
            $table->string('site_url');
            $table->string('install_path')->default('/public_html');
            $table->enum('status', ['pending', 'installing', 'installed', 'failed', 'updating'])->default('pending');
            $table->text('installation_log')->nullable();
            $table->timestamp('installed_at')->nullable();
            $table->timestamp('last_update_check')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wordpress_applications');
    }
};

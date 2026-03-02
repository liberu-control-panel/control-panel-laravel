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
        Schema::create('php_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('domain_id')->constrained()->onDelete('cascade');
            $table->string('php_version')->default('8.2');
            $table->unsignedInteger('memory_limit')->default(128);          // MB
            $table->unsignedInteger('upload_max_filesize')->default(64);    // MB
            $table->unsignedInteger('post_max_size')->default(64);          // MB
            $table->unsignedInteger('max_execution_time')->default(60);     // seconds
            $table->unsignedInteger('max_input_time')->default(60);         // seconds
            $table->unsignedInteger('max_input_vars')->default(1000);
            $table->boolean('display_errors')->default(false);
            $table->boolean('short_open_tag')->default(false);
            $table->string('error_reporting')->default('E_ALL & ~E_DEPRECATED & ~E_STRICT');
            $table->string('session_save_path')->nullable();
            $table->json('custom_settings')->nullable();                     // additional php.ini directives
            $table->timestamps();

            $table->unique('domain_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('php_configs');
    }
};

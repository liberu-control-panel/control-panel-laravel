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
        Schema::create('custom_error_pages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('domain_id');
            $table->integer('error_code'); // 400, 401, 403, 404, 500, etc.
            $table->text('custom_content')->nullable(); // HTML content
            $table->string('custom_file_path')->nullable(); // Path to custom error file
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // Foreign key constraint
            $table->foreign('domain_id')->references('id')->on('domains')->onDelete('cascade');
            
            // Unique constraint - one custom error page per error code per domain
            $table->unique(['domain_id', 'error_code']);
        });

        Schema::create('mime_types', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('domain_id');
            $table->string('extension'); // e.g., '.webp', '.svg'
            $table->string('mime_type'); // e.g., 'image/webp', 'image/svg+xml'
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // Foreign key constraint
            $table->foreign('domain_id')->references('id')->on('domains')->onDelete('cascade');
            
            // Unique constraint
            $table->unique(['domain_id', 'extension']);
        });

        Schema::create('redirects', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('domain_id');
            $table->string('source_path'); // Source path or pattern
            $table->string('destination_url'); // Destination URL
            $table->enum('redirect_type', ['301', '302', '307', '308'])->default('301');
            $table->boolean('match_query_string')->default(false);
            $table->boolean('is_regex')->default(false);
            $table->boolean('is_active')->default(true);
            $table->integer('priority')->default(100); // Lower = higher priority
            $table->timestamps();

            // Foreign key constraint
            $table->foreign('domain_id')->references('id')->on('domains')->onDelete('cascade');
            
            // Indexes
            $table->index(['domain_id', 'is_active', 'priority']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('redirects');
        Schema::dropIfExists('mime_types');
        Schema::dropIfExists('custom_error_pages');
    }
};

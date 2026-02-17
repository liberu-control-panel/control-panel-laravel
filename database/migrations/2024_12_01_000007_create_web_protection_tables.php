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
        Schema::create('hotlink_protections', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('domain_id');
            $table->boolean('enabled')->default(false);
            $table->json('allowed_domains')->nullable(); // Domains allowed to hotlink
            $table->json('protected_extensions')->nullable(); // File extensions to protect (jpg, png, etc.)
            $table->string('redirect_url')->nullable(); // URL to redirect hotlinkers to
            $table->boolean('allow_blank_referrer')->default(false);
            $table->timestamps();

            // Foreign key constraint
            $table->foreign('domain_id')->references('id')->on('domains')->onDelete('cascade');
        });

        Schema::create('directory_protections', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('domain_id');
            $table->string('directory_path');
            $table->string('auth_name')->default('Protected Area');
            $table->string('htpasswd_file_path');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // Foreign key constraint
            $table->foreign('domain_id')->references('id')->on('domains')->onDelete('cascade');
            
            // Indexes
            $table->index(['domain_id', 'directory_path']);
        });

        Schema::create('directory_protection_users', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('directory_protection_id');
            $table->string('username');
            $table->string('password'); // Hashed with APR1-MD5
            $table->timestamps();

            // Foreign key constraint
            $table->foreign('directory_protection_id', 'dir_prot_users_fk')
                  ->references('id')
                  ->on('directory_protections')
                  ->onDelete('cascade');
            
            // Unique constraint
            $table->unique(['directory_protection_id', 'username'], 'dir_prot_users_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('directory_protection_users');
        Schema::dropIfExists('directory_protections');
        Schema::dropIfExists('hotlink_protections');
    }
};

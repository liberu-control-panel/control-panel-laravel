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
        Schema::create('sftp_accounts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('domain_id')->nullable();
            $table->string('username')->unique();
            $table->string('password')->nullable(); // Optional for SSH key auth
            $table->string('home_directory');
            $table->bigInteger('quota_mb')->nullable(); // Quota in MB
            $table->bigInteger('bandwidth_limit_mb')->nullable(); // Bandwidth limit in MB
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_login_at')->nullable();
            
            // SSH Key authentication
            $table->boolean('ssh_key_auth_enabled')->default(false);
            $table->text('ssh_public_key')->nullable();
            $table->text('ssh_private_key')->nullable(); // Encrypted, for user download
            $table->string('ssh_key_type')->default('rsa'); // rsa, ed25519, ecdsa
            $table->integer('ssh_key_bits')->default(4096); // For RSA keys
            
            $table->timestamps();

            // Foreign key constraints
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('domain_id')->references('id')->on('domains')->onDelete('cascade');
            
            // Indexes
            $table->index('username');
            $table->index(['user_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sftp_accounts');
    }


};

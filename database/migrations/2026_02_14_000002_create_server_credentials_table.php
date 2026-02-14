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
        Schema::create('server_credentials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('server_id')->constrained()->onDelete('cascade');
            $table->string('username');
            $table->enum('auth_type', ['password', 'ssh_key', 'both'])->default('ssh_key');
            $table->text('password')->nullable(); // Encrypted
            $table->text('ssh_private_key')->nullable(); // Encrypted
            $table->text('ssh_public_key')->nullable();
            $table->string('ssh_key_passphrase')->nullable(); // Encrypted
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('server_credentials');
    }
};

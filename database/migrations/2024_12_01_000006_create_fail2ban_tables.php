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
        Schema::create('fail2ban_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable(); // null = system-wide
            $table->string('jail_name'); // e.g., 'sshd', 'postfix', 'nginx-limit-req'
            $table->boolean('enabled')->default(true);
            $table->integer('max_retry')->default(5);
            $table->integer('find_time')->default(600); // seconds
            $table->integer('ban_time')->default(3600); // seconds
            $table->json('whitelist_ips')->nullable(); // Array of IPs to never ban
            $table->timestamps();

            // Foreign key constraint
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            
            // Indexes
            $table->unique(['user_id', 'jail_name']);
        });

        Schema::create('fail2ban_bans', function (Blueprint $table) {
            $table->id();
            $table->string('jail_name');
            $table->string('ip_address');
            $table->timestamp('banned_at');
            $table->timestamp('unbanned_at')->nullable();
            $table->integer('ban_count')->default(1);
            $table->text('reason')->nullable();
            $table->timestamps();
            
            // Indexes
            $table->index(['ip_address', 'unbanned_at']);
            $table->index('jail_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fail2ban_bans');
        Schema::dropIfExists('fail2ban_settings');
    }
};

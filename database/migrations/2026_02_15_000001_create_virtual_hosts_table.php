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
        Schema::create('virtual_hosts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('domain_id')->nullable()->constrained()->onDelete('cascade');
            $table->foreignId('server_id')->nullable()->constrained()->onDelete('set null');
            $table->string('hostname')->unique();
            $table->string('document_root')->default('/var/www/html');
            $table->string('php_version')->default('8.3');
            $table->boolean('ssl_enabled')->default(false);
            $table->foreignId('ssl_certificate_id')->nullable()->constrained('ssl_certificates')->onDelete('set null');
            $table->boolean('letsencrypt_enabled')->default(true);
            $table->text('nginx_config')->nullable();
            $table->string('status')->default('pending');
            $table->integer('port')->default(80);
            $table->string('ipv4_address')->nullable();
            $table->string('ipv6_address')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index('hostname');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('virtual_hosts');
    }
};

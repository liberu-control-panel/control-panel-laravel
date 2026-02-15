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
        Schema::table('domains', function (Blueprint $table) {
            $table->string('virtual_host')->nullable()->after('domain_name');
            $table->string('letsencrypt_host')->nullable()->after('virtual_host');
            $table->string('letsencrypt_email')->nullable()->after('letsencrypt_host');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('domains', function (Blueprint $table) {
            $table->dropColumn(['virtual_host', 'letsencrypt_host', 'letsencrypt_email']);
        });
    }
};

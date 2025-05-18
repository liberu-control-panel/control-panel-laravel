<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

   return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('domains', function (Blueprint $table) {
            $table->string('sftp_username')->nullable();
            $table->string('sftp_password')->nullable();
            $table->string('ssh_username')->nullable();
            $table->string('ssh_password')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('domains', function (Blueprint $table) {
            $table->dropColumn(['sftp_username', 'sftp_password', 'ssh_username', 'ssh_password']);
        });
    }
}

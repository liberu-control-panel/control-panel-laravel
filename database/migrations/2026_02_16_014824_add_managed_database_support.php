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
        Schema::table('databases', function (Blueprint $table) {
            // Connection type: 'self-hosted' or 'managed'
            $table->string('connection_type')->default('self-hosted')->after('engine');
            
            // Cloud provider for managed databases (aws, azure, digitalocean, ovh, gcp)
            $table->string('provider')->nullable()->after('connection_type');
            
            // External host for managed databases
            $table->string('external_host')->nullable()->after('provider');
            $table->integer('external_port')->nullable()->after('external_host');
            
            // External username for managed databases
            $table->string('external_username')->nullable()->after('external_port');
            
            // Encrypted external password for managed databases
            $table->text('external_password')->nullable()->after('external_username');
            
            // SSL/TLS configuration for managed databases
            $table->boolean('use_ssl')->default(false)->after('external_password');
            $table->text('ssl_ca')->nullable()->after('use_ssl');
            $table->text('ssl_cert')->nullable()->after('ssl_ca');
            $table->text('ssl_key')->nullable()->after('ssl_cert');
            
            // Managed database instance identifier
            $table->string('instance_identifier')->nullable()->after('ssl_key');
            
            // Region for cloud provider
            $table->string('region')->nullable()->after('instance_identifier');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('databases', function (Blueprint $table) {
            $table->dropColumn([
                'connection_type',
                'provider',
                'external_host',
                'external_port',
                'external_username',
                'external_password',
                'use_ssl',
                'ssl_ca',
                'ssl_cert',
                'ssl_key',
                'instance_identifier',
                'region',
            ]);
        });
    }
};

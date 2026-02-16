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
        Schema::table('git_deployments', function (Blueprint $table) {
            // OAuth integration
            $table->foreignId('connected_account_id')
                ->nullable()
                ->after('domain_id')
                ->constrained('connected_accounts')
                ->nullOnDelete();
            
            $table->boolean('use_oauth')
                ->default(false)
                ->after('connected_account_id')
                ->comment('Use OAuth token instead of deploy key for authentication');
            
            // Container isolation
            $table->foreignId('container_id')
                ->nullable()
                ->after('use_oauth')
                ->constrained('containers')
                ->nullOnDelete();
            
            $table->string('kubernetes_pod_name')
                ->nullable()
                ->after('container_id')
                ->comment('Name of the Kubernetes pod for this deployment');
            
            $table->string('kubernetes_namespace')
                ->nullable()
                ->after('kubernetes_pod_name')
                ->comment('Kubernetes namespace for this deployment');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('git_deployments', function (Blueprint $table) {
            $table->dropForeign(['connected_account_id']);
            $table->dropForeign(['container_id']);
            $table->dropColumn([
                'connected_account_id',
                'use_oauth',
                'container_id',
                'kubernetes_pod_name',
                'kubernetes_namespace',
            ]);
        });
    }
};

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
        Schema::create('git_deployments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('domain_id')->constrained()->onDelete('cascade');
            $table->string('repository_url');
            $table->enum('repository_type', ['github', 'gitlab', 'bitbucket', 'other'])->default('other');
            $table->string('branch')->default('main');
            $table->string('deploy_path')->default('/public_html');
            $table->text('deploy_key')->nullable();
            $table->string('webhook_secret')->nullable();
            $table->enum('status', ['pending', 'cloning', 'deployed', 'failed', 'updating'])->default('pending');
            $table->text('deployment_log')->nullable();
            $table->text('build_command')->nullable();
            $table->text('deploy_command')->nullable();
            $table->boolean('auto_deploy')->default(false);
            $table->timestamp('last_deployed_at')->nullable();
            $table->string('last_commit_hash')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('git_deployments');
    }
};

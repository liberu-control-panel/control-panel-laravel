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
        Schema::create('installation_metadata', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->string('type')->default('string'); // string, json, boolean, integer
            $table->text('description')->nullable();
            $table->boolean('is_editable')->default(false);
            $table->timestamps();
        });

        // Insert default installation metadata
        DB::table('installation_metadata')->insert([
            [
                'key' => 'deployment_mode',
                'value' => null,
                'type' => 'string',
                'description' => 'Current deployment mode: standalone, docker-compose, or kubernetes',
                'is_editable' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'cloud_provider',
                'value' => null,
                'type' => 'string',
                'description' => 'Cloud provider: aws, azure, gcp, digitalocean, ovh, etc.',
                'is_editable' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'auto_scaling_enabled',
                'value' => 'false',
                'type' => 'boolean',
                'description' => 'Whether auto-scaling is enabled globally',
                'is_editable' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'installation_date',
                'value' => now()->toDateTimeString(),
                'type' => 'string',
                'description' => 'When the control panel was first installed',
                'is_editable' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('installation_metadata');
    }
};

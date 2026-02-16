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
        Schema::create('backup_destinations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type'); // local, sftp, ftp, s3
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->json('configuration'); // stores type-specific configuration
            $table->text('description')->nullable();
            $table->integer('retention_days')->default(30);
            $table->timestamps();

            $table->index('type');
            $table->index('is_default');
        });

        // Add destination_id to backups table
        Schema::table('backups', function (Blueprint $table) {
            $table->foreignId('destination_id')->nullable()->after('domain_id')->constrained('backup_destinations')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('backups', function (Blueprint $table) {
            $table->dropForeign(['destination_id']);
            $table->dropColumn('destination_id');
        });
        
        Schema::dropIfExists('backup_destinations');
    }
};

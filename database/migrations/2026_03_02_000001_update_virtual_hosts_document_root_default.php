<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Update the document_root column default to use /home/<username>/public_html
     * instead of /var/www/html.  Existing rows are left unchanged because their
     * value was already set at insertion time.
     */
    public function up(): void
    {
        Schema::table('virtual_hosts', function (Blueprint $table) {
            // Remove the old /var/www/html default; the application layer now
            // derives the correct per-user path at creation time.
            $table->string('document_root')->nullable()->default(null)->change();
        });
    }

    /**
     * Restore the previous default.
     */
    public function down(): void
    {
        Schema::table('virtual_hosts', function (Blueprint $table) {
            $table->string('document_root')->default('/var/www/html')->change();
        });
    }
};

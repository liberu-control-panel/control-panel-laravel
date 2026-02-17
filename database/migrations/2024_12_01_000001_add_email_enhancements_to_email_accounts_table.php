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
        Schema::table('email_accounts', function (Blueprint $table) {
            // Email autoresponder fields
            $table->boolean('autoresponder_enabled')->default(false)->after('forwarding_rules');
            $table->string('autoresponder_subject')->nullable()->after('autoresponder_enabled');
            $table->text('autoresponder_message')->nullable()->after('autoresponder_subject');
            $table->timestamp('autoresponder_start_date')->nullable()->after('autoresponder_message');
            $table->timestamp('autoresponder_end_date')->nullable()->after('autoresponder_start_date');
            
            // Spam filter settings
            $table->boolean('spam_filter_enabled')->default(true)->after('autoresponder_end_date');
            $table->integer('spam_threshold')->default(5)->after('spam_filter_enabled'); // SpamAssassin score
            $table->enum('spam_action', ['delete', 'move_to_spam', 'tag'])->default('move_to_spam')->after('spam_threshold');
            
            // Email forwarding enhancements
            $table->boolean('keep_copy_on_server')->default(true)->after('spam_action');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('email_accounts', function (Blueprint $table) {
            $table->dropColumn([
                'autoresponder_enabled',
                'autoresponder_subject',
                'autoresponder_message',
                'autoresponder_start_date',
                'autoresponder_end_date',
                'spam_filter_enabled',
                'spam_threshold',
                'spam_action',
                'keep_copy_on_server'
            ]);
        });
    }
};

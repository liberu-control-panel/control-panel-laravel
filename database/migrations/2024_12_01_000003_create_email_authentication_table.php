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
        Schema::create('email_authentication', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('domain_id')->unique();
            
            // SPF (Sender Policy Framework)
            $table->boolean('spf_enabled')->default(true);
            $table->text('spf_record')->nullable();
            
            // DKIM (DomainKeys Identified Mail)
            $table->boolean('dkim_enabled')->default(true);
            $table->string('dkim_selector')->default('default');
            $table->text('dkim_private_key')->nullable();
            $table->text('dkim_public_key')->nullable();
            $table->text('dkim_dns_record')->nullable();
            
            // DMARC (Domain-based Message Authentication, Reporting & Conformance)
            $table->boolean('dmarc_enabled')->default(true);
            $table->enum('dmarc_policy', ['none', 'quarantine', 'reject'])->default('none');
            $table->string('dmarc_rua_email')->nullable(); // Aggregate reports email
            $table->string('dmarc_ruf_email')->nullable(); // Forensic reports email
            $table->integer('dmarc_percentage')->default(100);
            $table->text('dmarc_record')->nullable();
            
            $table->timestamps();

            // Foreign key constraint
            $table->foreign('domain_id')->references('id')->on('domains')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('email_authentication');
    }
};

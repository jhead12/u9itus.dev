<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 6.1 – KYC Document Upload
 *
 * Adds a column to track the path of the government-issued ID document
 * that users (voters and politicians) upload for identity verification.
 *
 * Stored on the `public` disk under `kyc/{user_id}/document.{ext}`.
 * The admin reviews this file via the KYC queue before approving/rejecting.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Path (relative to the `public` storage disk) of the uploaded ID doc.
            // e.g. "kyc/42/document.jpg"
            $table->string('kyc_document_path')->nullable()->after('kyc_rejection_reason');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('kyc_document_path');
        });
    }
};

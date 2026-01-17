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
        Schema::table('users', function (Blueprint $table) {
            $table->enum('user_type', ['advertiser', 'viewer', 'admin'])->after('email');
            $table->string('first_name')->after('name');
            $table->string('last_name')->after('first_name');
            $table->string('phone')->nullable()->after('email');
            $table->timestamp('phone_verified_at')->nullable()->after('phone');
            $table->enum('kyc_status', ['pending', 'approved', 'rejected'])->default('pending')->after('phone_verified_at');
            $table->string('street_address')->nullable()->after('kyc_status');
            $table->string('city')->nullable()->after('street_address');
            $table->string('state')->nullable()->after('city');
            $table->string('zip_code')->nullable()->after('state');
            $table->string('country')->nullable()->after('zip_code');
            $table->unsignedBigInteger('current_assignment_id')->nullable()->after('country');
            $table->boolean('is_available_for_assignment')->default(true)->after('current_assignment_id');
            $table->boolean('is_verified')->default(false)->after('is_available_for_assignment');
            $table->timestamp('last_assignment_at')->nullable()->after('is_verified');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'user_type',
                'first_name',
                'last_name',
                'phone',
                'phone_verified_at',
                'kyc_status',
                'street_address',
                'city',
                'state',
                'zip_code',
                'country',
                'current_assignment_id',
                'is_available_for_assignment',
                'is_verified',
                'last_assignment_at',
            ]);
        });
    }
};

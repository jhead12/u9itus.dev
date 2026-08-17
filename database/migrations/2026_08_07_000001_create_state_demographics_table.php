<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('state_demographics', function (Blueprint $table) {
            $table->id();
            $table->string('state', 2)->unique();
            $table->decimal('poverty_rate', 5, 2)->nullable();
            $table->unsignedSmallInteger('census_year');
            $table->string('source', 20)->default('acs5');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('state_demographics');
    }
};

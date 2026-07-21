<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('city_demographics', function (Blueprint $table) {
            $table->id();
            $table->string('state', 2)->index();
            $table->string('city_name', 120);
            $table->unsignedInteger('population')->nullable();
            $table->decimal('poverty_rate', 5, 2)->nullable();
            $table->decimal('pct_bachelors_or_higher', 5, 2)->nullable();
            $table->unsignedInteger('median_household_income')->nullable();
            $table->unsignedSmallInteger('census_year');
            $table->string('source', 20)->default('acs5');
            $table->timestamps();

            $table->unique(['state', 'city_name', 'census_year']);
            $table->index(['state', 'population']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('city_demographics');
    }
};

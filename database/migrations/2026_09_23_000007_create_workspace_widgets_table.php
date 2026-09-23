<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workspace_widgets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('widget_key', 64);
            $table->unsignedInteger('position')->default(0);
            $table->string('width', 8)->default('half'); // 'half' | 'full'
            $table->json('settings')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'widget_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workspace_widgets');
    }
};

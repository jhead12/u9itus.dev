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
        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('slug')->unique();

            // Polymorphic author: Citizen, Politician, or Voter (read-only voters are not authors by default).
            $table->string('author_type');
            $table->unsignedBigInteger('author_id');
            $table->index(['author_type', 'author_id']);

            $table->string('title');
            $table->string('subtitle')->nullable();
            $table->text('excerpt')->nullable();
            $table->longText('body')->nullable();
            $table->string('featured_image_url')->nullable();

            $table->string('status')->default('draft'); // draft, pending_approval, published, archived
            $table->timestamp('published_at')->nullable();
            $table->timestamp('archived_at')->nullable();

            $table->boolean('allow_comments')->default(true);

            // Promotion / advertising
            $table->boolean('is_promoted')->default(false);
            $table->timestamp('promoted_until')->nullable();
            $table->decimal('credit_spent', 10, 2)->default(0);

            // Optional geotagging for map pins
            $table->string('location_name')->nullable();
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->string('state', 2)->nullable();
            $table->string('city')->nullable();
            $table->string('zip')->nullable();

            // SEO
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->string('canonical_url')->nullable();

            $table->timestamps();

            $table->index(['status', 'published_at']);
            $table->index(['is_promoted', 'promoted_until']);
            $table->index(['latitude', 'longitude']);
            $table->index(['state', 'city']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('posts');
    }
};

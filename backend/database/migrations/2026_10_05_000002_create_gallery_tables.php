<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Domains / topics: E-commerce, Health, Finance ...
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80);
            $table->string('slug', 90)->unique();
            $table->string('icon', 16)->nullable();          // emoji
            $table->string('description', 255)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->string('name', 40);
            $table->string('slug', 50)->unique();
            $table->timestamps();
        });

        Schema::create('designs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->string('title', 120);
            $table->string('slug', 150)->unique();
            $table->text('description')->nullable();
            $table->enum('platform', ['web', 'mobile', 'tablet'])->index();
            $table->string('screen_type', 40)->index();     // landing, login, dashboard ...
            $table->string('image_path');
            $table->unsignedInteger('image_width')->nullable();
            $table->unsignedInteger('image_height')->nullable();
            $table->string('source_url', 500)->nullable();
            $table->json('palette')->nullable();             // [{hex, percent}]
            $table->string('color_families', 120)->nullable(); // "blue white" for color filtering
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending')->index();
            $table->string('rejection_reason', 255)->nullable();
            $table->unsignedInteger('views_count')->default(0);
            $table->unsignedInteger('likes_count')->default(0);
            $table->unsignedInteger('bookmarks_count')->default(0);
            $table->unsignedInteger('comments_count')->default(0);
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'approved_at']);
            $table->index(['status', 'likes_count']);
        });

        Schema::create('design_tag', function (Blueprint $table) {
            $table->foreignId('design_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained()->cascadeOnDelete();
            $table->primary(['design_id', 'tag_id']);
        });

        Schema::create('likes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('design_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'design_id']);
        });

        Schema::create('collections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 60);
            $table->timestamps();
            $table->unique(['user_id', 'name']);
        });

        Schema::create('bookmarks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('design_id')->constrained()->cascadeOnDelete();
            $table->foreignId('collection_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'design_id']);
        });

        Schema::create('comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('design_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('comments')->cascadeOnDelete();
            $table->text('body');
            $table->timestamps();
        });

        // AI / template generated UI mockups
        Schema::create('ui_generations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('prompt', 500);
            $table->enum('platform', ['web', 'mobile', 'tablet']);
            $table->string('screen_type', 40);
            $table->string('style', 40);
            $table->string('accent', 7)->nullable();
            $table->longText('html');
            $table->string('provider', 30);                  // anthropic | template
            $table->boolean('is_public')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ui_generations');
        Schema::dropIfExists('comments');
        Schema::dropIfExists('bookmarks');
        Schema::dropIfExists('collections');
        Schema::dropIfExists('likes');
        Schema::dropIfExists('design_tag');
        Schema::dropIfExists('designs');
        Schema::dropIfExists('tags');
        Schema::dropIfExists('categories');
    }
};

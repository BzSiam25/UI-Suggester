<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Everything the generator form offers lives here, so languages,
        // frameworks, screens and domains are data, not code. Rows with a
        // user_id are custom entries that user added from the form.
        Schema::create('generator_options', function (Blueprint $table) {
            $table->id();
            $table->string('type', 20);                       // language | framework | screen | domain
            $table->string('name', 80);
            $table->string('slug', 90);
            $table->string('parent', 90)->nullable();         // framework -> language slug
            $table->string('description', 255)->nullable();
            $table->json('meta')->nullable();                 // framework file layout and coding notes
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['type', 'user_id']);
            $table->unique(['type', 'slug', 'user_id']);
        });

        Schema::table('ui_generations', function (Blueprint $table) {
            $table->string('language', 90)->nullable()->after('accent');
            $table->string('framework', 90)->nullable()->after('language');
            $table->json('screens')->nullable()->after('framework');   // [{slug, name}]
            $table->json('domains')->nullable()->after('screens');     // [{slug, name}]
            $table->longText('previews')->nullable()->after('html');   // JSON [{slug, name, html}]
            $table->longText('files')->nullable()->after('previews');  // JSON [{path, language, content, screen}]
            $table->string('status', 20)->default('done')->after('provider'); // pending | running | done | failed
            $table->json('progress')->nullable()->after('status');
            $table->text('error')->nullable()->after('progress');
            $table->boolean('use_research')->default(true)->after('error');
        });

        Schema::table('ui_generations', function (Blueprint $table) {
            $table->text('prompt')->change();
            $table->longText('html')->nullable()->change();
            $table->string('screen_type', 90)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('ui_generations', function (Blueprint $table) {
            $table->dropColumn(['language', 'framework', 'screens', 'domains', 'previews', 'files', 'status', 'progress', 'error', 'use_research']);
        });
        Schema::dropIfExists('generator_options');
    }
};

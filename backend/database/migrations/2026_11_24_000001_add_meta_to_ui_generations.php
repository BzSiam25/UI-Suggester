<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ui_generations', function (Blueprint $table) {
            // Design direction, layout recipe and the web references the
            // mockup was inspired by, so the reasoning survives a reload.
            $table->json('meta')->nullable()->after('provider');
        });
    }

    public function down(): void
    {
        Schema::table('ui_generations', function (Blueprint $table) {
            $table->dropColumn('meta');
        });
    }
};

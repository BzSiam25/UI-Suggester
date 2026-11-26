<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('username', 30)->nullable()->unique()->after('name');
            $table->string('avatar_path')->nullable()->after('bio');
            $table->string('headline', 120)->nullable()->after('avatar_path');
            $table->string('location', 80)->nullable()->after('headline');
            $table->string('website', 190)->nullable()->after('location');
            $table->json('skills')->nullable()->after('website');
            $table->json('links')->nullable()->after('skills');      // github, linkedin, dribbble, behance, x
            $table->boolean('is_profile_public')->default(true)->after('links');
        });

        // Give every existing account a handle for its public profile URL.
        $taken = [];
        foreach (DB::table('users')->orderBy('id')->get(['id', 'name']) as $user) {
            $base = Str::limit(preg_replace('/[^a-z0-9]/', '', Str::lower(Str::ascii($user->name))) ?: 'user', 20, '');
            $handle = $base;
            for ($i = 2; in_array($handle, $taken, true); $i++) {
                $handle = $base.$i;
            }
            $taken[] = $handle;
            DB::table('users')->where('id', $user->id)->update(['username' => $handle]);
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['username']);
            $table->dropColumn(['username', 'avatar_path', 'headline', 'location', 'website', 'skills', 'links', 'is_profile_public']);
        });
    }
};

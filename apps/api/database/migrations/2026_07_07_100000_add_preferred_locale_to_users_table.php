<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('preferred_locale', 8)->default('en');
        });

        DB::table('users')
            ->whereNull('preferred_locale')
            ->orWhereNotIn('preferred_locale', ['en', 'id', 'es'])
            ->update(['preferred_locale' => 'en']);

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE users ADD CONSTRAINT users_preferred_locale_allowed CHECK (preferred_locale IN ('en', 'id', 'es'))");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_preferred_locale_allowed');
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('preferred_locale');
        });
    }
};

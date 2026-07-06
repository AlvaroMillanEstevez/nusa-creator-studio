<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('generations', function (Blueprint $table) {
            $table->renameColumn('request_payload', 'input_payload');
            $table->renameColumn('result_payload', 'output_payload');
        });

        DB::table('generations')
            ->where('status', 'pending')
            ->update(['status' => 'queued']);

        Schema::table('generations', function (Blueprint $table) {
            $table->string('status')->default('queued')->change();
        });
    }

    public function down(): void
    {
        Schema::table('generations', function (Blueprint $table) {
            $table->string('status')->default('pending')->change();
        });

        Schema::table('generations', function (Blueprint $table) {
            $table->renameColumn('input_payload', 'request_payload');
            $table->renameColumn('output_payload', 'result_payload');
        });
    }
};

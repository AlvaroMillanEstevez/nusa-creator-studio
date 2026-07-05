<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Normalize the two development-only legacy direction labels.
        DB::table('credit_transactions')->where('type', 'credit')->update(['type' => 'admin_grant']);
        DB::table('credit_transactions')->where('type', 'debit')->update(['type' => 'usage']);

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            Schema::getConnection()->statement(
                "ALTER TABLE credit_transactions ADD CONSTRAINT credit_transactions_type_valid CHECK (type IN ('purchase', 'plan_renewal', 'admin_grant', 'refund', 'usage', 'expiration'))"
            );
        }
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            Schema::getConnection()->statement(
                'ALTER TABLE credit_transactions DROP CONSTRAINT IF EXISTS credit_transactions_type_valid'
            );
        }
    }
};

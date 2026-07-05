<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * @var array<int, array{table: string, name: string, check: string}>
     */
    private array $constraints = [
        [
            'table' => 'generations',
            'name' => 'generations_credits_charged_non_negative',
            'check' => 'credits_charged >= 0',
        ],
        [
            'table' => 'credit_transactions',
            'name' => 'credit_transactions_amount_non_negative',
            'check' => 'amount >= 0',
        ],
        [
            'table' => 'credit_transactions',
            'name' => 'credit_transactions_balance_non_negative',
            'check' => 'balance_after >= 0',
        ],
    ];

    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'pgsql') {
            return;
        }

        foreach ($this->constraints as $constraint) {
            $exists = Schema::getConnection()
                ->table('pg_constraint')
                ->where('conname', $constraint['name'])
                ->exists();

            if (! $exists) {
                Schema::getConnection()->statement(sprintf(
                    'ALTER TABLE %s ADD CONSTRAINT %s CHECK (%s)',
                    $constraint['table'],
                    $constraint['name'],
                    $constraint['check'],
                ));
            }
        }
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'pgsql') {
            return;
        }

        foreach (array_reverse($this->constraints) as $constraint) {
            Schema::getConnection()->statement(sprintf(
                'ALTER TABLE %s DROP CONSTRAINT IF EXISTS %s',
                $constraint['table'],
                $constraint['name'],
            ));
        }
    }
};

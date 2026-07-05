<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credit_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('generation_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type');
            $table->unsignedInteger('amount');
            $table->unsignedInteger('balance_after');
            $table->string('reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['user_id', 'type']);
        });

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            Schema::getConnection()->statement(
                'ALTER TABLE credit_transactions ADD CONSTRAINT credit_transactions_amount_non_negative CHECK (amount >= 0)'
            );
            Schema::getConnection()->statement(
                'ALTER TABLE credit_transactions ADD CONSTRAINT credit_transactions_balance_non_negative CHECK (balance_after >= 0)'
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_transactions');
    }
};

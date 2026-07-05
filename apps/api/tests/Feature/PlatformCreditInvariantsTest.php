<?php

namespace Tests\Feature;

use App\Enums\CreditTransactionType;
use App\Models\CreditTransaction;
use App\Models\Generation;
use App\Models\User;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlatformCreditInvariantsTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_credit_balance_defaults_to_twenty_and_cannot_be_negative(): void
    {
        $user = User::factory()->create();

        $this->assertSame(20, $user->credits_balance);

        $user->credits_balance = -1;

        $this->expectException(DomainException::class);
        $user->save();
    }

    public function test_credit_transaction_amount_cannot_be_negative(): void
    {
        $transaction = CreditTransaction::factory()
            ->for(User::factory())
            ->make(['amount' => -1]);

        $this->expectException(DomainException::class);
        $transaction->save();
    }

    public function test_credit_transaction_balance_after_cannot_be_negative(): void
    {
        $transaction = CreditTransaction::factory()
            ->for(User::factory())
            ->make(['balance_after' => -1]);

        $this->expectException(DomainException::class);
        $transaction->save();
    }

    public function test_generation_credits_charged_cannot_be_negative(): void
    {
        $generation = Generation::factory()
            ->for(User::factory())
            ->make(['credits_charged' => -1]);

        $this->expectException(DomainException::class);
        $generation->save();
    }

    public function test_usage_transaction_stores_a_positive_amount(): void
    {
        $transaction = CreditTransaction::factory()->create([
            'type' => CreditTransactionType::Usage,
            'amount' => 5,
            'balance_after' => 15,
        ]);

        $this->assertSame(CreditTransactionType::Usage, $transaction->type);
        $this->assertTrue($transaction->type->spendsCredits());
        $this->assertSame(5, $transaction->amount);
        $this->assertDatabaseHas('credit_transactions', [
            'id' => $transaction->id,
            'type' => 'usage',
            'amount' => 5,
            'balance_after' => 15,
        ]);
    }

    public function test_transaction_types_define_the_credit_direction(): void
    {
        foreach ([
            CreditTransactionType::Purchase,
            CreditTransactionType::PlanRenewal,
            CreditTransactionType::AdminGrant,
            CreditTransactionType::Refund,
        ] as $type) {
            $this->assertTrue($type->addsCredits());
            $this->assertFalse($type->spendsCredits());
        }

        foreach ([CreditTransactionType::Usage, CreditTransactionType::Expiration] as $type) {
            $this->assertFalse($type->addsCredits());
            $this->assertTrue($type->spendsCredits());
        }
    }

    public function test_credit_documentation_uses_internal_platform_terminology(): void
    {
        $documentation = file_get_contents(base_path('../../docs/api-domain.md'));

        $this->assertIsString($documentation);

        $normalizedDocumentation = strtolower($documentation);

        $this->assertStringContainsString('internal platform credits', $normalizedDocumentation);
        $this->assertStringContainsString('not payment cards', $normalizedDocumentation);
        $this->assertStringNotContainsString('negative credits', $normalizedDocumentation);
    }
}

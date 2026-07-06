<?php

namespace Database\Seeders;

use App\Models\SubscriptionPlan;
use Illuminate\Database\Seeder;

class SubscriptionPlanSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->plans() as $plan) {
            SubscriptionPlan::query()->firstOrCreate(
                ['key' => $plan['key']],
                $plan,
            );
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function plans(): array
    {
        return [
            [
                'key' => 'free',
                'name' => 'Free',
                'short_description' => 'Explore the creator studio with a practical monthly credit allowance.',
                'description' => 'A simple starting point for testing creator workflows and saving project history.',
                'price_amount' => 0,
                'currency' => 'IDR',
                'billing_interval' => 'month',
                'monthly_credits' => 20,
                'is_public' => true,
                'is_featured' => false,
                'is_active' => true,
                'is_internal' => false,
                'sort_order' => 10,
                'cta_label' => 'Start free',
                'features' => [
                    '20 monthly credits',
                    'Access to mock creator tools',
                    'Saved project history',
                    'Basic dashboard',
                ],
            ],
            [
                'key' => 'starter',
                'name' => 'Starter',
                'short_description' => 'More room to publish consistently and explore new creator workflows.',
                'description' => 'For creators moving beyond occasional experiments into regular content production.',
                'price_amount' => 99000,
                'currency' => 'IDR',
                'billing_interval' => 'month',
                'monthly_credits' => 100,
                'is_public' => true,
                'is_featured' => false,
                'is_active' => true,
                'is_internal' => false,
                'sort_order' => 20,
                'cta_label' => 'Choose Starter',
                'features' => [
                    '100 monthly credits',
                    'More content generations',
                    'Saved generation history',
                    'Priority access to new mock tools',
                ],
            ],
            [
                'key' => 'creator',
                'name' => 'Creator',
                'short_description' => 'A focused plan for active affiliate creators producing every week.',
                'description' => 'Balanced monthly credits for campaign variations and repeatable production workflows.',
                'price_amount' => 199000,
                'currency' => 'IDR',
                'billing_interval' => 'month',
                'monthly_credits' => 300,
                'is_public' => true,
                'is_featured' => true,
                'is_active' => true,
                'is_internal' => false,
                'sort_order' => 30,
                'cta_label' => 'Choose Creator',
                'features' => [
                    '300 monthly credits',
                    'Designed for active affiliate creators',
                    'More campaign variations',
                    'Future voice/video tools readiness',
                ],
            ],
            [
                'key' => 'pro',
                'name' => 'Pro',
                'short_description' => 'High-volume capacity for teams and demanding creator schedules.',
                'description' => 'Placeholder capacity for heavier workflows while real provider costs are evaluated.',
                'price_amount' => 399000,
                'currency' => 'IDR',
                'billing_interval' => 'month',
                'monthly_credits' => 1000,
                'is_public' => true,
                'is_featured' => false,
                'is_active' => true,
                'is_internal' => false,
                'sort_order' => 40,
                'cta_label' => 'Choose Pro',
                'features' => [
                    '1000 monthly credits',
                    'Higher usage limits',
                    'Best for creator teams and heavy usage',
                    'Future premium AI workflows readiness',
                ],
            ],
            [
                'key' => 'internal_unlimited',
                'name' => 'Internal Unlimited',
                'short_description' => 'Private access for internal creators and administrators.',
                'description' => 'Internal testing access that does not consume platform credits.',
                'price_amount' => null,
                'currency' => 'IDR',
                'billing_interval' => 'internal',
                'monthly_credits' => 0,
                'is_public' => false,
                'is_featured' => false,
                'is_active' => true,
                'is_internal' => true,
                'sort_order' => 100,
                'cta_label' => null,
                'features' => [
                    'Internal unlimited access',
                    'Generations do not consume credits',
                    'For internal creators and administrators',
                ],
            ],
        ];
    }
}

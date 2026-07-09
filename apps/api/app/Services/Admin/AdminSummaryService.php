<?php

namespace App\Services\Admin;

use App\Enums\CreditTransactionType;
use App\Models\CreditTransaction;
use App\Models\Generation;
use App\Models\Project;
use App\Models\User;

class AdminSummaryService
{
    /**
     * @return array<string, mixed>
     */
    public function get(): array
    {
        $usersByRole = User::query()
            ->selectRaw('role, COUNT(*) AS aggregate')
            ->groupBy('role')
            ->pluck('aggregate', 'role');

        $projectsByType = Project::query()
            ->selectRaw('type, COUNT(*) AS aggregate')
            ->groupBy('type')
            ->pluck('aggregate', 'type');

        $generationsByJobType = Generation::query()
            ->selectRaw('job_type, COUNT(*) AS aggregate')
            ->groupBy('job_type')
            ->pluck('aggregate', 'job_type');

        return [
            'total_users' => (int) $usersByRole->sum(),
            'total_admins' => (int) ($usersByRole['admin'] ?? 0),
            'total_internal_creators' => (int) ($usersByRole['internal_creator'] ?? 0),
            'total_subscribers' => (int) ($usersByRole['subscriber'] ?? 0),
            'total_free_users' => (int) ($usersByRole['free_user'] ?? 0),
            'total_projects' => Project::query()->count(),
            'total_generations' => Generation::query()->count(),
            'total_completed_generations' => Generation::query()->where('status', 'completed')->count(),
            'total_failed_generations' => Generation::query()->where('status', 'failed')->count(),
            'total_credits_spent' => (int) CreditTransaction::query()
                ->where('type', CreditTransactionType::Usage->value)
                ->where('amount', '>', 0)
                ->sum('amount'),
            'total_unlimited_generations' => Generation::query()
                ->whereHas('creditTransactions', function ($query): void {
                    $query
                        ->where('type', CreditTransactionType::Usage->value)
                        ->where('amount', 0)
                        ->where('metadata->unlimited_access', true);
                })
                ->count(),
            'projects_by_type' => [
                'workspace' => (int) ($projectsByType['workspace'] ?? 0),
                'affiliate_campaign' => (int) ($projectsByType['affiliate_campaign'] ?? 0),
                'video_enhancer' => (int) ($projectsByType['video_enhancer'] ?? 0),
                'product_to_video' => (int) ($projectsByType['product_to_video'] ?? 0),
                'ai_voiceover' => (int) ($projectsByType['ai_voiceover'] ?? 0),
                'fashion_try_on' => (int) ($projectsByType['fashion_try_on'] ?? 0),
                'motion_ai_video' => (int) ($projectsByType['motion_ai_video'] ?? 0),
            ],
            'generations_by_job_type' => [
                'generate_affiliate_campaign' => (int) ($generationsByJobType['generate_affiliate_campaign'] ?? 0),
                'generate_video_enhancer' => (int) ($generationsByJobType['generate_video_enhancer'] ?? 0),
                'generate_product_video_script' => (int) ($generationsByJobType['generate_product_video_script'] ?? 0),
                'generate_ai_voiceover' => (int) ($generationsByJobType['generate_ai_voiceover'] ?? 0),
                'generate_fashion_try_on' => (int) ($generationsByJobType['generate_fashion_try_on'] ?? 0),
                'generate_motion_ai_video' => (int) ($generationsByJobType['generate_motion_ai_video'] ?? 0),
            ],
        ];
    }
}

<?php

namespace App\Services;

use App\Enums\CreditTransactionType;
use App\Models\User;

class DashboardSummaryService
{
    /**
     * @return array<string, mixed>
     */
    public function forUser(User $user): array
    {
        $projectStats = $user->projects()
            ->selectRaw('COUNT(*) AS total_projects')
            ->selectRaw("SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END) AS draft_projects")
            ->selectRaw("SUM(CASE WHEN type = 'workspace' THEN 1 ELSE 0 END) AS workspace_projects")
            ->selectRaw("SUM(CASE WHEN type = 'affiliate_campaign' THEN 1 ELSE 0 END) AS affiliate_campaign_projects")
            ->selectRaw("SUM(CASE WHEN type = 'video_enhancer' THEN 1 ELSE 0 END) AS video_enhancer_projects")
            ->selectRaw("SUM(CASE WHEN type = 'product_to_video' THEN 1 ELSE 0 END) AS product_to_video_projects")
            ->selectRaw("SUM(CASE WHEN type = 'fashion_try_on' THEN 1 ELSE 0 END) AS fashion_try_on_projects")
            ->selectRaw("SUM(CASE WHEN type = 'motion_ai_video' THEN 1 ELSE 0 END) AS motion_ai_video_projects")
            ->firstOrFail();

        $generationStats = $user->generations()
            ->selectRaw('COUNT(*) AS total_generations')
            ->selectRaw("SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) AS completed_generations")
            ->selectRaw("SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) AS failed_generations")
            ->firstOrFail();

        $recentProjects = $user->projects()
            ->withCount('generations')
            ->with([
                'latestGeneration' => function ($query): void {
                    $query->select([
                        'generations.id',
                        'generations.project_id',
                        'generations.status',
                        'generations.created_at',
                    ]);
                },
            ])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(5)
            ->get()
            ->map(function ($project): array {
                return [
                    'id' => $project->id,
                    'title' => $project->title,
                    'type' => $project->type,
                    'status' => $project->status,
                    'created_at' => $project->created_at?->toISOString(),
                    'updated_at' => $project->updated_at?->toISOString(),
                    'generations_count' => (int) $project->generations_count,
                    'latest_generation_status' => $project->latestGeneration?->status,
                    'latest_generation_created_at' => $project->latestGeneration?->created_at?->toISOString(),
                ];
            })
            ->values();

        $recentGenerations = $user->generations()
            ->with('project:id,title,type')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(5)
            ->get()
            ->map(function ($generation): array {
                return [
                    'id' => $generation->id,
                    'project_id' => $generation->project_id,
                    'project_title' => $generation->project?->title,
                    'project_type' => $generation->project?->type,
                    'job_type' => $generation->job_type,
                    'provider' => $generation->provider,
                    'status' => $generation->status,
                    'credits_charged' => $generation->credits_charged,
                    'cost_usd' => $generation->cost_usd,
                    'created_at' => $generation->created_at?->toISOString(),
                    'finished_at' => $generation->finished_at?->toISOString(),
                ];
            })
            ->values();

        return [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'plan_key' => $user->plan_key,
                'is_unlimited' => $user->is_unlimited,
                'monthly_credit_allowance' => $user->monthly_credit_allowance,
                'credits_balance' => $user->credits_balance,
            ],
            'access' => [
                'label' => $this->accessLabel($user),
                'is_unlimited' => $user->is_unlimited,
                'monthly_credit_allowance' => $user->monthly_credit_allowance,
                'credits_balance' => $user->credits_balance,
                'can_generate_without_credits' => $user->hasUnlimitedAccess(),
            ],
            'stats' => [
                'total_projects' => (int) $projectStats->total_projects,
                'total_generations' => (int) $generationStats->total_generations,
                'completed_generations' => (int) $generationStats->completed_generations,
                'failed_generations' => (int) $generationStats->failed_generations,
                'draft_projects' => (int) $projectStats->draft_projects,
                'workspace_projects' => (int) $projectStats->workspace_projects,
                'total_credits_spent' => (int) $user->creditTransactions()
                    ->where('type', CreditTransactionType::Usage->value)
                    ->where('amount', '>', 0)
                    ->sum('amount'),
                'affiliate_campaign_projects' => (int) $projectStats->affiliate_campaign_projects,
                'video_enhancer_projects' => (int) $projectStats->video_enhancer_projects,
                'product_to_video_projects' => (int) $projectStats->product_to_video_projects,
                'fashion_try_on_projects' => (int) $projectStats->fashion_try_on_projects,
                'motion_ai_video_projects' => (int) $projectStats->motion_ai_video_projects,
            ],
            'recent_projects' => $recentProjects,
            'recent_generations' => $recentGenerations,
        ];
    }

    private function accessLabel(User $user): string
    {
        if ($user->hasUnlimitedAccess()) {
            return 'Unlimited internal access';
        }

        return match ($user->plan_key) {
            'starter' => 'Starter plan',
            'creator' => 'Creator plan',
            'pro' => 'Pro plan',
            'internal_unlimited' => 'Internal plan',
            default => 'Free plan',
        };
    }
}

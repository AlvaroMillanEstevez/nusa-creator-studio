<?php

namespace App\Services\Admin;

use App\Enums\CreditTransactionType;
use App\Models\User;
use App\Services\PlanService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AdminUserService
{
    public function __construct(private readonly PlanService $plans) {}

    /**
     * @return array<string, mixed>
     */
    public function detail(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'plan_key' => $user->plan_key,
            'is_unlimited' => $user->is_unlimited,
            'monthly_credit_allowance' => $user->monthly_credit_allowance,
            'credits_balance' => $user->credits_balance,
            'created_at' => $user->created_at?->toISOString(),
            'updated_at' => $user->updated_at?->toISOString(),
            'stats' => [
                'projects_count' => $user->projects()->count(),
                'generations_count' => $user->generations()->count(),
                'completed_generations_count' => $user->generations()
                    ->where('status', 'completed')
                    ->count(),
                'failed_generations_count' => $user->generations()
                    ->where('status', 'failed')
                    ->count(),
                'credits_spent' => (int) $user->creditTransactions()
                    ->where('type', CreditTransactionType::Usage->value)
                    ->where('amount', '>', 0)
                    ->sum('amount'),
            ],
            'recent_projects' => $user->projects()
                ->latest('created_at')
                ->latest('id')
                ->limit(5)
                ->get()
                ->map(fn ($project): array => [
                    'id' => $project->id,
                    'title' => $project->title,
                    'type' => $project->type,
                    'status' => $project->status,
                    'created_at' => $project->created_at?->toISOString(),
                ])
                ->values(),
            'recent_generations' => $user->generations()
                ->with('project:id,title')
                ->latest('created_at')
                ->latest('id')
                ->limit(5)
                ->get()
                ->map(fn ($generation): array => [
                    'id' => $generation->id,
                    'project_id' => $generation->project_id,
                    'project_title' => $generation->project?->title,
                    'job_type' => $generation->job_type,
                    'provider' => $generation->provider,
                    'status' => $generation->status,
                    'credits_charged' => $generation->credits_charged,
                    'created_at' => $generation->created_at?->toISOString(),
                ])
                ->values(),
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function updateAccess(User $user, array $attributes): User
    {
        if (
            array_key_exists('plan_key', $attributes)
            && ! array_key_exists('monthly_credit_allowance', $attributes)
        ) {
            $attributes['monthly_credit_allowance'] = $this->plans
                ->findByKeyOrFail($attributes['plan_key'])
                ->monthly_credits;
        }

        return DB::transaction(function () use ($user, $attributes): User {
            $adminIds = User::query()
                ->where('role', 'admin')
                ->orderBy('id')
                ->lockForUpdate()
                ->pluck('id');

            $lockedUser = User::query()
                ->whereKey($user->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $wouldDemoteAdmin = $lockedUser->isAdmin()
                && array_key_exists('role', $attributes)
                && $attributes['role'] !== 'admin';

            if ($wouldDemoteAdmin && $adminIds->count() <= 1) {
                throw ValidationException::withMessages([
                    'role' => ['The last admin account cannot be demoted.'],
                ]);
            }

            $lockedUser->fill($attributes)->save();

            return $lockedUser->refresh();
        });
    }
}

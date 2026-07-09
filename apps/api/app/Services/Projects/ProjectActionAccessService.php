<?php

namespace App\Services\Projects;

use App\Models\User;

class ProjectActionAccessService
{
    /**
     * @return array{user_has_access: bool, upgrade_required: bool, access_label: ?string, locked_reason: ?string}
     */
    public function metadataFor(User $user, string $serviceTier): array
    {
        $hasAccess = $this->userHasAccess($user, $serviceTier);

        return [
            'user_has_access' => $hasAccess,
            'upgrade_required' => ! $hasAccess,
            'access_label' => $hasAccess ? $this->accessLabel($user, $serviceTier) : null,
            'locked_reason' => $hasAccess ? null : $this->lockedReason($serviceTier),
        ];
    }

    public function userHasAccess(User $user, string $serviceTier): bool
    {
        if ($this->hasInternalAccess($user)) {
            return true;
        }

        return match ($serviceTier) {
            'basic' => true,
            'advanced' => in_array($user->plan_key, ['starter', 'creator', 'pro'], true),
            'premium' => in_array($user->plan_key, ['creator', 'pro'], true),
            default => false,
        };
    }

    public function hasInternalAccess(User $user): bool
    {
        return $user->isAdmin()
            || $user->isInternalCreator()
            || $user->hasUnlimitedAccess()
            || $user->plan_key === 'internal_unlimited';
    }

    private function accessLabel(User $user, string $serviceTier): string
    {
        if ($this->hasInternalAccess($user)) {
            return 'Internal/unlimited access';
        }

        return match ($serviceTier) {
            'basic' => 'Included in every plan',
            'advanced' => 'Starter plan or higher',
            'premium' => 'Creator plan or higher',
            default => $user->planLabel().' plan',
        };
    }

    private function lockedReason(string $serviceTier): string
    {
        return match ($serviceTier) {
            'advanced' => 'Advanced actions require Starter, Creator or Pro access.',
            'premium' => 'Premium actions require Creator or Pro access.',
            default => 'Your current plan does not include this action.',
        };
    }
}

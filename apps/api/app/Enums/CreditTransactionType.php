<?php

namespace App\Enums;

enum CreditTransactionType: string
{
    case Purchase = 'purchase';
    case PlanRenewal = 'plan_renewal';
    case AdminGrant = 'admin_grant';
    case Refund = 'refund';
    case Usage = 'usage';
    case Expiration = 'expiration';

    public function addsCredits(): bool
    {
        return match ($this) {
            self::Purchase,
            self::PlanRenewal,
            self::AdminGrant,
            self::Refund => true,
            self::Usage,
            self::Expiration => false,
        };
    }

    public function spendsCredits(): bool
    {
        return ! $this->addsCredits();
    }
}

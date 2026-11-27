<?php

declare(strict_types=1);

namespace Modules\Accounting\Enums;

enum AccountCategory: string
{
    case Bank = 'bank';
    case CreditCard = 'credit_card';
    case UndepositedCash = 'undeposited_cash';

    public function coreType(): string
    {
        return $this === self::CreditCard ? 'liability' : 'asset';
    }

    public function detailCodes(): array
    {
        return match ($this) {
            self::Bank => ['bank-accounts', 'bank-account', 'cash-in-bank', 'cash-in-banks'],
            self::CreditCard => ['credit-card', 'credit-cards', 'credit-card-payable', 'credit-cards-payable'],
            self::UndepositedCash => ['undeposited-cash', 'pending-deposits', 'undeposited-funds', 'undeposited-checks', 'undeposited-cheques'],
        };
    }

    public function legacyNames(): array
    {
        return array_map(static fn (string $code) => str_replace('-', ' ', $code), $this->detailCodes());
    }
}

<?php

namespace App\Services\Payouts;

use App\Models\LedgerAccount;
use App\Models\Society;
use App\Services\Accounting\SocietyAssetAccounts;

final class CurrentSocietyCashBalances
{
    public function __construct(
        private readonly SocietyAssetAccounts $assets
    ) {}

    public function build(Society $society): array
    {
        $codes = [
            'cash_cents' => 'CASH_ON_HAND',
            'bank_cents' => 'BANK_ACCOUNT',
            'mobile_money_cents' => 'MOBILE_MONEY',
        ];

        $result = [];

        foreach ($codes as $key => $code) {
            $account = LedgerAccount::query()
                ->where('society_id', $society->id)
                ->where('code', $code)
                ->first();

            $result[$key] = $account
                ? $this->assets->balanceCents($account)
                : 0;
        }

        $result['total_cents'] =
            $result['cash_cents']
            + $result['bank_cents']
            + $result['mobile_money_cents'];

        return $result;
    }
}

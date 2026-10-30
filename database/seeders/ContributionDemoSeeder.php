<?php

namespace Database\Seeders;

use App\Models\ContributionSetting;
use App\Models\Member;
use App\Models\Payment;
use App\Models\User;
use App\Services\ContributionLedger;
use Illuminate\Database\Seeder;

/**
 * Demo data for the Contributions module: two yearly settings, and 5 members covering
 * every payment pattern (on time, late, partial, several months unpaid, paid in advance).
 */
class ContributionDemoSeeder extends Seeder
{
    public function run(): void
    {
        ContributionSetting::create([
            'name' => '2025',
            'start_date' => '2025-01-01',
            'end_date' => '2025-12-31',
            'monthly_amount' => 40000,
            'penalty_percent' => 10,
        ]);

        ContributionSetting::create([
            'name' => '2026',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'monthly_amount' => 50000,
            'penalty_percent' => 10,
        ]);

        $recordedBy = User::first()?->id;

        $asha = $this->makeMember('Asha Mwalimu', '255700000001', '2026-07-15');
        $this->pay($asha, 50000, '2026-07-05', $recordedBy);
        $this->pay($asha, 50000, '2026-08-03', $recordedBy);
        $this->pay($asha, 50000, '2026-09-02', $recordedBy);

        $baraka = $this->makeMember('Baraka Juma', '255700000002', '2026-06-15');
        $this->pay($baraka, 50000, '2026-06-10', $recordedBy);
        // July missed entirely during July: one penalty will be charged on 2026-08-01
        $this->pay($baraka, 55000, '2026-08-20', $recordedBy); // clears July's penalty + July's contribution
        $this->pay($baraka, 50000, '2026-09-15', $recordedBy);

        $chiku = $this->makeMember('Chiku Said', '255700000003', '2026-08-15');
        $this->pay($chiku, 20000, '2026-08-20', $recordedBy); // partial: 30,000 stays outstanding

        $this->makeMember('Dickson Mushi', '255700000004', '2026-06-15');
        // No payments at all: several months unpaid, accruing a penalty at every month-end that passes.

        $fatma = $this->makeMember('Fatma Rajabu', '255700000005', '2026-09-15');
        $this->pay($fatma, 150000, '2026-09-20', $recordedBy); // covers Sep + Oct, 50,000 left over as credit

        app(ContributionLedger::class)->rebuildAll();
    }

    private function makeMember(string $name, string $phone, string $createdAt): Member
    {
        $member = Member::create([
            'name' => $name,
            'phone' => $phone,
            'email' => null,
        ]);
        $member->created_at = $createdAt;
        $member->save();

        return $member;
    }

    private function pay(Member $member, float $amount, string $paidOn, ?int $recordedBy): Payment
    {
        return Payment::create([
            'member_id' => $member->id,
            'amount' => $amount,
            'paid_on' => $paidOn,
            'recorded_by' => $recordedBy,
        ]);
    }
}

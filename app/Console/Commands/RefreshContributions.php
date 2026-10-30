<?php

namespace App\Console\Commands;

use App\Services\ContributionLedger;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('contributions:refresh')]
#[Description('Rebuild every member\'s contribution dues, penalties and allocations')]
class RefreshContributions extends Command
{
    public function handle(ContributionLedger $ledger): int
    {
        $this->components->info('Refreshing contributions for all members...');

        $ledger->rebuildAll();

        $this->components->info('Done.');

        return self::SUCCESS;
    }
}

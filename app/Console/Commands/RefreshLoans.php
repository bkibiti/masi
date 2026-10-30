<?php

namespace App\Console\Commands;

use App\Services\LoanLedger;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('loans:refresh')]
#[Description('Rebuild every disbursed loan\'s installments paid, penalties and status')]
class RefreshLoans extends Command
{
    public function handle(LoanLedger $ledger): int
    {
        $this->components->info('Refreshing loans...');

        $ledger->rebuildAll();

        $this->components->info('Done.');

        return self::SUCCESS;
    }
}

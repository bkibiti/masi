<?php

namespace App\Console\Commands;

use App\Models\Member;
use App\Services\PenaltyPaymentSplitter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SplitFinesFromPayments extends Command
{
    protected $signature = 'contributions:split-fines {--member= : Only this member ID} {--dry-run : Show the splits without saving}';

    protected $description = 'Apply "fines are paid first": split monthly-fee payments so they settle unpaid fines before the monthly fee';

    public function handle(PenaltyPaymentSplitter $splitter): int
    {
        $members = Member::query()->when($this->option('member'), fn ($q, $id) => $q->whereKey($id))->orderBy('id')->get();
        $rows = [];

        DB::beginTransaction();
        foreach ($members as $member) {
            foreach ($splitter->splitForMember($member) as $s) {
                $rows[] = [$s['member'], $s['paid_on'], $s['reference'], number_format($s['original']), number_format($s['fine']), number_format($s['fee'])];
            }
        }
        $this->option('dry-run') ? DB::rollBack() : DB::commit();

        $this->table(['Member', 'Paid on', 'Reference', 'Payment', 'To fines', 'To monthly fee'], $rows);
        $this->components->info(count($rows).' split(s) '.($this->option('dry-run') ? 'found (dry run, nothing saved).' : 'saved.'));

        return self::SUCCESS;
    }
}

<?php

namespace App\Console\Commands;

use App\Models\Member;
use App\Models\Payment;
use App\Models\User;
use App\Services\ContributionLedger;
use Carbon\Carbon;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Imports the historical M-Koba transactions for MASI.
 *
 * Reads database/import/masi_members.csv and database/import/masi_payments.csv.
 * Safe to run more than once: members are matched by name, payments by reference.
 */
#[Signature('masi:import-transactions
    {--dry-run : Show what would be imported without saving anything}
    {--joined= : Joining date (YYYY-MM-DD) to use for ALL members; dues start from this month}
    {--purge-demo : Delete the 5 demo members (and their payments) created by ContributionDemoSeeder}
    {--no-rebuild : Skip rebuilding the contribution ledger after import}')]
#[Description('Import MASI members and historical M-Koba payments from the CSV files in database/import')]
class ImportMasiTransactions extends Command
{
    private const DEMO_PHONES = ['255700000001', '255700000002', '255700000003', '255700000004', '255700000005'];

    public function handle(ContributionLedger $ledger): int
    {
        $dir = database_path('import');
        $members = $this->readCsv($dir . DIRECTORY_SEPARATOR . 'masi_members.csv');
        $payments = $this->readCsv($dir . DIRECTORY_SEPARATOR . 'masi_payments.csv');

        if ($members === null || $payments === null) {
            return self::FAILURE;
        }

        $joinedOverride = $this->option('joined');
        if ($joinedOverride && ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $joinedOverride)) {
            $this->components->error('--joined must be a date in the format YYYY-MM-DD.');

            return self::FAILURE;
        }

        // Every payment must point to a member listed in masi_members.csv.
        $known = collect($members)->mapWithKeys(fn ($m) => [$this->key($m['name']) => true]);
        $unknown = collect($payments)->pluck('member_name')->unique()
            ->reject(fn ($name) => isset($known[$this->key($name)]));
        if ($unknown->isNotEmpty()) {
            $this->components->error('Payments reference members not in masi_members.csv: ' . $unknown->implode(', '));

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $recordedBy = User::query()->orderBy('id')->value('id');
        $summary = ['members_created' => 0, 'members_existing' => 0, 'payments_created' => 0, 'payments_skipped' => 0];

        $work = function () use ($members, $payments, $joinedOverride, $recordedBy, $dryRun, &$summary) {
            if ($this->option('purge-demo')) {
                $demo = Member::query()->whereIn('phone', self::DEMO_PHONES)->get();
                $this->components->info("Purging {$demo->count()} demo member(s).");
                foreach ($demo as $member) {
                    $member->payments()->withTrashed()->forceDelete();
                    $member->delete();
                }
            }

            $byName = [];
            foreach ($members as $row) {
                $name = trim($row['name']);
                $member = Member::query()->whereRaw('LOWER(TRIM(name)) = ?', [$this->key($name)])->first();

                if ($member) {
                    $summary['members_existing']++;
                } else {
                    $joined = $joinedOverride ?: ($row['joined_on'] ?: now()->toDateString());
                    $member = new Member([
                        'name' => $name,
                        'phone' => $row['phone'] ?: null,
                        'email' => $row['email'] ?: null,
                        'created_by' => $recordedBy,
                    ]);
                    $member->created_at = Carbon::parse($joined)->startOfDay();
                    if (! $dryRun) {
                        $member->save();
                    }
                    $summary['members_created']++;
                }

                if ($joinedOverride && $member->exists && ! $dryRun) {
                    $member->created_at = Carbon::parse($joinedOverride)->startOfDay();
                    $member->save();
                }

                $byName[$this->key($name)] = $member;
            }

            foreach ($payments as $row) {
                if (Payment::withTrashed()->where('reference', $row['reference'])->exists()) {
                    $summary['payments_skipped']++;

                    continue;
                }

                $member = $byName[$this->key($row['member_name'])];
                if (! $dryRun) {
                    Payment::create([
                        'member_id' => $member->id,
                        'amount' => (float) $row['amount'],
                        'paid_on' => $row['paid_on'],
                        'reference' => $row['reference'],
                        'notes' => $row['notes'],
                        'recorded_by' => $recordedBy,
                    ]);
                }
                $summary['payments_created']++;
            }
        };

        if ($dryRun) {
            $work();
        } else {
            DB::transaction($work);
        }

        $total = collect($payments)->sum(fn ($p) => (float) $p['amount']);
        $this->table(['Item', 'Count'], collect($summary)->map(fn ($v, $k) => [str_replace('_', ' ', $k), $v])->values());
        $this->components->info('Payments in file: ' . count($payments) . ' totalling TZS ' . number_format($total));

        if ($dryRun) {
            $this->components->warn('Dry run: nothing was saved.');

            return self::SUCCESS;
        }

        if (! $this->option('no-rebuild')) {
            $this->components->info('Rebuilding contribution ledger for all members...');
            $ledger->rebuildAll();
        }

        $this->components->info('Import complete.');

        return self::SUCCESS;
    }

    private function key(string $name): string
    {
        return mb_strtolower(preg_replace('/\s+/', ' ', trim($name)));
    }

    /** @return array<int, array<string, string>>|null */
    private function readCsv(string $path): ?array
    {
        if (! is_file($path)) {
            $this->components->error("File not found: {$path}");

            return null;
        }

        $handle = fopen($path, 'r');
        $headers = array_map(fn ($h) => trim($h, "\xEF\xBB\xBF \t"), fgetcsv($handle));
        $rows = [];
        while (($data = fgetcsv($handle)) !== false) {
            if (count(array_filter($data, fn ($v) => trim((string) $v) !== '')) === 0) {
                continue;
            }
            $rows[] = array_map('trim', array_combine($headers, array_pad($data, count($headers), '')));
        }
        fclose($handle);

        return $rows;
    }
}

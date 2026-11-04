{{-- Monthly statement table. Expects $statement (ContributionLedger::statementForMember()). --}}
@php
    $badgeClass = fn ($status) => match (true) {
        str_starts_with($status, 'Paid on time') => 'text-bg-success',
        str_starts_with($status, 'Paid late') => 'text-bg-warning',
        str_starts_with($status, 'Partly paid') => 'text-bg-info',
        str_starts_with($status, 'Unpaid') => 'text-bg-danger',
        str_starts_with($status, 'Current month') => 'text-bg-primary',
        default => 'text-bg-light',
    };
@endphp
<div class="table-responsive">
    <table class="table table-sm table-hover mb-0">
        <thead>
            <tr>
                <th>Month</th>
                <th class="text-end">Expected</th>
                <th class="text-end">Penalties</th>
                <th class="text-end">Amount</th>
                <th>Paid on</th>
                <th class="text-end">Balance</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($statement['months'] as $row)
                <tr>
                    <td>{{ $row['label'] }}</td>
                    <td class="text-end">{{ $row['expected'] > 0 ? formatAmount($row['expected']) : '—' }}</td>
                    <td class="text-end">{{ $row['penalty_total'] > 0 ? formatAmount($row['penalty_total']) : '—' }}</td>
                    <td class="text-end">{{ $row['paid'] > 0 ? formatAmount($row['paid']) : '—' }}</td>
                    <td>{{ $row['last_paid_on'] ? \Carbon\Carbon::parse($row['last_paid_on'])->format('j M Y') : '—' }}</td>
                    <td class="text-end">{{ $row['balance'] > 0 ? formatAmount($row['balance']) : '—' }}</td>
                    <td><span class="badge {{ $badgeClass($row['status']) }}">{{ $row['status'] }}</span></td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr class="table-light fw-bold">
                <td>Total</td>
                <td class="text-end">{{ formatAmount($statement['year_totals']['expected']) }}</td>
                <td class="text-end">{{ formatAmount($statement['year_totals']['penalties']) }}</td>
                <td class="text-end">{{ formatAmount($statement['year_totals']['paid']) }}</td>
                <td></td>
                <td class="text-end">{{ formatAmount($statement['year_totals']['balance']) }}</td>
                <td></td>
            </tr>
        </tfoot>
    </table>
</div>

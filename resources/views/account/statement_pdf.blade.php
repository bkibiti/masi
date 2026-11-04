@include('reports_pdf.header')

<div class="row">
    <h3 align="center" style="color:SteelBlue;">Statement for {{ $member->name }} — {{ $year }}</h3>

    <table width="100%" style="width:100%; margin-bottom: 15px;">
        <tr>
            <td>Paid this year: <strong>{{ formatAmount($statement['year_totals']['paid']) }}</strong></td>
            <td>Penalties paid/owed: <strong>{{ formatAmount($penalties_paid) }} / {{ formatAmount($penalties_owed) }}</strong></td>
        </tr>
        <tr>
            <td>Arrears: <strong>{{ formatAmount($statement['arrears']) }}</strong></td>
            <td>Credit: <strong>{{ formatAmount($statement['credit']) }}</strong></td>
        </tr>
    </table>

    <table width="100%" style="width:100%" border="1" cellspacing="0">
        <thead>
            <tr>
                <th bgcolor="#bfbfbf">Month</th>
                <th bgcolor="#bfbfbf">Expected</th>
                <th bgcolor="#bfbfbf">Penalties</th>
                <th bgcolor="#bfbfbf">Paid</th>
                <th bgcolor="#bfbfbf">Balance</th>
                <th bgcolor="#bfbfbf">Status</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($statement['months'] as $row)
                <tr>
                    <td style="text-align:center">{{ $row['label'] }}</td>
                    <td style="text-align:right">{{ $row['expected'] > 0 ? formatAmount($row['expected']) : '—' }}</td>
                    <td style="text-align:right">{{ $row['penalty_total'] > 0 ? formatAmount($row['penalty_total']) : '—' }}</td>
                    <td style="text-align:right">{{ $row['paid'] > 0 ? formatAmount($row['paid']) : '—' }}</td>
                    <td style="text-align:right">{{ $row['balance'] > 0 ? formatAmount($row['balance']) : '—' }}</td>
                    <td style="text-align:center">{{ $row['status'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <h4 style="margin-top:20px;">Payment history</h4>
    <table width="100%" style="width:100%" border="1" cellspacing="0">
        <thead>
            <tr>
                <th bgcolor="#bfbfbf">Paid on</th>
                <th bgcolor="#bfbfbf">Amount</th>
                <th bgcolor="#bfbfbf">Type</th>
                <th bgcolor="#bfbfbf">Method</th>
                <th bgcolor="#bfbfbf">Reference</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($payments as $payment)
                <tr>
                    <td style="text-align:center">{{ $payment->paid_on->format('j M Y') }}</td>
                    <td style="text-align:right">{{ formatAmount($payment->amount) }}</td>
                    <td>{{ $payment->paymentType?->name ?? '—' }}</td>
                    <td>{{ $payment->paymentMethod?->name ?? '—' }}</td>
                    <td>{{ $payment->reference ?: '—' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>

@include('reports_pdf.footer')

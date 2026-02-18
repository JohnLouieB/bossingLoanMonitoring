<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monthly Report - Bossing Loan Monitoring</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px; }
        h1 { color: #1e293b; font-size: 1.5rem; margin-bottom: 0.5rem; }
        h2 { color: #475569; font-size: 1.1rem; margin-top: 1.5rem; margin-bottom: 0.5rem; }
        .greeting { margin-bottom: 1.5rem; }
        .stat { background: #f8fafc; padding: 12px 16px; border-radius: 8px; margin: 8px 0; border-left: 4px solid #3b82f6; }
        .stat-value { font-size: 1.25rem; font-weight: bold; color: #1e293b; }
        .list { background: #f8fafc; padding: 12px; border-radius: 8px; margin-top: 8px; }
        .list-item { padding: 10px 0; border-bottom: 1px solid #e2e8f0; }
        .list-item:last-child { border-bottom: none; }
        .status-paid { color: #16a34a; font-weight: 600; }
        .status-pending { color: #dc2626; font-weight: 600; }
        .footer { margin-top: 2rem; padding-top: 1rem; font-size: 0.875rem; color: #64748b; }
    </style>
</head>
<body>
    <h1>Bossing Loan Monitoring - Monthly Report</h1>
    <p class="greeting">Hello {{ $memberName }},</p>
    <p>Here is your personalized report for <strong>{{ $reportMonth }} {{ $reportYear }}</strong>.</p>

    @if (count($memberLoans) > 0)
        <h2>Your Loan(s)</h2>
        <div class="list">
            @foreach ($memberLoans as $index => $loan)
                <div class="list-item">
                    <strong>Loan {{ count($memberLoans) > 1 ? $index + 1 . ': ' : '' }}</strong>
                    @if (!empty($loan['description']))
                        {{ $loan['description'] }}<br>
                    @endif
                    <table style="width:100%; font-size: 0.95rem; margin-top: 6px;">
                        <tr><td style="color: #64748b;">Loan amount:</td><td style="text-align: right; font-weight: 600;">₱{{ number_format($loan['loan_amount'], 2) }}</td></tr>
                        <tr><td style="color: #64748b;">Interest this month:</td><td style="text-align: right;">₱{{ number_format($loan['interest_this_month'], 2) }}</td></tr>
                        <tr><td style="color: #64748b;">Remaining balance:</td><td style="text-align: right; font-weight: 600;">
                            @if ($loan['remaining_balance'] <= 0)
                                <span style="color: #16a34a;">Paid</span>
                            @else
                                ₱{{ number_format($loan['remaining_balance'], 2) }}
                            @endif
                        </td></tr>
                    </table>
                </div>
            @endforeach
        </div>
    @else
        <h2>Your Loans</h2>
        <p style="color: #64748b;">You have no active loans.</p>
    @endif

    <h2>Monthly Contribution Status ({{ $reportMonth }} {{ $reportYear }})</h2>
    <div class="stat">
        @if ($monthlyContributionStatus === 'Paid')
            <span class="status-paid">✓ Paid</span>
        @else
            <span class="status-pending">Pending</span>
            <p style="margin: 4px 0 0 0; font-size: 0.9rem; color: #64748b;">Amount to pay: <strong>₱{{ number_format($monthlyContributionAmount, 2) }}</strong></p>
        @endif
    </div>

    <h2>Organization Summary (All Years)</h2>
    <div class="stat">
        <span class="stat-value">₱{{ number_format($totalAvailableMoneyAllYears, 2) }}</span>
        <p style="margin: 4px 0 0 0; font-size: 0.9rem; color: #64748b;">Total available money across all years</p>
    </div>
    <div class="stat">
        <span class="stat-value">₱{{ number_format($totalMoneyReleasedAllYears, 2) }}</span>
        <p style="margin: 4px 0 0 0; font-size: 0.9rem; color: #64748b;">Total money released across all years</p>
    </div>

    <div class="footer">
        <p>This is an automated report from Bossing Loan Monitoring System.</p>
    </div>
</body>
</html>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transaction Notification</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background-color: #f8f9fa;
            padding: 20px 0;
            line-height: 1.6;
            color: #1a1a1a;
        }

        .container {
            max-width: 520px;
            margin: 0 auto;
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
        }

        .header {
            background: linear-gradient(135deg, #1e40af 0%, #3b82f6 100%);
            padding: 40px 30px;
            text-align: center;
            color: white;
        }

        .header-title {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 8px;
            letter-spacing: -0.5px;
        }

        .header-subtitle {
            font-size: 13px;
            opacity: 0.9;
            font-weight: 500;
        }

        .content {
            padding: 40px 30px;
        }

        .greeting {
            font-size: 15px;
            font-weight: 600;
            color: #1a1a1a;
            margin-bottom: 24px;
        }

        .badge {
            display: inline-block;
            padding: 8px 14px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
            letter-spacing: 0.5px;
            margin-bottom: 24px;
            text-transform: uppercase;
        }

        .badge-contribution {
            background: #dbeafe;
            color: #1e40af;
        }

        .badge-loan-disbursement {
            background: #fef3c7;
            color: #92400e;
        }

        .badge-loan-repayment {
            background: #d1fae5;
            color: #065f46;
        }

        .badge-default {
            background: #e5e7eb;
            color: #374151;
        }

        .key-info {
            background: #f3f4f6;
            padding: 24px;
            border-radius: 8px;
            margin-bottom: 24px;
        }

        .key-info-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid #e5e7eb;
        }

        .key-info-row:last-child {
            border-bottom: none;
            padding-bottom: 0;
        }

        .key-info-label {
            font-size: 13px;
            color: #6b7280;
            font-weight: 500;
        }

        .key-info-value {
            font-size: 14px;
            font-weight: 600;
            color: #1a1a1a;
        }

        .key-info-value.amount {
            font-size: 18px;
            color: #1e40af;
            font-weight: 700;
        }

        .details-section {
            background: #f9fafb;
            padding: 24px;
            border-radius: 8px;
            margin-top: 24px;
            border-left: 4px solid #3b82f6;
        }

        .section-title {
            font-size: 13px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #6b7280;
            margin-bottom: 16px;
        }

        .detail-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid #e5e7eb;
        }

        .detail-row:last-child {
            border-bottom: none;
        }

        .detail-label {
            font-size: 13px;
            color: #6b7280;
            font-weight: 500;
        }

        .detail-value {
            font-size: 13px;
            color: #1a1a1a;
            font-weight: 600;
            text-align: right;
        }

        .detail-value.amount {
            font-weight: 700;
            font-size: 14px;
        }

        .detail-value.outstanding {
            color: #dc2626;
            font-weight: 700;
        }

        .detail-value.outstanding.paid {
            color: #059669;
        }

        .status-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-active {
            background: #d1fae5;
            color: #065f46;
        }

        .status-repaid {
            background: #dbeafe;
            color: #1e40af;
        }

        .status-written-off {
            background: #fee2e2;
            color: #7f1d1d;
        }

        .status-overdue {
            background: #fecaca;
            color: #7f1d1d;
        }

        .notes-box {
            background: #fffbeb;
            border-left: 4px solid #f59e0b;
            padding: 16px;
            border-radius: 6px;
            margin-top: 24px;
        }

        .notes-label {
            font-size: 11px;
            font-weight: 700;
            color: #92400e;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 6px;
        }

        .notes-text {
            font-size: 13px;
            color: #78350f;
            line-height: 1.5;
        }

        .footer {
            background: #f9fafb;
            padding: 20px 30px;
            text-align: center;
            border-top: 1px solid #e5e7eb;
        }

        .footer-text {
            font-size: 12px;
            color: #9ca3af;
        }

        .footer-brand {
            font-weight: 600;
            color: #6b7280;
        }

        /* Responsive */
        @media (max-width: 600px) {
            .container {
                border-radius: 0;
            }

            .header {
                padding: 30px 20px;
            }

            .header-title {
                font-size: 20px;
            }

            .content {
                padding: 25px 20px;
            }

            .key-info,
            .details-section {
                padding: 18px;
            }

            .key-info-row,
            .detail-row {
                flex-direction: column;
                align-items: flex-start;
                padding: 10px 0;
            }

            .detail-value,
            .key-info-value {
                text-align: left;
                margin-top: 5px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <div class="header-title">{{ $society->name }}</div>
            <div class="header-subtitle">Transaction Notification</div>
        </div>

        <!-- Content -->
        <div class="content">
            <!-- Greeting -->
            <div class="greeting">Hi {{ $member->user->name }},</div>

            <!-- Transaction Type Badge -->
            @php
                $badgeClass = 'badge-default';
                $typeLabel = ucfirst(str_replace('_', ' ', $transaction->type));
                
                if ($transaction->type === 'contribution') {
                    $badgeClass = 'badge-contribution';
                    $typeLabel = 'Contribution';
                } elseif ($transaction->type === 'loan_disbursement') {
                    $badgeClass = 'badge-loan-disbursement';
                    $typeLabel = 'Loan Disbursement';
                } elseif ($transaction->type === 'loan_repayment') {
                    $badgeClass = 'badge-loan-repayment';
                    $typeLabel = 'Loan Repayment';
                }
            @endphp
            
            <div class="badge {{ $badgeClass }}">
                {{ $typeLabel }}
            </div>

            <!-- Key Information -->
            <div class="key-info">
                <div class="key-info-row">
                    <span class="key-info-label">Member</span>
                    <span class="key-info-value">{{ $transaction->member->user->name }}</span>
                </div>
                <div class="key-info-row">
                    <span class="key-info-label">Amount</span>
                    <span class="key-info-value amount">M {{ number_format($transaction->amount, 2) }}</span>
                </div>
                <div class="key-info-row">
                    <span class="key-info-label">Date</span>
                    <span class="key-info-value">{{ $transaction->transaction_date->format('M d, Y') }}</span>
                </div>
            </div>

            <!-- Loan Disbursement Details -->
            @if ($transaction->type === 'loan_disbursement' && $loan)
            <div class="details-section">
                <div class="section-title">Loan Details</div>
                
                <div class="detail-row">
                    <span class="detail-label">Principal Amount</span>
                    <span class="detail-value amount">M {{ number_format($loan->principal, 2) }}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Interest Amount</span>
                    <span class="detail-value amount">M {{ number_format($loan->interest, 2) }}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Total Amount Due</span>
                    <span class="detail-value amount" style="color: #1e40af;">M {{ number_format($loan->total_amount, 2) }}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Interest Rate</span>
                    <span class="detail-value">{{ number_format($loan->interest_rate ?? $society->interest_rate ?? 0, 1) }}%</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Due Date</span>
                    <span class="detail-value">{{ $loan->due_date->format('M d, Y') }}</span>
                </div>

                @if ($loan->purpose)
                <div class="detail-row">
                    <span class="detail-label">Purpose</span>
                    <span class="detail-value">{{ $loan->purpose }}</span>
                </div>
                @endif
            </div>
            @endif

            <!-- Loan Repayment Status -->
            @if ($transaction->type === 'loan_repayment' && $loan)
            <div class="details-section">
                <div class="section-title">Loan Status</div>
                
                <div class="detail-row">
                    <span class="detail-label">Total Loan Amount</span>
                    <span class="detail-value amount">M {{ number_format($loan->total_amount, 2) }}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Amount Repaid</span>
                    <span class="detail-value amount">M {{ number_format($loan->amount_repaid, 2) }}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Outstanding Balance</span>
                    @php
                        $outstandingClass = $loan->outstanding_balance > 0 ? 'outstanding' : 'outstanding paid';
                    @endphp
                    <span class="detail-value {{ $outstandingClass }}">M {{ number_format($loan->outstanding_balance, 2) }}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Status</span>
                    @php
                        $statusClass = 'status-' . $loan->status;
                    @endphp
                    <span class="status-badge {{ $statusClass }}">
                        {{ ucfirst($loan->status) }}
                    </span>
                </div>
            </div>
            @endif

            <!-- Notes -->
            @if ($transaction->notes)
            <div class="notes-box">
                <div class="notes-label">📝 Notes</div>
                <div class="notes-text">{{ $transaction->notes }}</div>
            </div>
            @endif
        </div>

        <!-- Footer -->
        <div class="footer">
            <div class="footer-text">
                <span class="footer-brand">Mokhatlo</span>
            </div>
            <div class="footer-text" style="margin-top: 8px;">
                This is an automated notification. Please do not reply to this email.
            </div>
        </div>
    </div>
</body>
</html>

@php
    $money = fn ($cents) =>
        'M ' . number_format($cents / 100, 2);

    $monthly = $insights['monthly'];

    $totalOverdue =
        $report['contributions_overdue']
        + $report['loan_instalments_overdue'];

    $interestRefunds = $profitPreview
        ? $profitPreview['interest_collected_cents']
        : 0;

    $sharedPenalties = $profitPreview
        ? $profitPreview['penalty_pool_cents']
        : 0;
@endphp

{{-- Main dashboard header --}}
<div class="mk-analytics-hero mb-4">

    <div class="d-flex justify-content-between
                align-items-start flex-wrap gap-3">

        <div>
            <span class="mk-analytics-kicker">
                <i class="bi bi-activity me-2"></i>
                Society Financial Intelligence
            </span>

            <h1 class="mt-3 mb-2">
                Financial Overview
            </h1>

            <p class="mb-0">
                {{ $society->name }}

                @if($report['cycle'])
                    &middot; {{ $report['cycle']->name }}
                @endif
            </p>
        </div>

        <form
            method="GET"
            action="{{ $filterRoute }}"
            class="d-flex align-items-center gap-2"
        >
            <label
                for="analytics-months"
                class="visually-hidden"
            >
                Reporting Period
            </label>

            <select
                name="months"
                id="analytics-months"
                class="form-select form-select-sm"
                onchange="this.form.submit()"
            >
                @foreach([3, 6, 12] as $period)
                    <option
                        value="{{ $period }}"
                        @selected($months === $period)
                    >
                        Last {{ $period }} Months
                    </option>
                @endforeach
            </select>

            <button
                type="submit"
                class="btn btn-sm btn-light"
                title="Refresh"
            >
                <i class="bi bi-arrow-clockwise"></i>
            </button>
        </form>
    </div>

    <div class="mt-4 d-flex flex-wrap gap-2">

        <span class="mk-hero-chip">
            <i class="bi bi-shield-check me-1"></i>
            Ledger-Backed Data
        </span>

        <span class="mk-hero-chip">
            <i class="bi bi-calendar3 me-1"></i>
            {{ now('Africa/Maseru')->format('d M Y') }}
        </span>

        <span class="mk-hero-chip">
            <i class="bi bi-people me-1"></i>

            {{ $society->memberships()
                ->where('status', 'active')
                ->count() }}
            Active Members
        </span>

    </div>
</div>

{{-- Primary KPI cards --}}
<div class="row g-3 mb-4">

    @foreach([
        [
            'label' => 'Recorded Liquid Assets',
            'value' => $report['liquid_assets'],
            'icon' => 'bi-wallet2',
            'description' => 'Cash, bank and mobile money',
            'tone' => 'purple',
        ],
        [
            'label' => 'Savings Received',
            'value' => $report['contributions_received'],
            'icon' => 'bi-piggy-bank',
            'description' => 'Current savings cycle',
            'tone' => 'blue',
        ],
        [
            'label' => 'Loan Principal Outstanding',
            'value' => $report['loan_principal_outstanding'],
            'icon' => 'bi-cash-stack',
            'description' => 'Unpaid loan principal',
            'tone' => 'teal',
        ],
        [
            'label' => 'Past-Due Balances',
            'value' => $totalOverdue,
            'icon' => 'bi-exclamation-triangle',
            'description' => 'Contribution and loan balances',
            'tone' => 'amber',
        ],
    ] as $metric)

        <div class="col-sm-6 col-xl-3">
            <div class="mk-analytics-card h-100">

                <div class="d-flex justify-content-between
                            align-items-start mb-4">

                    <span class="mk-analytics-icon
                                 tone-{{ $metric['tone'] }}">
                        <i class="bi {{ $metric['icon'] }}"></i>
                    </span>

                    <i class="bi bi-three-dots muted"></i>
                </div>

                <p class="mk-analytics-label">
                    {{ $metric['label'] }}
                </p>

                <div class="mk-analytics-value">
                    {{ $money($metric['value']) }}
                </div>

                <p class="small muted mt-2 mb-0">
                    {{ $metric['description'] }}
                </p>

            </div>
        </div>

    @endforeach
</div>

{{-- Primary financial chart --}}
<div class="row g-3 mb-4">

    <div class="col-xl-8">
        <div class="mk-chart-card h-100">

            <div class="d-flex justify-content-between
                        align-items-start flex-wrap gap-3 mb-4">

                <div>
                    <h2 class="mk-chart-heading">
                        Financial Activity
                    </h2>

                    <p class="small muted mb-0">
                        Contributions, repayments and disbursements
                    </p>
                </div>

                <div class="btn-group btn-group-sm"
                     role="group"
                     aria-label="Chart type">

                    <button
                        type="button"
                        class="btn btn-outline-secondary active"
                        data-chart-type="bar"
                    >
                        <i class="bi bi-bar-chart me-1"></i>
                        Bars
                    </button>

                    <button
                        type="button"
                        class="btn btn-outline-secondary"
                        data-chart-type="line"
                    >
                        <i class="bi bi-graph-up me-1"></i>
                        Lines
                    </button>

                </div>
            </div>

            <div class="mk-chart-stage">
                <canvas id="mkActivityChart"></canvas>
            </div>

        </div>
    </div>

    {{-- Cash account breakdown --}}
    <div class="col-xl-4">
        <div class="mk-chart-card h-100">

            <h2 class="mk-chart-heading">
                Where Society Funds Are
            </h2>

            <p class="small muted mb-3">
                Recorded cash, bank and mobile money balances
            </p>

            <div class="mk-chart-stage mk-chart-stage-small">
                <canvas id="mkAssetsChart"></canvas>
            </div>

            <div class="d-grid gap-2 mt-3">

                @foreach($report['accounts'] as $account)

                    <div class="d-flex justify-content-between
                                align-items-center gap-2 small">

                        <span class="muted">
                            {{ $account['label'] }}
                        </span>

                        <strong>
                            {{ $money($account['amount']) }}
                        </strong>

                    </div>

                @endforeach

            </div>
        </div>
    </div>
</div>

{{-- Earnings and loan statuses --}}
<div class="row g-3 mb-4">

    <div class="col-xl-6">
        <div class="mk-chart-card h-100">

            <h2 class="mk-chart-heading">
                Interest Refunds & Shared Penalties
            </h2>

            <p class="small muted mb-4">
                Collected borrower interest and collected penalties
            </p>

            <div class="mk-chart-stage mk-chart-stage-small">
                <canvas id="mkEarningsChart"></canvas>
            </div>

            <div class="row g-3 mt-2">

                <div class="col-6">
                    <div class="metric-label">
                        Borrower Interest Refunds
                    </div>

                    <strong class="d-block mt-1">
                        {{ $money($interestRefunds) }}
                    </strong>
                </div>

                <div class="col-6">
                    <div class="metric-label">
                        Collected Penalty Pool
                    </div>

                    <strong class="d-block mt-1">
                        {{ $money($sharedPenalties) }}
                    </strong>
                </div>

            </div>

        </div>
    </div>

    <div class="col-xl-6">
        <div class="mk-chart-card h-100">

            <h2 class="mk-chart-heading">
                Loan Portfolio
            </h2>

            <p class="small muted mb-4">
                Loans awaiting disbursement, active or settled
            </p>

            <div class="mk-chart-stage mk-chart-stage-small">
                <canvas id="mkLoanStatusChart"></canvas>
            </div>

            <p class="small muted mb-0 mt-3">
                Captured loans are already verbally approved.
                Awaiting disbursement is not an approval queue.
            </p>

        </div>
    </div>

</div>

{{-- Secondary financial indicators --}}
<div class="row g-3 mb-4">

    <div class="col-lg-4">
        <div class="mk-analytics-card h-100">

            <h2 class="mk-chart-heading mb-4">
                Contribution Position
            </h2>

            @foreach([
                [
                    'Scheduled Minimums',
                    $report['contributions_required']
                ],
                [
                    'Outstanding Minimums',
                    $report['contributions_outstanding']
                ],
                [
                    'Overdue Minimums',
                    $report['contributions_overdue']
                ],
            ] as [$label, $value])

                <div class="d-flex justify-content-between
                            align-items-center gap-2 mb-3">

                    <span class="small muted">
                        {{ $label }}
                    </span>

                    <strong>
                        {{ $money($value) }}
                    </strong>
                </div>

            @endforeach

        </div>
    </div>

    <div class="col-lg-4">
        <div class="mk-analytics-card h-100">

            <h2 class="mk-chart-heading mb-4">
                Lending Position
            </h2>

            @foreach([
                [
                    'Principal Outstanding',
                    $report['loan_principal_outstanding']
                ],
                [
                    'Interest Outstanding',
                    $report['loan_interest_outstanding']
                ],
                [
                    'Interest Collected',
                    $report['loan_interest_collected']
                ],
            ] as [$label, $value])

                <div class="d-flex justify-content-between
                            align-items-center gap-2 mb-3">

                    <span class="small muted">
                        {{ $label }}
                    </span>

                    <strong>
                        {{ $money($value) }}
                    </strong>
                </div>

            @endforeach

        </div>
    </div>

    <div class="col-lg-4">
        <div class="mk-analytics-card h-100">

            <h2 class="mk-chart-heading mb-4">
                Outstanding Penalties
            </h2>

            @foreach([
                [
                    'Contribution Penalties',
                    $report['contribution_penalties_outstanding']
                ],
                [
                    'Loan Penalties',
                    $report['loan_penalties_outstanding']
                ],
            ] as [$label, $value])

                <div class="d-flex justify-content-between
                            align-items-center gap-2 mb-3">

                    <span class="small muted">
                        {{ $label }}
                    </span>

                    <strong>
                        {{ $money($value) }}
                    </strong>
                </div>

            @endforeach

            <p class="small muted mt-3 mb-0">
                Unpaid penalties are not distributable cash.
            </p>

        </div>
    </div>

</div>

{{-- Recent financial activity --}}
<div class="mk-chart-card mb-4">

    <div class="d-flex justify-content-between
                align-items-start flex-wrap gap-3 mb-4">

        <div>
            <h2 class="mk-chart-heading">
                Recent Transactions
            </h2>

            <p class="small muted mb-0">
                Latest posted financial movements
            </p>
        </div>

        @can('transactions.view')
            <a href="{{ route('transactions.index') }}"
               class="btn btn-sm btn-outline-primary">
                View All Transactions
            </a>
        @endcan

    </div>

    <div class="table-responsive">
        <table class="table align-middle mk-table">

            <thead>
                <tr>
                    <th>Date</th>
                    <th>Transaction</th>
                    <th>Recorded By</th>
                    <th class="text-end">Amount</th>
                </tr>
            </thead>

            <tbody>
                @forelse($insights['recent_transactions'] as $transaction)

                    @php
                        $amountCents = $transaction->lines->sum(
                            fn ($line) =>
                                \App\Support\Money::toCents(
                                    $line->debit_amount
                                )
                        );
                    @endphp

                    <tr>
                        <td>
                            {{ \Carbon\Carbon::parse(
                                $transaction->posting_date
                            )->format('d M Y') }}
                        </td>

                        <td>
                            <strong>
                                {{ $transaction->description }}
                            </strong>

                            <div class="small muted">
                                {{ ucwords(str_replace(
                                    '_',
                                    ' ',
                                    $transaction->source_type
                                )) }}
                            </div>
                        </td>

                        <td>
                            {{ $transaction->creator?->name ?? 'System' }}
                        </td>

                        <td class="text-end fw-bold">
                            {{ $money($amountCents) }}
                        </td>
                    </tr>

                @empty
                    <tr>
                        <td colspan="4"
                            class="text-center py-4 muted">
                            No financial activity recorded yet.
                        </td>
                    </tr>
                @endforelse
            </tbody>

        </table>
    </div>

</div>

<div class="small muted mb-4">
    These figures are based on recorded transactions and are not
    a substitute for cash and bank reconciliation. Interest refunds
    and penalty shares are entitlements, not completed payouts.
</div>

<script
    src="https://cdn.jsdelivr.net/npm/chart.js@4.5.1/dist/chart.umd.min.js">
</script>

<script>
document.addEventListener('DOMContentLoaded', function () {
    if (typeof Chart === 'undefined') {
        return;
    }

    const monthly = @json($monthly);
    const accounts = @json(array_values($report['accounts']));
    const loanStatuses = @json($insights['loan_statuses']);

    const interestCents = @json($interestRefunds);
    const penaltyCents = @json($sharedPenalties);

    const toLoti = cents =>
        Number(cents || 0) / 100;

    const currency = amount =>
        'M ' + Number(amount || 0).toLocaleString(
            'en-ZA',
            {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            }
        );

    const colors = {
        purple: '#7866f5',
        blue: '#3f8cff',
        teal: '#19b6a2',
        orange: '#efa840',
        red: '#ed6877',
        gray: '#8b90a5'
    };

    const charts = [];

    const textColor = () =>
        getComputedStyle(document.documentElement)
            .getPropertyValue('--bs-body-color')
            .trim() || '#667085';

    const gridColor = () =>
        document.documentElement.getAttribute(
            'data-bs-theme'
        ) === 'dark'
            ? 'rgba(255,255,255,.08)'
            : 'rgba(31,41,55,.08)';

    const commonLegend = {
        position: 'bottom',
        labels: {
            usePointStyle: true,
            padding: 18,
            color: textColor()
        }
    };

    let activityChart = null;

    function renderActivityChart(type) {
        if (activityChart) {
            activityChart.destroy();

            const index = charts.indexOf(activityChart);

            if (index !== -1) {
                charts.splice(index, 1);
            }
        }

        activityChart = new Chart(
            document.getElementById('mkActivityChart'),
            {
                type: type,

                data: {
                    labels: monthly.map(
                        row => row.label
                    ),

                    datasets: [
                        {
                            label: 'Contributions',
                            data: monthly.map(
                                row => toLoti(row.contributions)
                            ),
                            backgroundColor: colors.purple,
                            borderColor: colors.purple,
                            borderRadius: 6,
                            tension: .35
                        },
                        {
                            label: 'Loan Repayments',
                            data: monthly.map(
                                row => toLoti(row.repayments)
                            ),
                            backgroundColor: colors.teal,
                            borderColor: colors.teal,
                            borderRadius: 6,
                            tension: .35
                        },
                        {
                            label: 'Loan Disbursements',
                            data: monthly.map(
                                row => toLoti(row.disbursements)
                            ),
                            backgroundColor: colors.orange,
                            borderColor: colors.orange,
                            borderRadius: 6,
                            tension: .35
                        }
                    ]
                },

                options: {
                    responsive: true,
                    maintainAspectRatio: false,

                    interaction: {
                        mode: 'index',
                        intersect: false
                    },

                    plugins: {
                        legend: commonLegend,

                        tooltip: {
                            callbacks: {
                                label(context) {
                                    return context.dataset.label
                                        + ': '
                                        + currency(context.parsed.y);
                                }
                            }
                        }
                    },

                    scales: {
                        x: {
                            grid: {
                                display: false
                            },
                            ticks: {
                                color: textColor()
                            }
                        },

                        y: {
                            beginAtZero: true,
                            grid: {
                                color: gridColor()
                            },
                            ticks: {
                                color: textColor(),
                                callback: value =>
                                    'M ' + Number(value)
                                        .toLocaleString('en-ZA')
                            }
                        }
                    }
                }
            }
        );

        charts.push(activityChart);
    }

    function renderDoughnut(
        canvasId,
        labels,
        values,
        palette,
        isCurrency = true
    ) {
        const hasData = values.some(
            value => value > 0
        );

        const chart = new Chart(
            document.getElementById(canvasId),
            {
                type: 'doughnut',

                data: {
                    labels: hasData
                        ? labels
                        : ['No Data'],

                    datasets: [
                        {
                            data: hasData
                                ? values
                                : [1],

                            backgroundColor: hasData
                                ? palette
                                : [colors.gray],

                            borderWidth: 0,
                            hoverOffset: 8
                        }
                    ]
                },

                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '72%',

                    plugins: {
                        legend: commonLegend,

                        tooltip: {
                            callbacks: {
                                label(context) {
                                    if (!hasData) {
                                        return 'No recorded data';
                                    }

                                    return context.label
                                        + ': '
                                        + (
                                            isCurrency
                                                ? currency(context.parsed)
                                                : context.parsed
                                        );
                                }
                            }
                        }
                    }
                }
            }
        );

        charts.push(chart);
    }

    renderActivityChart('bar');

    renderDoughnut(
        'mkAssetsChart',
        accounts.map(row => row.label),
        accounts.map(row =>
            Math.max(0, toLoti(row.amount))
        ),
        [
            colors.purple,
            colors.blue,
            colors.teal
        ]
    );

    renderDoughnut(
        'mkEarningsChart',
        [
            'Borrower Interest Refunds',
            'Collected Penalties'
        ],
        [
            toLoti(interestCents),
            toLoti(penaltyCents)
        ],
        [
            colors.purple,
            colors.orange
        ]
    );

    renderDoughnut(
        'mkLoanStatusChart',
        [
            'Awaiting Disbursement',
            'Disbursed',
            'Settled',
            'Legacy Rejected'
        ],
        [
            loanStatuses.approved || 0,
            loanStatuses.disbursed || 0,
            loanStatuses.settled || 0,
            loanStatuses.rejected || 0
        ],
        [
            colors.blue,
            colors.orange,
            colors.teal,
            colors.red
        ],
        false
    );

    document.querySelectorAll(
        '[data-chart-type]'
    ).forEach(function (button) {
        button.addEventListener(
            'click',
            function () {
                const type = this.dataset.chartType;

                document.querySelectorAll(
                    '[data-chart-type]'
                ).forEach(function (item) {
                    item.classList.toggle(
                        'active',
                        item === button
                    );
                });

                renderActivityChart(type);
            }
        );
    });

    // Synchronise chart text with Bootstrap theme.
    const observer = new MutationObserver(
        function () {
            charts.forEach(function (chart) {
                if (
                    chart.options.plugins
                    && chart.options.plugins.legend
                ) {
                    chart.options.plugins.legend.labels.color =
                        textColor();
                }

                if (chart.options.scales?.x) {
                    chart.options.scales.x.ticks.color =
                        textColor();

                    chart.options.scales.y.ticks.color =
                        textColor();

                    chart.options.scales.y.grid.color =
                        gridColor();
                }

                chart.update('none');
            });
        }
    );

    observer.observe(
        document.documentElement,
        {
            attributes: true,
            attributeFilter: ['data-bs-theme']
        }
    );
});
</script>

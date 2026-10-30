
<div class="d-flex align-items-center gap-3 px-2 mb-4">
    <span class="brand-mark">M</span>

    <div>
        <div class="fw-bold fs-5 lh-sm">
            Mokhatlo
        </div>
        <div class="small muted">
            Society Management
        </div>
    </div>
</div>

<div class="nav-section-label">
    Workspace
</div>

<nav aria-label="Society navigation">

    <a href="{{ route('dashboard') }}"
       class="app-nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}">
        <i class="bi bi-grid-1x2 me-3"></i>
        Overview
    </a>

    <a href="{{ route('societies.index') }}"
       class="app-nav-link">
        <i class="bi bi-buildings me-3"></i>
        My Societies
    </a>

    @can('members.view')
        <a href="{{ route('members.index') }}"
           class="app-nav-link {{ request()->routeIs('members.*') ? 'active' : '' }}">
            <i class="bi bi-people me-3"></i>
            Members
        </a>
    @endcan

</nav>

<div class="nav-section-label">
    Financial Management
</div>

<nav aria-label="Financial navigation">

    @can('cycles.view')
        <a href="{{ route('cycles.index') }}"
           class="app-nav-link {{ request()->routeIs('cycles.*') ? 'active' : '' }}">
            <i class="bi bi-calendar3 me-3"></i>
            Savings Cycles
        </a>
    @endcan

    
@if(
    auth()->user()->can('contributions.view')
    || auth()->user()->can('contributions.view_own')
)
    <a href="{{ route('contributions.index') }}"
       class="app-nav-link {{ request()->routeIs('contributions.*') ? 'active' : '' }}">
        <i class="bi bi-wallet2 me-3"></i>
        Contributions
    </a>
@endif


@if(
    auth()->user()->can('penalties.view')
    || auth()->user()->can('penalties.view_own')
)
    <a href="{{ route('penalties.index') }}"
       class="app-nav-link {{ request()->routeIs('penalties.*') ? 'active' : '' }}">

        <i class="bi bi-exclamation-circle me-3"></i>
        Penalties
    </a>
@endif

@can('transactions.view')
    <a href="{{ route('transactions.index') }}"
       class="app-nav-link {{ request()->routeIs('transactions.*') ? 'active' : '' }}">

        <i class="bi bi-journal-text me-3"></i>
        Transactions
    </a>
@endcan

@if(
    auth()->user()->can('loans.view')
    || auth()->user()->can('loans.view_own')
)
    <a href="{{ route('loans.index') }}"
       class="app-nav-link {{ request()->routeIs('loans.*') ? 'active' : '' }}">

        <i class="bi bi-cash-stack me-3"></i>
        Loans
    </a>
@endif


@if(
    auth()->user()->can('loans.penalties.view')
    || auth()->user()->can('loans.penalties.view_own')
)
    <a href="{{ route('loans.penalties.index') }}"
       class="app-nav-link {{ request()->routeIs('loans.penalties.*') ? 'active' : '' }}">
        <i class="bi bi-exclamation-diamond me-3"></i>
        Loan Penalties
    </a>
@endif

@if(auth()->user()->can('reports.view'))
    <a href="{{ route('reports.index') }}"
       class="app-nav-link {{ request()->routeIs('reports.index', 'reports.export') ? 'active' : '' }}">
        <i class="bi bi-graph-up-arrow me-3"></i>
        Financial Reports
    </a>
@endif

@if(
    auth()->user()->can('reports.view')
    || auth()->user()->can('reports.view_own')
)
    <a href="{{ route('reports.member-statement') }}"
       class="app-nav-link {{ request()->routeIs('reports.member-statement*') ? 'active' : '' }}">
        <i class="bi bi-file-earmark-text me-3"></i>
        Member Statements
    </a>
@endif


@can('payouts.view')
    <a
        href="{{ route('payouts.index') }}"
        class="app-nav-link {{
            request()->routeIs('payouts.*') ? 'active' : ''
        }}"
    >
        <i class="bi bi-wallet2 me-3"></i>
        Year-End Payouts
    </a>
@endcan


</nav>

<div class="nav-section-label">
    Coming Next
</div>

<div class="px-3 py-2 muted small">
  

</div>

<div class="mt-4 border-top pt-3">

    <div class="px-3 small muted mb-3">
        Signed in as

        <strong class="d-block mt-1"
                style="color: var(--mk-text)">
            {{ auth()->user()->name }}
        </strong>
    </div>

    <form method="POST" action="{{ route('logout') }}">
        @csrf

        <button
            type="submit"
            class="app-nav-link w-100 border-0
                   bg-transparent text-start">

            <i class="bi bi-box-arrow-right me-3"></i>
            Sign Out
        </button>
    </form>

</div>

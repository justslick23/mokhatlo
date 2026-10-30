
@extends('layouts.app')

@section('title', 'Loan Penalties')

@section('content')
<div class="d-flex justify-content-between
            align-items-end flex-wrap gap-3 mb-4">
    <div>
        <p class="eyebrow mb-2">Loan Management</p>
        <h1 class="page-title">
            {{ $canManage ? 'Loan Penalties' : 'My Loan Penalties' }}
        </h1>
        <p class="muted mt-2 mb-0">
            Track overdue instalment charges, payments and waivers.
        </p>
    </div>
</div>

<div class="card-soft">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle mk-table">
                <thead>
                    <tr>
                        <th>Loan</th>
                        @if($canManage)
                            <th>Member</th>
                        @endif
                
                        <th>Assessed</th>
                        <th>Collected</th>
                        <th>Waived</th>
                        <th>Outstanding</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($penalties as $penalty)
                        <tr>
                            <td>
                                <strong>{{ $penalty->loan->loan_number }}</strong>
                                <div class="small muted">
                                    {{ $penalty->assessed_on->format('d M Y') }}
                                </div>
                            </td>

                            @if($canManage)
                                <td>
                                    {{ $penalty->loan->membership->user->name }}
                                </td>
                            @endif

                      

                            <td>
                                M {{ \App\Support\Money::fromCents(
                                    $penalty->assessedCents()
                                ) }}
                            </td>

                            <td>
                                M {{ \App\Support\Money::fromCents(
                                    $penalty->paidCents()
                                ) }}
                            </td>

                            <td>
                                M {{ \App\Support\Money::fromCents(
                                    $penalty->waivedCents()
                                ) }}
                            </td>

                            <td>
                                <strong>
                                    M {{ \App\Support\Money::fromCents(
                                        $penalty->outstandingCents()
                                    ) }}
                                </strong>
                            </td>

                            <td class="text-end">
                                <a
                                    href="{{ route('loans.penalties.show', $penalty->id) }}"
                                    class="btn btn-sm btn-outline-primary"
                                >
                                    View
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $canManage ? 8 : 7 }}">
                                <div class="empty-state">
                                    <i class="bi bi-shield-check fs-2 d-block mb-3"></i>
                                    <strong>No loan penalties</strong>
                                    <p class="muted mb-0 mt-2">
                                        Assessed overdue instalment penalties
                                        will appear here.
                                    </p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $penalties->links() }}
        </div>
    </div>
</div>
@endsection

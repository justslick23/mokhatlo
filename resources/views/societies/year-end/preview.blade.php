@extends('layouts.app')

@section('content')
<div class="container-fluid">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3>Year-End Settlement Preview</h3>
        <span class="badge bg-warning text-dark">
            Cycle Ending
        </span>
    </div>

    {{-- Summary Cards --}}
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <h6>Total Contributions</h6>
                    <h4>{{ number_format($data['totalContributions'],2) }}</h4>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <h6>Total Interest</h6>
                    <h4>{{ number_format($data['totalInterest'],2) }}</h4>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <h6>Total Penalties</h6>
                    <h4>{{ number_format($data['totalPenalties'],2) }}</h4>
                </div>
            </div>
        </div>
    </div>

    {{-- Member Breakdown --}}
    <div class="card">
        <div class="card-header">
            <h5>Member Settlement Breakdown</h5>
        </div>

        <div class="table-responsive">
            <table class="table table-bordered align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Member</th>
                        <th>Contributions</th>
                        <th>Interest Share</th>
                        <th>Penalty Share</th>
                        <th>Outstanding Loans</th>
                        <th>Final Payout</th>
                    </tr>
                </thead>
                <tbody>
                @foreach($data['rows'] as $row)
                    <tr>
                        <td>{{ $row['member']->user->name }}</td>
                        <td>{{ number_format($row['contributions'],2) }}</td>
                        <td>{{ number_format($row['interest'],2) }}</td>
                        <td>{{ number_format($row['penalties'],2) }}</td>
                        <td class="text-danger">
                            {{ number_format($row['outstanding'],2) }}
                        </td>
                        <td class="fw-bold text-success">
                            {{ number_format($row['payout'],2) }}
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>

        <div class="card-footer text-end">
            <form method="POST"
                  action="{{ route('societies.year-end.process', $society) }}">
                @csrf
                <button class="btn btn-danger">
                    <i data-feather="check-circle"></i>
                    Confirm & Close Cycle
                </button>
            </form>
        </div>
    </div>

</div>
@endsection

@extends('layouts.app')

@section('content')
<div class="container">
    <div class="d-flex justify-content-between mb-3">
        <h4>Cycles – {{ $society->name }}</h4>
        <a href="{{ route('societies.cycles.create', $society) }}"
           class="btn btn-primary">
            + New Cycle
        </a>
    </div>

    <table class="table">
        <thead>
            <tr>
                <th>Name</th>
                <th>Period</th>
                <th>Status</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @foreach($cycles as $cycle)
            <tr>
                <td>{{ $cycle->name }}</td>
                <td>{{ $cycle->start_date }} → {{ $cycle->end_date }}</td>
                <td>
                    <span class="badge bg-{{ $cycle->status === 'active' ? 'success' : 'secondary' }}">
                        {{ ucfirst($cycle->status) }}
                    </span>
                </td>
                <td>
                    @if($cycle->status === 'active')
                    <form method="POST"
                          action="{{ route('societies.cycles.close', [$society, $cycle]) }}">
                        @csrf
                        @method('PUT')
                        <button class="btn btn-sm btn-danger">
                            Close
                        </button>
                    </form>
                    @endif
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endsection

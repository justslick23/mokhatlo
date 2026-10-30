
@extends('layouts.app')

@section('title', 'Members')

@section('content')

<div class="d-flex align-items-center justify-content-between
            flex-wrap gap-3 mb-4">

    <div>
        <p class="eyebrow mb-2">Society Management</p>
        <h1 class="page-title">Members</h1>
        <p class="muted mt-2 mb-0">
            Manage memberships and invitations for
            {{ $society->name }}.
        </p>
    </div>

    @can('members.invite')
        <a href="{{ route('members.invite') }}"
           class="btn btn-primary">
            <i class="bi bi-person-plus me-2"></i>
            Invite Member
        </a>
    @endcan

</div>

<div class="row g-3 mb-4">

    <div class="col-md-4">
        <div class="card-soft">
            <div class="card-body">
                <div class="metric-label">
                    Total Members
                </div>
                <div class="metric-value">
                    {{ $society->memberships()->count() }}
                </div>
                <div class="small muted mt-2">
                    All society memberships
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card-soft">
            <div class="card-body">
                <div class="metric-label">
                    Active Members
                </div>
                <div class="metric-value">
                    {{ $society->memberships()
                        ->where('status', 'active')
                        ->count() }}
                </div>
                <div class="small muted mt-2">
                    Currently active
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card-soft">
            <div class="card-body">
                <div class="metric-label">
                    Pending Invitations
                </div>
                <div class="metric-value">
                    {{ $society->invitations()
                        ->whereNull('accepted_at')
                        ->whereNull('revoked_at')
                        ->where('expires_at', '>', now())
                        ->count() }}
                </div>
                <div class="small muted mt-2">
                    Awaiting acceptance
                </div>
            </div>
        </div>
    </div>

</div>

<div class="card-soft mb-4">
    <div class="card-body">

        <div class="d-flex justify-content-between
                    align-items-center mb-4">
            <h2 class="section-title mb-0">
                Society Members
            </h2>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mk-table">
                <thead>
                    <tr>
                        <th>Member</th>
                        <th>Member No.</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Date Joined</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse($members as $membership)
                        <tr>
                            <td>
                                <div class="d-flex align-items-center gap-3">
                                    <span class="avatar-circle">
                                        {{ strtoupper(
                                            mb_substr(
                                                $membership->user->name,
                                                0,
                                                1
                                            )
                                        ) }}
                                    </span>

                                    <div>
                                        <strong class="d-block">
                                            {{ $membership->user->name }}
                                        </strong>
                                        <span class="small muted">
                                            {{ $membership->user->email }}
                                        </span>
                                    </div>
                                </div>
                            </td>

                            <td>
                                {{ $membership->member_number ?? '—' }}
                            </td>

                            <td>
                                @php
                                    $roleName = $membership->user
                                        ->getRoleNames()
                                        ->first() ?? 'member';
                                @endphp

                                <span class="badge text-bg-secondary">
                                    {{ ucwords($roleName) }}
                                </span>
                            </td>

                            <td>
                                @if($membership->status === 'active')
                                    <span class="badge bg-success-subtle text-success">
                                        Active
                                    </span>
                                @else
                                    <span class="badge bg-warning-subtle text-warning-emphasis">
                                        {{ ucfirst($membership->status) }}
                                    </span>
                                @endif
                            </td>

                            <td>
                                {{ $membership->joined_at?->format('d M Y') ?? '—' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5">
                                <div class="empty-state">
                                    <i class="bi bi-people fs-2 d-block mb-3"></i>
                                    <h3 class="h6">No members found</h3>
                                    <p class="mb-0">
                                        Invite members to get started.
                                    </p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-3">
            {{ $members->links() }}
        </div>

    </div>
</div>

@if($pendingInvitations->isNotEmpty())
    <div class="card-soft">
        <div class="card-body">

            <h2 class="section-title mb-4">
                Pending Invitations
            </h2>

            <div class="table-responsive">
                <table class="table align-middle mk-table">
                    <thead>
                        <tr>
                            <th>Email Address</th>
                            <th>Assigned Role</th>
                            <th>Expires</th>
                        </tr>
                    </thead>

                    <tbody>
                        @foreach($pendingInvitations as $invitation)
                            <tr>
                                <td>{{ $invitation->email }}</td>
                                <td>{{ ucfirst($invitation->role_name) }}</td>
                                <td>
                                    {{ $invitation->expires_at->format('d M Y') }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

        </div>
    </div>
@endif

@endsection

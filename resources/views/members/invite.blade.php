
@extends('layouts.app')

@section('title', 'Invite Member')

@section('content')

<div class="mb-4">
    <a href="{{ route('members.index') }}"
       class="small fw-semibold">
        <i class="bi bi-arrow-left me-2"></i>
        Back to Members
    </a>
</div>

<div class="row justify-content-center">

    <div class="col-lg-8 col-xl-7">

        <div class="mb-4">
            <p class="eyebrow mb-2">
                Membership Management
            </p>

            <h1 class="page-title">
                Invite a Member
            </h1>

            <p class="muted mt-2">
                Invite someone to join your savings society.
                They'll receive a secure invitation link by email.
            </p>
        </div>

        <div class="card-soft">
            <div class="card-body p-lg-4">

                <form method="POST"
                      action="{{ route('members.invite.store') }}">
                    @csrf

                    <div class="mb-4">
                        <label class="form-label" for="email">
                            Email Address
                        </label>

                        <input
                            type="email"
                            id="email"
                            name="email"
                            value="{{ old('email') }}"
                            class="form-control @error('email') is-invalid @enderror"
                            placeholder="member@example.com"
                            required
                        >

                        @error('email')
                            <div class="invalid-feedback">
                                {{ $message }}
                            </div>
                        @enderror
                    </div>

                    <div class="mb-4">
                        <label for="role_name" class="form-label">
                            Society Role
                        </label>

                        <select
                            name="role_name"
                            id="role_name"
                            class="form-select @error('role_name') is-invalid @enderror"
                            required
                        >
                            @foreach($allowedRoles as $role)
                                <option value="{{ $role }}"
                                    @selected(old('role_name', 'member') === $role)>
                                    {{ ucfirst($role) }}
                                </option>
                            @endforeach
                        </select>

                        @error('role_name')
                            <div class="invalid-feedback">
                                {{ $message }}
                            </div>
                        @enderror

                        <div class="form-text">
                            Roles determine what the member can
                            access and manage within this society.
                        </div>
                    </div>

                    <div class="mk-info-panel mb-4">

                        <div class="d-flex gap-3">
                            <i class="bi bi-info-circle fs-4"></i>

                            <div>
                                <strong class="d-block mb-2">
                                    How invitations work
                                </strong>

                                <p class="mb-0 small">
                                    The recipient must register or sign in
                                    using the invited email address,
                                    verify their account, and accept the
                                    invitation within seven days.
                                </p>
                            </div>
                        </div>

                    </div>

                    <div class="d-flex justify-content-end
                                gap-2 flex-wrap">

                        <a href="{{ route('members.index') }}"
                           class="btn btn-outline-secondary">
                            Cancel
                        </a>

                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-send me-2"></i>
                            Send Invitation
                        </button>

                    </div>
                </form>

            </div>
        </div>
    </div>
</div>

@endsection

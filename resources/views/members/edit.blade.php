@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <h3 class="mb-4">Edit Member</h3>

    <form method="POST" action="{{ route('societies.members.update', [$society, $member]) }}">
        @csrf
        @method('PUT')

        <div class="mb-3">
            <label>Name</label>
            <input class="form-control" value="{{ $member->user->name }}" disabled>
        </div>

        <div class="mb-3">
            <label>Email</label>
            <input class="form-control" value="{{ $member->user->email }}" disabled>
        </div>

        <div class="row">
            <div class="col-md-4 mb-3">
                <label>Role</label>
                <select name="role" class="form-control">
                    @foreach(['member','chairman','treasurer','secretary'] as $role)
                        <option value="{{ $role }}" @selected($member->role === $role)>
                            {{ ucfirst($role) }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="col-md-4 mb-3">
                <label>Status</label>
                <select name="status" class="form-control">
                    <option value="active" @selected($member->status === 'active')>Active</option>
                    <option value="inactive" @selected($member->status === 'inactive')>Inactive</option>
                </select>
            </div>
        </div>

        <button class="btn btn-primary">Save Changes</button>
        <a href="{{ route('societies.members.index', $society) }}" class="btn btn-light">
            Cancel
        </a>
    </form>
</div>
@endsection

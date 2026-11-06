@extends('layouts.portal')

@section('title', 'User Management')

@section('content')
    <div class="page-heading">
        <div><h1>User Management</h1><p>Manage system users and permissions.</p></div>
        <span class="text-link">{{ number_format($users->total()) }} users</span>
    </div>
    <section class="panel">
        <div class="panel-head"><h2>All Users</h2><span class="text-link">User accounts and access roles</span></div>
        <div class="panel-body">
            <form class="toolbar" method="GET" action="{{ route('users.index') }}">
                <div class="filters">
                    <input type="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Search users...">
                    <select name="role" aria-label="Filter role"><option value="">All roles</option>@foreach ($roles as $role)<option value="{{ $role->value }}" @selected(($filters['role'] ?? '') === $role->value)>{{ str($role->value)->title() }}</option>@endforeach</select>
                    <select name="is_active" aria-label="Filter status"><option value="">All status</option><option value="1" @selected(($filters['is_active'] ?? '') === '1')>Active</option><option value="0" @selected(($filters['is_active'] ?? '') === '0')>Inactive</option></select>
                </div>
                <button class="btn primary" type="submit">Search</button><a class="btn subtle" href="{{ route('users.index') }}">Reset</a>
            </form>
        </div>
        <div class="table-wrap">
            <table class="data-table"><thead><tr><th>User</th><th>Company</th><th>Role</th><th>Status</th><th>Shipments</th><th>Joined</th><th>Actions</th></tr></thead><tbody>
                @forelse ($users as $user)
                    <tr>
                        <td class="primary-text">{{ $user->name }}<span class="secondary-text">{{ $user->email }}</span></td>
                        <td>{{ $user->company_name ?: '—' }}</td>
                        <td><span class="status other">{{ str($user->role->value)->title() }}</span></td>
                        <td><span class="status {{ $user->is_active ? 'active' : 'cancelled' }}">{{ $user->is_active ? 'Active' : 'Inactive' }}</span></td>
                        <td>{{ number_format($user->shipments_count) }}</td>
                        <td>{{ $user->created_at->format('d M Y') }}</td>
                        <td><a class="btn" href="mailto:{{ $user->email }}" aria-label="Contact {{ $user->name }}">Contact ↗</a></td>
                    </tr>
                @empty
                    <tr><td colspan="7"><div class="empty">No users match these filters.</div></td></tr>
                @endforelse
            </tbody></table>
        </div>
        <div class="pagination">{{ $users->links() }}</div>
    </section>
@endsection

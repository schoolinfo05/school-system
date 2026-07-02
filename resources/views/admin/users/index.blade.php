@extends('layouts.portal', ['title' => 'Users'])

@section('content')
@php
    $manageableRoles = \App\Models\User::ADMIN_MANAGEABLE_ROLES;
    $allRoles = \App\Models\User::ROLES;
    $roleLabel = fn ($role) => ucwords(str_replace('_', ' ', $role));
@endphp
<div class="grid grid-cols-1 lg:grid-cols-[360px_1fr] gap-6">
    <section class="bg-white rounded-xl border border-slate-200 shadow-sm p-5 h-fit">
        <h1 class="text-xl font-black text-slate-900 mb-4">Create Staff User</h1>
        <form method="POST" action="{{ route('admin.users.store') }}" class="space-y-3">
            @csrf
            <input name="name" value="{{ old('name') }}" placeholder="Full name" class="w-full rounded-lg border-slate-300 text-sm">
            <input name="email" value="{{ old('email') }}" placeholder="Email" class="w-full rounded-lg border-slate-300 text-sm">
            <select name="role" class="w-full rounded-lg border-slate-300 text-sm">
                @foreach($manageableRoles as $role)
                    <option value="{{ $role }}">{{ $roleLabel($role) }}</option>
                @endforeach
            </select>
            <input name="password" type="password" placeholder="Temporary password" class="w-full rounded-lg border-slate-300 text-sm">
            <button class="w-full rounded-lg bg-blue-700 text-white py-2 text-sm font-bold">Create user</button>
        </form>
    </section>

    <section>
        <div class="mb-4">
            <h1 class="text-2xl font-black text-slate-900">Users</h1>
            <p class="text-sm text-slate-500 mt-1">Manage admin, registrar, faculty, parent, and staff web/mobile accounts.</p>
        </div>
        <form method="GET" class="bg-white rounded-xl border border-slate-200 p-4 mb-5 grid grid-cols-1 md:grid-cols-[1fr_160px_auto] gap-3">
            <input name="search" value="{{ request('search') }}" placeholder="Search name or email" class="rounded-lg border-slate-300 text-sm">
            <select name="role" class="rounded-lg border-slate-300 text-sm">
                <option value="">All roles</option>
                @foreach($allRoles as $role)
                    <option value="{{ $role }}" @selected(request('role') === $role)>{{ $roleLabel($role) }}</option>
                @endforeach
            </select>
            <button class="rounded-lg bg-slate-900 text-white px-5 py-2 text-sm font-bold">Filter</button>
        </form>

        <div class="bg-white rounded-xl border border-slate-200 shadow-sm divide-y divide-slate-100">
            @foreach($users as $user)
                <form method="POST" action="{{ route('admin.users.update', $user) }}" class="p-4 grid grid-cols-1 md:grid-cols-[1fr_1fr_150px_180px_auto] gap-3 items-center">
                    @csrf
                    @method('PUT')
                    <input name="name" value="{{ $user->name }}" class="rounded-lg border-slate-300 text-sm">
                    <input name="email" value="{{ $user->email }}" class="rounded-lg border-slate-300 text-sm">
                    <select name="role" class="rounded-lg border-slate-300 text-sm">
                        @foreach($manageableRoles as $role)
                            <option value="{{ $role }}" @selected($user->role === $role)>{{ $roleLabel($role) }}</option>
                        @endforeach
                    </select>
                    <input name="password" type="password" placeholder="New password optional" class="rounded-lg border-slate-300 text-sm">
                    <button class="rounded-lg bg-blue-700 text-white px-4 py-2 text-sm font-bold">Save</button>
                </form>
            @endforeach
            <div class="p-4">{{ $users->links() }}</div>
        </div>
    </section>
</div>
@endsection

@extends('layouts.portal', ['title' => 'Profile'])

@section('content')
<div class="mb-6">
    <h1 class="text-2xl font-black text-slate-900">Profile</h1>
    <p class="mt-1 text-sm text-slate-500">Your registrar portal account details.</p>
</div>

<section class="max-w-2xl rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
    <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div><dt class="text-sm text-slate-500">Name</dt><dd class="font-black text-slate-900">{{ $user->name }}</dd></div>
        <div><dt class="text-sm text-slate-500">Email</dt><dd class="font-black text-slate-900">{{ $user->email }}</dd></div>
        <div><dt class="text-sm text-slate-500">Role</dt><dd class="font-black text-slate-900">{{ ucwords(str_replace('_', ' ', $user->role)) }}</dd></div>
        <div><dt class="text-sm text-slate-500">Position</dt><dd class="font-black text-slate-900">{{ $user->position ? ucwords(str_replace('_', ' ', $user->position)) : '-' }}</dd></div>
    </dl>
</section>
@endsection

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\AuthorizesPortal;
use App\Models\User;
use App\Services\ArchiveService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

class UserManageController extends Controller
{
    use AuthorizesPortal;

    public function index(Request $request)
    {
        $this->requireAnyRole($request, ['admin']);

        $users = User::query()
            ->whereIn('role', User::ROLES)
            ->when($request->role, fn ($query) => $query->where('role', $request->role))
            ->when($request->search, function ($query) use ($request) {
                $search = trim($request->search);
                $query->where(fn ($inner) => $inner
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%"));
            })
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('admin.users.index', compact('users'));
    }

    public function store(Request $request)
    {
        $this->requireAnyRole($request, ['admin']);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'role' => ['required', Rule::in(User::ADMIN_MANAGEABLE_ROLES)],
            'position' => ['nullable', 'string', Rule::in($this->allowedPositions())],
            'password' => ['required', 'string', 'min:6'],
        ]);
        $data['position'] = $this->positionForRole($data['role'], $data['position'] ?? null);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'role' => $data['role'],
            'position' => $data['position'],
            'password' => Hash::make($data['password']),
        ]);
        Role::findOrCreate($data['role'], 'web');
        $user->assignRole($data['role']);

        return redirect()->route('admin.users.index')->with('status', 'User created.');
    }

    public function update(Request $request, User $user)
    {
        $this->requireAnyRole($request, ['admin']);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'role' => ['required', Rule::in(User::ADMIN_MANAGEABLE_ROLES)],
            'position' => ['nullable', 'string', Rule::in($this->allowedPositions())],
            'password' => ['nullable', 'string', 'min:6'],
        ]);
        $data['position'] = $this->positionForRole($data['role'], $data['position'] ?? null);

        if ($user->id === $request->user()->id && $data['role'] !== 'admin') {
            return back()->withErrors(['role' => 'You cannot remove admin access from your own account.']);
        }

        $user->fill([
            'name' => $data['name'],
            'email' => $data['email'],
            'role' => $data['role'],
            'position' => $data['position'],
        ]);

        if (!empty($data['password'])) {
            $user->password = Hash::make($data['password']);
        }

        $user->save();
        Role::findOrCreate($data['role'], 'web');
        $user->syncRoles([$data['role']]);

        return redirect()->route('admin.users.index')->with('status', 'User updated.');
    }

    private function allowedPositions(): array
    {
        return collect(User::POSITIONS)->flatten()->values()->all();
    }

    private function positionForRole(string $role, ?string $position): ?string
    {
        if ($role === User::ROLE_FACULTY && !$position) {
            return User::POSITION_TEACHER;
        }

        if (!$position) {
            return null;
        }

        return in_array($position, User::POSITIONS[$role] ?? [], true) ? $position : null;
    }

    public function destroy(Request $request, User $user)
    {
        $this->requireAnyRole($request, ['admin']);

        if ($user->id === $request->user()->id) {
            return back()->withErrors(['user' => 'You cannot delete your own account.']);
        }

        if (($user->role === User::ROLE_ADMIN || $user->hasRole(User::ROLE_ADMIN))
            && User::query()->where('role', User::ROLE_ADMIN)->count() <= 1) {
            return back()->withErrors(['user' => 'At least one admin account is required.']);
        }

        ArchiveService::record($user, $request->user()?->id, 'web.admin.users');
        $user->delete();

        return redirect()->route('admin.users.index')->with('status', 'User archived and removed.');
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

class AdminUserController extends Controller
{
    private array $manageableRoles = ['admin', 'registrar', 'teacher'];

    public function dashboard(Request $request)
    {
        $this->authorizeAdmin($request);

        $counts = User::query()
            ->selectRaw('role, COUNT(*) as total')
            ->whereIn('role', ['admin', 'registrar', 'teacher', 'student'])
            ->groupBy('role')
            ->pluck('total', 'role');

        $recentUsers = User::query()
            ->whereIn('role', $this->manageableRoles)
            ->latest()
            ->limit(5)
            ->get(['id', 'name', 'email', 'role', 'created_at']);

        return response()->json([
            'counts' => [
                'admins'     => (int) ($counts['admin'] ?? 0),
                'registrars' => (int) ($counts['registrar'] ?? 0),
                'teachers'   => (int) ($counts['teacher'] ?? 0),
                'students'   => (int) ($counts['student'] ?? 0),
            ],
            'recent_users' => $recentUsers,
        ]);
    }

    public function index(Request $request)
    {
        $this->authorizeAdmin($request);

        $role = $request->query('role');
        $search = trim((string) $request->query('search', ''));

        $users = User::query()
            ->whereIn('role', $this->manageableRoles)
            ->when(in_array($role, $this->manageableRoles, true), fn ($query) => $query->where('role', $role))
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->orderBy('role')
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'role', 'created_at', 'updated_at']);

        return response()->json($users);
    }

    public function store(Request $request)
    {
        $this->authorizeAdmin($request);

        $data = $request->validate([
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:6'],
            'role'     => ['required', Rule::in($this->manageableRoles)],
        ]);

        $user = User::create([
            'name'     => $data['name'],
            'email'    => $data['email'],
            'password' => Hash::make($data['password']),
            'role'     => $data['role'],
        ]);

        $this->syncRole($user, $data['role']);

        return response()->json($user->only(['id', 'name', 'email', 'role', 'created_at', 'updated_at']), 201);
    }

    public function update(Request $request, User $user)
    {
        $this->authorizeAdmin($request);
        $this->ensureManageable($user);

        $data = $request->validate([
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['nullable', 'string', 'min:6'],
            'role'     => ['required', Rule::in($this->manageableRoles)],
        ]);

        if ($user->id === $request->user()->id && $data['role'] !== 'admin') {
            return response()->json(['message' => 'You cannot remove admin access from your own account.'], 422);
        }

        if ($this->isLastAdmin($user) && $data['role'] !== 'admin') {
            return response()->json(['message' => 'At least one admin account is required.'], 422);
        }

        $user->name = $data['name'];
        $user->email = $data['email'];
        $user->role = $data['role'];

        if (!empty($data['password'])) {
            $user->password = Hash::make($data['password']);
        }

        $user->save();
        $this->syncRole($user, $data['role']);

        return response()->json($user->only(['id', 'name', 'email', 'role', 'created_at', 'updated_at']));
    }

    public function destroy(Request $request, User $user)
    {
        $this->authorizeAdmin($request);
        $this->ensureManageable($user);

        if ($user->id === $request->user()->id) {
            return response()->json(['message' => 'You cannot delete your own account.'], 422);
        }

        if ($this->isLastAdmin($user)) {
            return response()->json(['message' => 'At least one admin account is required.'], 422);
        }

        $user->delete();

        return response()->json(['message' => 'User deleted.']);
    }

    private function authorizeAdmin(Request $request): void
    {
        $user = $request->user();

        if (!$user || ($user->role !== 'admin' && !$user->hasRole('admin'))) {
            abort(response()->json(['message' => 'Admin access is required.'], 403));
        }
    }

    private function ensureManageable(User $user): void
    {
        if (!in_array($user->role, $this->manageableRoles, true) && !$user->hasAnyRole($this->manageableRoles)) {
            abort(response()->json(['message' => 'This user cannot be managed from the admin mobile dashboard.'], 422));
        }
    }

    private function syncRole(User $user, string $role): void
    {
        Role::firstOrCreate(['name' => $role]);
        $user->syncRoles([$role]);
    }

    private function isLastAdmin(User $user): bool
    {
        if ($user->role !== 'admin' && !$user->hasRole('admin')) {
            return false;
        }

        return User::query()
            ->where('role', 'admin')
            ->orWhereHas('roles', fn ($query) => $query->where('name', 'admin'))
            ->count() <= 1;
    }
}

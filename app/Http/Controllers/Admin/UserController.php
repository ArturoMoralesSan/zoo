<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    /**
     * Solo un SuperAdmin puede dar ese rol o tocar cuentas que lo tengan:
     * si no, un admin podría escalar a un rol que se salta todos los permisos.
     */
    private const SUPER_ADMIN = 'SuperAdmin';

    public function index(Request $request)
    {
        $users = User::with('roles')
            ->when($request->search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('admin/users/Index', [
            'users' => $users,
            'filters' => [
                'search' => $request->search,
            ],
        ]);
    }

    public function create()
    {
        return Inertia::render('admin/users/Create', [
            'roles' => Role::where('guard_name', 'web')
                ->when(! $this->actorIsSuperAdmin(), fn ($query) => $query->where('name', '!=', self::SUPER_ADMIN))
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'role' => $this->roleRules(),
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);

        $user->assignRole($validated['role']);

        return redirect()
            ->route('admin.users.index')
            ->with('success', 'Usuario creado correctamente.');
    }

    public function edit(User $user)
    {
        $this->ensureCanManage($user);

        $user->load('roles');

        return Inertia::render('admin/users/Edit', [
            'user' => $user,
            'roles' => Role::where('guard_name', 'web')
                ->when(! $this->actorIsSuperAdmin(), fn ($query) => $query->where('name', '!=', self::SUPER_ADMIN))
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function update(Request $request, User $user)
    {
        $this->ensureCanManage($user);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                'max:255',
                'unique:users,email,'.$user->id,
            ],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'role' => $this->roleRules(),
        ]);

        $data = [
            'name' => $validated['name'],
            'email' => $validated['email'],
        ];

        if (! empty($validated['password'])) {
            $data['password'] = Hash::make($validated['password']);
        }

        $user->update($data);

        $user->syncRoles([$validated['role']]);

        return redirect()
            ->route('admin.users.index')
            ->with('success', 'Usuario actualizado correctamente.');
    }

    public function destroy(User $user)
    {
        $this->ensureCanManage($user);

        if ($user->id === auth()->id()) {
            return back()->with('error', 'No puedes eliminar tu propio usuario.');
        }

        $user->delete();

        return redirect()
            ->route('admin.users.index')
            ->with('success', 'Usuario eliminado correctamente.');
    }

    private function actorIsSuperAdmin(): bool
    {
        return (bool) auth()->user()?->hasRole(self::SUPER_ADMIN);
    }

    /**
     * @return array<int, mixed>
     */
    private function roleRules(): array
    {
        return [
            'required',
            'exists:roles,name',
            ...($this->actorIsSuperAdmin() ? [] : [Rule::notIn([self::SUPER_ADMIN])]),
        ];
    }

    private function ensureCanManage(User $user): void
    {
        abort_if(
            $user->hasRole(self::SUPER_ADMIN) && ! $this->actorIsSuperAdmin(),
            403,
            'Solo un SuperAdmin puede modificar esta cuenta.'
        );
    }
}

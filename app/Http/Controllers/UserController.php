<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $users = User::with('branch')
            ->when($request->filled('q'), function ($query) use ($request) {
                $search = '%' . $request->q . '%';
                $query->where(fn ($q) => $q->where('name', 'like', $search)->orWhere('email', 'like', $search));
            })
            ->orderBy('name')
            ->paginate(10);
        return view('users.index', compact('users'));
    }

    public function create()
    {
        $branches = Branch::where('is_active', true)->orderBy('name')->get();
        return view('users.create', compact('branches'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'branch_id' => ['required_unless:role,super_admin,admin', 'nullable', 'exists:branches,id'],
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:180', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'phone' => ['nullable', 'string', 'max:30'],
            'role' => ['required', Rule::in($this->assignableRoles())],
            'commission_type' => ['nullable', Rule::in(['fixed', 'percent'])],
            'commission_value' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'is_active' => ['boolean'],
        ]);

        $validated['password'] = bcrypt($validated['password']);
        // La contraseña la conoce quien la creó: el usuario la cambia al entrar.
        $validated['must_change_password'] = true;

        User::create($validated);

        return redirect()->route('users.index')
            ->with('success', 'Usuario creado exitosamente.');
    }

    public function show(User $user)
    {
        $user->load('branch');
        return view('users.show', compact('user'));
    }

    public function edit(User $user)
    {
        $this->authorizeManage($user);
        $branches = Branch::where('is_active', true)->orderBy('name')->get();
        return view('users.edit', compact('user', 'branches'));
    }

    public function update(Request $request, User $user)
    {
        $this->authorizeManage($user);

        $validated = $request->validate([
            'branch_id' => ['required_unless:role,super_admin,admin', 'nullable', 'exists:branches,id'],
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:180', Rule::unique('users')->ignore($user->id)],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'phone' => ['nullable', 'string', 'max:30'],
            'role' => ['required', Rule::in($this->assignableRoles())],
            'commission_type' => ['nullable', Rule::in(['fixed', 'percent'])],
            'commission_value' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'is_active' => ['boolean'],
        ]);

        // Nadie cambia su propio rol ni se desactiva a sí mismo.
        if ($user->id === Auth::id()) {
            $validated['role'] = $user->role;
            $validated['is_active'] = true;
        }

        // Siempre debe quedar al menos un super admin activo.
        $losesSuperAdmin = $user->isSuperAdmin() && $user->is_active
            && ($validated['role'] !== 'super_admin' || ! ($validated['is_active'] ?? true));
        if ($losesSuperAdmin && ! $this->hasOtherActiveSuperAdmin($user)) {
            return back()->withInput()->with('error', 'Debe quedar al menos un super admin activo.');
        }

        if (empty($validated['password'])) {
            unset($validated['password']);
        } else {
            $validated['password'] = bcrypt($validated['password']);
            // Restablecida por otro usuario: se cambia al entrar.
            $validated['must_change_password'] = $user->id !== Auth::id();
        }

        $user->fill($validated);
        // Al desactivar o cambiar la contraseña se cierran las sesiones "recordarme".
        if ($user->isDirty('password') || ($user->isDirty('is_active') && ! $user->is_active)) {
            $user->remember_token = Str::random(60);
        }
        $user->save();

        return redirect()->route('users.index')
            ->with('success', 'Usuario actualizado exitosamente.');
    }

    public function destroy(User $user)
    {
        $this->authorizeManage($user);

        if ($user->id === auth()->id()) {
            return back()->with('error', 'No puedes eliminar tu propio usuario.');
        }
        if ($user->isSuperAdmin() && $user->is_active && ! $this->hasOtherActiveSuperAdmin($user)) {
            return back()->with('error', 'Debe quedar al menos un super admin activo.');
        }

        $user->delete();

        return redirect()->route('users.index')
            ->with('success', 'Usuario eliminado exitosamente.');
    }

    /**
     * Roles que puede asignar el usuario actual: solo un super admin crea otros super admin.
     */
    private function assignableRoles(): array
    {
        return Auth::user()->isSuperAdmin()
            ? ['super_admin', 'admin', 'technician', 'receptionist']
            : ['admin', 'technician', 'receptionist'];
    }

    /**
     * Un admin no puede editar ni eliminar a un super admin.
     */
    private function authorizeManage(User $user): void
    {
        abort_if($user->isSuperAdmin() && ! Auth::user()->isSuperAdmin(), 403, 'Solo un super admin puede modificar a otro super admin.');
    }

    private function hasOtherActiveSuperAdmin(User $user): bool
    {
        return User::where('role', 'super_admin')->where('is_active', true)->whereKeyNot($user->id)->exists();
    }
}

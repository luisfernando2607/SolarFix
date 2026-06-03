<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index()
    {
        $users = User::with('branch')->orderBy('name')->paginate(10);
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
            'branch_id' => ['nullable', 'exists:branches,id'],
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:180', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'phone' => ['nullable', 'string', 'max:30'],
            'role' => ['required', Rule::in(['super_admin', 'admin', 'technician', 'receptionist'])],
            'commission_type' => ['nullable', Rule::in(['fixed', 'percent'])],
            'commission_value' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'is_active' => ['boolean'],
        ]);

        $validated['password'] = bcrypt($validated['password']);

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
        $branches = Branch::where('is_active', true)->orderBy('name')->get();
        return view('users.edit', compact('user', 'branches'));
    }

    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'branch_id' => ['nullable', 'exists:branches,id'],
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:180', Rule::unique('users')->ignore($user->id)],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'phone' => ['nullable', 'string', 'max:30'],
            'role' => ['required', Rule::in(['super_admin', 'admin', 'technician', 'receptionist'])],
            'commission_type' => ['nullable', Rule::in(['fixed', 'percent'])],
            'commission_value' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'is_active' => ['boolean'],
        ]);

        if (empty($validated['password'])) {
            unset($validated['password']);
        } else {
            $validated['password'] = bcrypt($validated['password']);
        }

        $user->update($validated);

        return redirect()->route('users.index')
            ->with('success', 'Usuario actualizado exitosamente.');
    }

    public function destroy(User $user)
    {
        if ($user->id === auth()->id()) {
            return back()->with('error', 'No puedes eliminar tu propio usuario.');
        }

        $user->delete();

        return redirect()->route('users.index')
            ->with('success', 'Usuario eliminado exitosamente.');
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function index()
    {
        $organization = auth()->user()->organization;
        $users = $organization->users()->paginate(10);
        
        return view('users.index', compact('users', 'organization'));
    }

    public function create()
    {
        $organization = auth()->user()->organization;
        return view('users.create', compact('organization'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'role' => 'required|in:admin,operator,viewer',
        ]);

        $organization = auth()->user()->organization;
        
        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'organization_id' => $organization->id,
        ]);

        $user->assignRole($validated['role']);

        return redirect()->route('organization.users.index')
            ->with('success', 'Пользователь успешно создан');
    }

    public function edit(User $user)
    {
        $organization = auth()->user()->organization;
        
        // Проверяем, что пользователь принадлежит организации
        if ($user->organization_id !== $organization->id) {
            abort(403);
        }
        
        return view('users.edit', compact('user', 'organization'));
    }

    public function update(Request $request, User $user)
    {
        $organization = auth()->user()->organization;
        
        // Проверяем, что пользователь принадлежит организации
        if ($user->organization_id !== $organization->id) {
            abort(403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email,' . $user->id,
            'password' => 'nullable|string|min:8|confirmed',
            'role' => 'required|in:admin,operator,viewer',
            'is_active' => 'boolean',
        ]);

        $user->update([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'is_active' => $request->boolean('is_active'),
        ]);

        if (!empty($validated['password'])) {
            $user->update(['password' => Hash::make($validated['password'])]);
        }

        $user->syncRoles([$validated['role']]);

        return redirect()->route('organization.users.index')
            ->with('success', 'Пользователь обновлен');
    }

    public function destroy(User $user)
    {
        $organization = auth()->user()->organization;
        
        // Проверяем, что пользователь принадлежит организации
        if ($user->organization_id !== $organization->id) {
            abort(403);
        }
        
        // Нельзя удалить себя
        if ($user->id === auth()->id()) {
            return redirect()->route('organization.users.index')
                ->with('error', 'Вы не можете удалить свой аккаунт');
        }
        
        // Нельзя удалить владельца
        if ($user->hasRole('owner')) {
            return redirect()->route('organization.users.index')
                ->with('error', 'Нельзя удалить владельца организации');
        }

        $user->delete();

        return redirect()->route('organization.users.index')
            ->with('success', 'Пользователь удален');
    }
}
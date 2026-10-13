<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->only(['search', 'role', 'is_active']);

        $users = User::with('roles')
            ->filter($request)
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        $roles = Role::orderBy('name')->get();

        return view('admin.users.index', compact('users', 'roles', 'filters'));
    }

    public function create(): View
    {
        $roles = Role::orderBy('name')->get();

        return view('admin.users.create', compact('roles'));
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $roles = $request->input('roles', []);
        if (in_array('superadmin', $roles, true) && ! $request->user()?->hasRole('superadmin')) {
            abort(403, 'Hanya superadmin yang dapat menetapkan peran superadmin.');
        }

        $data = $request->safe()->except('roles');

        if (! empty($data['phone_number'])) {
            $digits = preg_replace('/\D+/', '', (string) $data['phone_number']);
            if (str_starts_with($digits, '0')) {
                $digits = '62'.substr($digits, 1);
            }
            $data['phone_normalized'] = '+'.$digits;
        } else {
            $data['phone_normalized'] = null;
        }

        $user = User::create($data);
        $user->syncRoles($roles);

        return redirect()->route('admin.users.index')->with('success', 'Pengguna berhasil ditambahkan.');
    }

    public function show(User $user): View
    {
        $user->load([
            'roles',
            'activityLogs' => fn ($query) => $query->latest('id')->take(10),
        ]);

        return view('admin.users.show', compact('user'));
    }

    public function edit(User $user): View
    {
        if ($user->hasRole('superadmin') && ! auth()->user()?->hasRole('superadmin')) {
            abort(403, 'Tidak memiliki akses untuk mengubah akun superadmin.');
        }

        $user->load('roles');
        $roles = Role::orderBy('name')->get();

        return view('admin.users.edit', compact('user', 'roles'));
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        if ($user->hasRole('superadmin') && ! $request->user()?->hasRole('superadmin')) {
            abort(403, 'Tidak memiliki akses untuk mengubah akun superadmin.');
        }

        $roles = $request->input('roles', []);
        if (in_array('superadmin', $roles, true) && ! $request->user()?->hasRole('superadmin')) {
            abort(403, 'Hanya superadmin yang dapat menetapkan peran superadmin.');
        }

        if ($request->user()?->id === $user->id) {
            if (! $request->boolean('is_active')) {
                return back()->with('error', 'Tidak dapat menonaktifkan akun sendiri.');
            }

            if ($user->hasRole('superadmin') && ! in_array('superadmin', $roles, true)) {
                return back()->with('error', 'Tidak dapat mencabut peran superadmin milik sendiri.');
            }
        }

        $data = $request->safe()->except(['roles', 'password']);

        if ($request->filled('password')) {
            $data['password'] = $request->input('password');
        }

        if (array_key_exists('phone_number', $data)) {
            if (! empty($data['phone_number'])) {
                $digits = preg_replace('/\D+/', '', (string) $data['phone_number']);
                if (str_starts_with($digits, '0')) {
                    $digits = '62'.substr($digits, 1);
                }
                $data['phone_normalized'] = '+'.$digits;
            } else {
                $data['phone_normalized'] = null;
            }
        }

        $user->update($data);
        $user->syncRoles($roles);

        return redirect()->route('admin.users.index')->with('success', 'Pengguna berhasil diperbarui.');
    }

    public function destroy(User $user): RedirectResponse
    {
        if (auth()->id() === $user->id) {
            return back()->with('error', 'Tidak dapat menghapus akun sendiri.');
        }

        if ($user->hasRole('superadmin')) {
            return back()->with('error', 'Tidak dapat menghapus akun superadmin.');
        }

        $user->delete();

        return redirect()->route('admin.users.index')->with('success', 'Pengguna berhasil dihapus.');
    }
}

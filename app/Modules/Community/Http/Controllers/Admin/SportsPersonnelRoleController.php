<?php

declare(strict_types=1);

namespace App\Modules\Community\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Community\Http\Requests\PersonnelRoleRequest;
use App\Modules\Community\Models\SportsPersonnelRole;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class SportsPersonnelRoleController extends Controller
{
    public function index(): View
    {
        $roles = SportsPersonnelRole::orderBy('sort_order')->orderBy('id')->get();

        return view('admin.personnel-roles.index', compact('roles'));
    }

    public function store(PersonnelRoleRequest $request): RedirectResponse
    {
        SportsPersonnelRole::create($request->validated());

        return back()->with('success', 'Peran ditambahkan.');
    }

    public function update(PersonnelRoleRequest $request, SportsPersonnelRole $role): RedirectResponse
    {
        $data = $request->validated();
        unset($data['code']);
        $role->update($data);

        return back()->with('success', 'Peran diperbarui. Kode tetap untuk menjaga referensi.');
    }
}

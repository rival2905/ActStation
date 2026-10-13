<?php

declare(strict_types=1);

namespace App\Modules\MasterData\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\MasterData\Models\School;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SchoolController extends Controller
{
    public function index(Request $request): View
    {
        $query = School::query()->with('city');

        if ($request->filled('search')) {
            $search = (string) $request->input('search');
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('npsn', 'like', "%{$search}%");
            });
        }

        if ($request->filled('school_level')) {
            $query->where('school_level', $request->input('school_level'));
        }

        if ($request->filled('school_status')) {
            $query->where('school_status', $request->input('school_status'));
        }

        if ($request->filled('city_id')) {
            $query->where('city_id', $request->input('city_id'));
        }

        $schools = $query->paginate(20)->withQueryString();
        $filters = $request->only(['search', 'school_level', 'school_status', 'city_id']);

        return view('admin.schools.index', compact('schools', 'filters'));
    }

    public function show(School $school): View
    {
        $school->load(['province', 'city', 'subdistrict', 'village']);

        return view('admin.schools.show', compact('school'));
    }
}

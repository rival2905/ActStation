<?php

declare(strict_types=1);

namespace App\Modules\Community\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Community\Http\Requests\AdminSchoolAuthorityRequest;
use App\Modules\Community\Models\Community;
use App\Modules\Community\Services\SchoolAuthorityService;
use Illuminate\Http\RedirectResponse;

class SchoolAuthorityController extends Controller
{
    public function store(AdminSchoolAuthorityRequest $request, Community $community, SchoolAuthorityService $service): RedirectResponse
    {
        $service->transferByAdmin((int) $community->id, $request->user(), $request->validated('target_email'), $request->validated('reason'));

        return redirect()->route('admin.communities.show', $community)->with('success', 'Tanggung jawab sekolah berhasil diserahkan. Perubahan langsung berlaku.');
    }
}

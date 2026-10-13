<?php

declare(strict_types=1);

namespace App\Modules\Event\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Modules\Event\Models\Event;
use App\Modules\Event\Services\EventInvitationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class EventInvitationController extends Controller
{
    public function store(Request $request, Event $event, EventInvitationService $invitations): RedirectResponse
    {
        if (! $request->routeIs('admin.*') && (int) $event->user_id !== (int) $request->user()?->id && ! $request->user()?->hasRole('superadmin')) {
            abort(403);
        }

        $data = $request->validate([
            'event_category_id' => ['required', 'integer'],
            'community_id' => ['required', 'integer', Rule::exists('communities', 'id')->where('status', 'verified')],
            'expires_at' => ['required', 'date', 'after:now'],
        ]);

        $category = $event->categories()->findOrFail($data['event_category_id']);
        if ($category->registration_flow !== 'by_invitation') {
            return back()->withErrors(['event_category_id' => 'Kategori tidak menggunakan alur undangan.']);
        }

        $token = $invitations->issue($category, (int) $data['community_id'], (int) $request->user()->id, $data['expires_at']);

        return back()->with('success', 'Undangan diterbitkan. Salin token ini sekarang; hanya ditampilkan sekali.')->with('invitation_token', $token);
    }
}

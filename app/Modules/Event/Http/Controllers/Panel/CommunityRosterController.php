<?php

declare(strict_types=1);

namespace App\Modules\Event\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Modules\Community\Models\CommunityOfficial;
use App\Modules\Event\Models\EventParticipant;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class CommunityRosterController extends Controller
{
    public function show(EventParticipant $participant): View
    {
        $this->authorizeParticipantAccess($participant);

        $participant->loadMissing([
            'event',
            'category',
            'members.user',
            'paymentVerifiedBy',
        ]);

        return view('panel.registrations.roster', compact('participant'));
    }

    private function authorizeParticipantAccess(EventParticipant $participant): void
    {
        $userId = Auth::id();
        if ($participant->user_id === $userId || Auth::user()?->hasRole('superadmin')) {
            return;
        }

        // Atlet dalam roster juga boleh melihat roster (PRD_EXTERNAL §4.3 D)
        $isMember = $participant->members()->where('user_id', $userId)->exists();
        if ($isMember) {
            return;
        }

        if ($participant->community_id) {
            $isOfficial = CommunityOfficial::query()
                ->where('community_id', $participant->community_id)
                ->where('user_id', $userId)
                ->where('status', 'active')
                ->whereNull('ended_at')
                ->exists();

            if ($isOfficial) {
                return;
            }
        }

        abort(403, 'Anda tidak memiliki hak akses pada berkas pendaftaran ini.');
    }
}

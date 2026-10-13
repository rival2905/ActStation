<?php

declare(strict_types=1);

namespace App\Modules\Event\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Modules\Event\Models\EventParticipant;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class MyEventController extends Controller
{
    public function index(): View
    {
        $userId = (int) Auth::id();

        // Cari partisipasi sebagai PIC atau sebagai anggota roster
        $participants = EventParticipant::query()
            ->where(function ($q) use ($userId): void {
                $q->where('user_id', $userId)
                    ->orWhereHas('members', fn ($mq) => $mq->where('user_id', $userId));
            })
            ->with(['event', 'category', 'members.user'])
            ->latest('registered_at')
            ->get();

        return view('public.my.events', compact('participants'));
    }
}

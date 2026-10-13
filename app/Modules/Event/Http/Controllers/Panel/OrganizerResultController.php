<?php

declare(strict_types=1);

namespace App\Modules\Event\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Modules\Event\Models\Event;
use App\Modules\Event\Models\Game;
use App\Modules\Event\Services\Tournament\GameResultFinalizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class OrganizerResultController extends Controller
{
    public function __construct(
        private readonly GameResultFinalizer $resultFinalizer,
    ) {}

    public function index(Event $event): View
    {
        $this->authorizeOrganizer($event);

        $games = Game::query()
            ->where('event_id', $event->id)
            ->with(['category', 'teamA', 'teamB'])
            ->orderBy('game_time')
            ->get();

        $workspace = request()->routeIs('admin.*') ? 'admin' : 'organizer';

        return view('panel.organizer.results.index', compact('event', 'games', 'workspace'));
    }

    public function recordScore(Request $request, Event $event, Game $game): RedirectResponse
    {
        $this->authorizeOrganizer($event);

        abort_unless((int) $game->event_id === (int) $event->id, 404);

        $validated = $request->validate([
            'score_a' => ['required', 'integer', 'min:0'],
            'score_b' => ['required', 'integer', 'min:0'],
            'finalize' => ['nullable', 'boolean'],
        ]);

        $scoreA = (int) $validated['score_a'];
        $scoreB = (int) $validated['score_b'];

        if ($request->boolean('finalize')) {
            try {
                $this->resultFinalizer->finalize($game, $scoreA, $scoreB);

                return back()->with('success', "Skor pertandingan resmi difinalisasi: {$scoreA} - {$scoreB}. Tim pemenang otomatis dimajukan ke node berikutnya.");
            } catch (\Throwable $e) {
                return back()->with('error', $e->getMessage());
            }
        }

        $game->update([
            'score_a' => $scoreA,
            'score_b' => $scoreB,
            'game_status' => 'live',
        ]);

        return back()->with('success', "Skor live pertandingan berhasil diperbarui: {$scoreA} - {$scoreB}.");
    }

    private function authorizeOrganizer(Event $event): void
    {
        if (! request()->routeIs('admin.*') && $event->user_id !== Auth::id() && ! Auth::user()?->hasRole('superadmin')) {
            abort(403, 'Akses ditolak.');
        }
    }
}

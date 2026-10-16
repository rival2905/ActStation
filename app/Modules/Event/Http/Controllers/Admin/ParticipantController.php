<?php

declare(strict_types=1);

namespace App\Modules\Event\Http\Controllers\Admin;

use App\Contracts\CommunityLookup;
use App\Contracts\RosterProvider;
use App\Http\Controllers\Controller;
use App\Modules\Event\Exceptions\RegistrationGuardException;
use App\Modules\Event\Exceptions\RosterValidationException;
use App\Modules\Event\Http\Requests\Admin\StoreParticipantRequest;
use App\Modules\Event\Http\Requests\Admin\StoreRosterRequest;
use App\Modules\Event\Http\Requests\Admin\UpdateParticipantRequest;
use App\Modules\Event\Http\Requests\Admin\UpdateParticipantStatusRequest;
use App\Modules\Event\Models\Event;
use App\Modules\Event\Models\EventParticipant;
use App\Modules\Event\Services\RegistrationGuard;
use App\Modules\Event\Services\RosterValidator;
use App\Modules\Payment\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ParticipantController extends Controller
{
    public function index(Request $request, Event $event): View
    {
        $query = $event->participants()->with(['category', 'members.user']);

        if ($request->filled('category_id')) {
            $query->where('event_category_id', $request->input('category_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('search')) {
            $search = (string) $request->input('search');
            $query->where(function ($q) use ($search): void {
                $q->where('team_name', 'like', "%{$search}%")
                    ->orWhere('contact_person', 'like', "%{$search}%");
            });
        }

        $participants = $query->latest()->paginate(15)->withQueryString();
        $categories = $event->categories;
        $communities = DB::table('communities')
            ->where('status', 'verified')
            ->get(['id', 'name']);

        return view('admin.events.participants.index', compact('event', 'participants', 'categories', 'communities'));
    }

    public function store(
        StoreParticipantRequest $request,
        Event $event,
        RegistrationGuard $guard,
        CommunityLookup $communities
    ): RedirectResponse {
        $category = $event->categories()->findOrFail($request->validated('event_category_id'));
        $communityId = (int) $request->validated('community_id');
        $teamName = (string) $request->validated('team_name');
        $community = $communities->find($communityId);
        if ($category->registration_flow === 'by_invitation' || ((float) $category->registration_fee > 0 && $category->registration_flow === 'payment_first')) {
            return back()->withInput()->with('error', 'Pendaftaran kategori berbayar/undangan harus melalui jalur peserta agar undangan dan invoice diverifikasi.');
        }

        try {
            DB::transaction(function () use ($event, $category, $communityId, $community, $teamName, $guard, $request): void {
                $lockedEvent = Event::query()->whereKey($event->id)->lockForUpdate()->firstOrFail();
                $lockedCategory = $lockedEvent->categories()->whereKey($category->id)->lockForUpdate()->firstOrFail();
                $guard->validate($lockedEvent, $lockedCategory, $communityId, $teamName, registrationSource: $community?->subtype === 'club' ? 'club' : 'community', communitySubtype: $community?->subtype);

                $data = $request->validated();
                $data['event_id'] = $event->id;
                $data['registered_at'] = Carbon::now();
                $data['status'] = 'registered';
                EventParticipant::create($data);
            });

            return back()->with('success', 'Peserta berhasil didaftarkan.');
        } catch (RegistrationGuardException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function update(
        UpdateParticipantRequest $request,
        Event $event,
        EventParticipant $participant,
        RegistrationGuard $guard,
        CommunityLookup $communities
    ): RedirectResponse {
        abort_unless((int) $participant->event_id === (int) $event->id, 404);

        $teamName = (string) $request->validated('team_name');
        $community = $participant->community_id !== null ? $communities->find((int) $participant->community_id) : null;

        try {
            $guard->validate(
                $event,
                $participant->category,
                $participant->community_id !== null ? (int) $participant->community_id : null,
                $teamName,
                $participant,
                registrationSource: $community?->subtype === 'club' ? 'club' : 'community',
                communitySubtype: $community?->subtype,
                invitationAuthorized: $participant->category?->registration_flow === 'by_invitation'
            );

            $participant->update($request->validated());

            return back()->with('success', 'Data peserta berhasil diperbarui.');
        } catch (RegistrationGuardException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function updateStatus(
        UpdateParticipantStatusRequest $request,
        Event $event,
        EventParticipant $participant
    ): RedirectResponse {
        abort_unless((int) $participant->event_id === (int) $event->id, 404);

        $status = $request->validated('status');
        if ($status === 'confirmed') {
            return back()->with('error', 'Konfirmasi wajib melalui verifikasi roster dan berkas tim.');
        }
        if ($status === 'attended' && $participant->status !== 'confirmed') {
            return back()->with('error', 'Hanya tim terkonfirmasi boleh dicatat hadir.');
        }
        if ($status === 'registered' && $participant->status !== 'registered') {
            return back()->with('error', 'Status pembayaran dan verifikasi tidak boleh direset langsung.');
        }
        $participant->update(['status' => $status]);

        return back()->with('success', 'Status peserta berhasil diperbarui.');
    }

    public function destroy(Event $event, EventParticipant $participant): RedirectResponse
    {
        abort_unless((int) $participant->event_id === (int) $event->id, 404);
        $hasPaidTransaction = Transaction::query()
            ->where('payable_type', 'event_participant')
            ->where('payable_id', $participant->id)
            ->where('status', 'paid')
            ->exists();

        if ($hasPaidTransaction || $participant->payment_verified_at !== null) {
            return back()->with('error', 'Peserta tidak dapat dihapus karena sudah memiliki pembayaran yang lunas (status paid).');
        }

        $inPool = $participant->poolTeams()->exists();
        $inGames = $participant->homeGames()->exists() || $participant->awayGames()->exists();
        if ($inPool || $inGames) {
            return back()->with('error', 'Peserta tidak dapat dihapus karena sudah masuk dalam jadwal pertandingan atau bagan turnamen.');
        }

        DB::transaction(function () use ($participant): void {
            Transaction::query()
                ->where('payable_type', 'event_participant')
                ->where('payable_id', $participant->id)
                ->whereIn('status', ['pending', 'expired'])
                ->delete();

            $participant->delete();
        });

        return back()->with('success', 'Peserta berhasil dihapus.');
    }

    public function roster(
        Event $event,
        EventParticipant $participant,
        RosterProvider $rosterProvider
    ): View {
        abort_unless((int) $participant->event_id === (int) $event->id, 404);

        $participant->load(['category', 'members.user']);
        $category = $participant->category;

        $eligibleAthletes = $participant->community_id !== null
            ? $rosterProvider->eligibleAthletes((int) $participant->community_id, (int) $participant->event_category_id)
            : collect();

        return view('admin.events.participants.roster', compact('event', 'participant', 'category', 'eligibleAthletes'));
    }

    public function updateRoster(
        StoreRosterRequest $request,
        Event $event,
        EventParticipant $participant,
        RosterValidator $validator
    ): RedirectResponse {
        abort_unless((int) $participant->event_id === (int) $event->id, 404);
        abort_if($participant->status === 'confirmed', 403, 'Roster yang sudah terkonfirmasi tidak dapat diubah.');

        $members = (array) $request->validated('members');

        try {
            $validator->validate($participant, $members);

            DB::transaction(function () use ($participant, $members): void {
                $lockedParticipant = EventParticipant::query()
                    ->where('id', $participant->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                abort_if($lockedParticipant->status === 'confirmed', 403, 'Roster yang sudah terkonfirmasi tidak dapat diubah.');

                $lockedParticipant->members()->delete();

                foreach ($members as $memberData) {
                    $lockedParticipant->members()->create([
                        'user_id' => (int) $memberData['user_id'],
                        'team_role' => $memberData['team_role'] ?? 'player',
                        'jersey_number' => isset($memberData['jersey_number']) && $memberData['jersey_number'] !== ''
                            ? (int) $memberData['jersey_number']
                            : null,
                        'is_captain' => ! empty($memberData['is_captain']),
                        'position' => $memberData['position'] ?? null,
                    ]);
                }
            });

            return back()->with('success', 'Roster peserta berhasil disimpan.');
        } catch (RosterValidationException $e) {
            return back()->withInput()->withErrors($e->errors)->with('error', $e->getMessage());
        }
    }
}

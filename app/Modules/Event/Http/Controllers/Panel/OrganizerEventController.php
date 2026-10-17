<?php

declare(strict_types=1);

namespace App\Modules\Event\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Event\Exceptions\InvalidLifecycleTransitionException;
use App\Modules\Event\Http\Requests\Panel\OrganizerEventRequest;
use App\Modules\Event\Models\Event;
use App\Modules\Event\Services\EventLifecycleService;
use App\Modules\Event\Services\EventVenueService;
use App\Modules\MasterData\Models\SportCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class OrganizerEventController extends Controller
{
    public function __construct(
        private readonly EventLifecycleService $lifecycleService,
    ) {}

    public function index(): View
    {
        $userId = Auth::id();

        // Panel organizer "Kelola Event Saya" hanya menampilkan event milik user yang login
        // Manajemen seluruh event dilakukan via Admin Back Office (/admin/events)
        $events = Event::query()
            ->where('user_id', $userId)
            ->with(['categories', 'venue'])
            ->latest()
            ->paginate(10);

        return view('panel.organizer.events.index', compact('events'));
    }

    public function create(): View|RedirectResponse
    {
        /** @var User $user */
        $user = Auth::user();

        // Just-In-Time KYC Check (PRD_EXTERNAL §2.1 & §3.1)
        if (! $user->hasRole('organizer') || $user->kyc_status !== 'verified' || empty($user->bank_account_number)) {
            return redirect()
                ->route('panel.organizer.apply')
                ->with('warning', 'Lengkapi profil identitas PIC & rekening payout sebelum membuat turnamen baru.');
        }

        $venueData = app(EventVenueService::class)->formData(isset($event) ? $event : null);
        $sports = SportCategory::query()->where('is_active', true)->get();

        return view('panel.organizer.events.create', array_merge(compact('sports'), $venueData));
    }

    public function store(OrganizerEventRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = Auth::user();

        if (! $user->hasRole('organizer') || $user->kyc_status !== 'verified' || empty($user->bank_account_number)) {
            abort(403, 'Akses ditolak. Profil penyelenggara belum diverifikasi admin.');
        }

        $validated = $request->validated();

        $slug = Str::slug($validated['title']).'-'.Str::lower(Str::random(5));
        $sportCategoryId = $validated['sport_category_id'] ?? (SportCategory::query()->where('slug', 'basketball')->value('id') ?? 1);

        $eventData = [
            'sport_category_id' => (int) $sportCategoryId,
            'venue_id' => ($validated['venue_ids'][0] ?? $validated['venue_id'] ?? null),
            'user_id' => Auth::id(),
            'title' => $validated['title'],
            'slug' => $slug,
            'description' => $validated['description'] ?? null,
            'status' => 'draft',
            'registration_open' => $request->boolean('registration_open'),
            'max_participants' => $validated['max_participants'] ?? null,
            'starts_at' => $validated['starts_at'],
            'ends_at' => $validated['ends_at'],
            'registration_deadline' => $validated['registration_deadline'] ?? null,
            'allow_manual_payment' => $request->boolean('allow_manual_payment'),
            'manual_bank_name' => $validated['manual_bank_name'] ?? null,
            'manual_bank_account_number' => $validated['manual_bank_account_number'] ?? null,
            'manual_bank_account_holder' => $validated['manual_bank_account_holder'] ?? null,
            'manual_payment_instructions' => $validated['manual_payment_instructions'] ?? null,
        ];

        if ($request->hasFile('banner_image')) {
            $eventData['banner_image'] = $request->file('banner_image')->store('event_banners', 'public');
        }

        if ($request->hasFile('venue_approval_doc')) {
            $eventData['venue_approval_doc'] = $request->file('venue_approval_doc')->store('venue_approvals', 'public');
        }

        $event = DB::transaction(function () use ($eventData, $validated): Event {
            $eventData['venue_id'] = null;
            $event = Event::create($eventData);
            app(EventVenueService::class)->sync($event, $validated['venue_ids'] ?? [$validated['venue_id']], $validated['venue_court_ids'] ?? []);

            return $event;
        });

        return redirect()
            ->route('panel.organizer.events.manage', $event->id)
            ->with('success', 'Draf turnamen berhasil dibuat! Silakan tambahkan kategori kompetisi.');
    }

    public function edit(Event $event): View
    {
        $this->authorizeOrganizer($event);

        $venueData = app(EventVenueService::class)->formData(isset($event) ? $event : null);
        $sports = SportCategory::query()->where('is_active', true)->get();

        return view('panel.organizer.events.edit', array_merge(compact('event', 'sports'), $venueData));
    }

    public function update(OrganizerEventRequest $request, Event $event): RedirectResponse
    {
        $this->authorizeOrganizer($event);

        $validated = $request->validated();

        $this->ensureCategoryWindowsRemainValid($event, $validated);

        $updateData = [
            'sport_category_id' => (int) ($validated['sport_category_id'] ?? $event->sport_category_id),
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'registration_open' => $request->boolean('registration_open'),
            'max_participants' => $validated['max_participants'] ?? null,
            'starts_at' => $validated['starts_at'],
            'ends_at' => $validated['ends_at'],
            'registration_deadline' => $validated['registration_deadline'] ?? null,
            'allow_manual_payment' => $request->boolean('allow_manual_payment'),
            'manual_bank_name' => $validated['manual_bank_name'] ?? null,
            'manual_bank_account_number' => $validated['manual_bank_account_number'] ?? null,
            'manual_bank_account_holder' => $validated['manual_bank_account_holder'] ?? null,
            'manual_payment_instructions' => $validated['manual_payment_instructions'] ?? null,
        ];

        if ($request->hasFile('banner_image')) {
            $updateData['banner_image'] = $request->file('banner_image')->store('event_banners', 'public');
        }

        if ($request->hasFile('venue_approval_doc')) {
            $updateData['venue_approval_doc'] = $request->file('venue_approval_doc')->store('venue_approvals', 'public');
        }

        DB::transaction(function () use ($event, $updateData, $validated): void {
            $venueIds = $validated['venue_ids'] ?? [$validated['venue_id']];
            $courtIds = $validated['venue_court_ids'] ?? null;
            if (isset($validated['venue_selection_present'])) {
                $courtIds ??= [];
            }
            app(EventVenueService::class)->sync($event, $venueIds, $courtIds);
            $event->update($updateData);
        });

        return redirect()
            ->route('panel.organizer.events.manage', $event->id)
            ->with('success', 'Parameter turnamen berhasil diperbarui.');
    }

    public function submitReview(Event $event): RedirectResponse
    {
        $this->authorizeOrganizer($event);

        try {
            $this->lifecycleService->submitReview($event);

            return redirect()
                ->route('panel.organizer.events.manage', $event->id)
                ->with('success', 'Turnamen berhasil diajukan untuk moderasi admin internal.');
        } catch (InvalidLifecycleTransitionException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function ensureCategoryWindowsRemainValid(Event $event, array $validated): void
    {
        $registrationDeadline = isset($validated['registration_deadline'])
            ? Carbon::parse($validated['registration_deadline'])
            : null;
        $startsAt = Carbon::parse($validated['starts_at']);
        $latestRegistrationEnd = $event->categories()->whereNotNull('registration_end')->max('registration_end');
        $latestPaymentEnd = $event->categories()->whereNotNull('payment_end')->max('payment_end');
        $latestCategoryEnd = collect([$latestRegistrationEnd, $latestPaymentEnd])->filter()->max();
        $errors = [];

        if ($registrationDeadline !== null && $latestRegistrationEnd !== null && $registrationDeadline->lt($latestRegistrationEnd)) {
            $errors['registration_deadline'] = 'Batas akhir event tidak boleh lebih awal dari tutup pendaftaran kategori.';
        }

        if ($latestCategoryEnd !== null && $startsAt->lt($latestCategoryEnd)) {
            $errors['starts_at'] = 'Waktu mulai event tidak boleh lebih awal dari tutup pendaftaran atau pembayaran kategori.';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function authorizeOrganizer(Event $event): void
    {
        if ($event->user_id !== Auth::id() && ! Auth::user()?->hasRole('superadmin')) {
            abort(403, 'Akses ditolak untuk event organizer lain.');
        }
    }
}

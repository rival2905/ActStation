<?php

declare(strict_types=1);

namespace App\Modules\Event\Http\Controllers\Public;

use App\Contracts\DevelopmentLevelLookup;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Event\Exceptions\RegistrationGuardException;
use App\Modules\Event\Exceptions\RosterValidationException;
use App\Modules\Event\Models\Event;
use App\Modules\Event\Models\EventCategory;
use App\Modules\Event\Models\EventParticipant;
use App\Modules\Event\Models\EventParticipantMember;
use App\Modules\Event\Services\RegistrationGuard;
use App\Modules\Event\Services\RosterValidator;
use App\Modules\Payment\Models\Transaction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PublicRegistrationController extends Controller
{
    public function __construct(
        private readonly RegistrationGuard $registrationGuard,
        private readonly RosterValidator $rosterValidator,
        private readonly DevelopmentLevelLookup $developmentLevelLookup,
    ) {}

    public function create(string $slug): View
    {
        $event = Event::query()
            ->where('slug', $slug)
            ->where('status', 'published')
            ->where('registration_open', true)
            ->with(['categories'])
            ->firstOrFail();

        return view('public.events.register', compact('event'));
    }

    public function storeIndividual(Request $request, string $slug): RedirectResponse
    {
        $event = Event::query()
            ->where('slug', $slug)
            ->where('status', 'published')
            ->where('registration_open', true)
            ->firstOrFail();

        $validated = $request->validate([
            'registration_source' => ['nullable', 'in:individual'],
            'event_category_id' => ['required', 'integer', 'exists:event_categories,id'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone_number' => ['required', 'string', 'max:20'],
            'identity_number' => ['required', 'string', 'max:30'],
            'birth_date' => ['required', 'date'],
            'gender' => ['required', 'in:male,female'],
            'jersey_number' => ['required', 'integer', 'between:0,99'],
        ]);

        /** @var EventCategory $category */
        $category = EventCategory::query()
            ->where('id', $validated['event_category_id'])
            ->where('event_id', $event->id)
            ->firstOrFail();

        if ($category->registration_flow === 'by_invitation') {
            return back()->withInput()->with('error', 'Kategori undangan hanya dapat didaftarkan oleh komunitas yang menerima undangan event.');
        }

        // 1. Resolve user berdasarkan identity_number (Anti-Fraud Dedup - PRD_EXTERNAL §4.3 B)
        $user = User::query()->where('identity_number', $validated['identity_number'])->first();
        if (! $user) {
            $user = User::query()->where('email', $validated['email'])->first();
        }

        if (! $user) {
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'phone_number' => $validated['phone_number'],
                'identity_number' => $validated['identity_number'],
                'birth_date' => $validated['birth_date'],
                'gender' => $validated['gender'],
                'password' => Hash::make(Str::random(16)),
                'is_active' => true,
            ]);
            $user->assignRole('athlete');
        } else {
            // Update NIK / birth_date jika belum ada
            if (! $user->identity_number) {
                $user->update([
                    'identity_number' => $validated['identity_number'],
                    'birth_date' => $validated['birth_date'],
                    'gender' => $validated['gender'],
                ]);
            }
        }

        if (! Auth::check()) {
            Auth::login($user);
        }

        $teamName = $user->name.' (Individu)';

        try {
            $this->registrationGuard->validate(
                event: $event,
                category: $category,
                communityId: null,
                teamName: $teamName,
                registrationSource: $validated['registration_source'] ?? 'individual',
            );
        } catch (RegistrationGuardException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        try {
            $participant = DB::transaction(function () use ($event, $category, $user, $teamName, $validated): EventParticipant {
                Event::query()->whereKey($event->id)->lockForUpdate()->firstOrFail();
                /** @var EventCategory $lockedCategory */
                $lockedCategory = EventCategory::query()->whereKey($category->id)->lockForUpdate()->firstOrFail();
                $this->registrationGuard->validate(
                    event: $event,
                    category: $lockedCategory,
                    communityId: null,
                    teamName: $teamName,
                    registrationSource: $validated['registration_source'] ?? 'individual',
                );

                $fee = (float) $lockedCategory->registration_fee;
                $invoiceOnRegistration = $fee > 0 && $lockedCategory->registration_flow === 'payment_first';
                $participant = EventParticipant::create([
                    'event_id' => $event->id,
                    'event_category_id' => $lockedCategory->id,
                    'registrant_type' => 'individual',
                    'user_id' => $user->id,
                    'team_name' => $teamName,
                    'contact_person' => $user->name,
                    'contact_phone' => $user->phone_number,
                    'status' => $invoiceOnRegistration ? 'pending_payment' : 'registered',
                    'payment_deadline_at' => $invoiceOnRegistration ? $lockedCategory->payment_end : null,
                    'registered_at' => now(),
                ]);

                $members = [[
                    'user_id' => $user->id,
                    'jersey_number' => (int) $validated['jersey_number'],
                    'is_captain' => 1,
                    'position' => 'Guard',
                    'team_role' => 'player',
                ]];
                $this->rosterValidator->validate($participant, $members);

                EventParticipantMember::create([
                    'event_participant_id' => $participant->id,
                    'user_id' => $user->id,
                    'jersey_number' => (int) $validated['jersey_number'],
                    'is_captain' => true,
                    'position' => 'Guard',
                    'team_role' => 'player',
                ]);

                if ($invoiceOnRegistration) {
                    Transaction::create([
                        'user_id' => $user->id,
                        'invoice_number' => 'INV-IND-'.strtoupper(Str::random(8)),
                        'type' => 'registration',
                        'payable_type' => 'event_participant',
                        'payable_id' => $participant->id,
                        'amount' => $lockedCategory->registration_fee,
                        'fee' => 0.00,
                        'total' => $lockedCategory->registration_fee,
                        'status' => 'pending',
                        'expired_at' => $lockedCategory->payment_end,
                    ]);
                }

                return $participant;
            });
        } catch (RegistrationGuardException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        } catch (RosterValidationException $e) {
            return back()->withInput()->withErrors($e->errors())->with('error', 'Validasi syarat kategori individu gagal.');
        }

        if ($participant->status === 'pending_payment') {
            return redirect()
                ->route('panel.community-events.checkout', $participant->id)
                ->with('success', 'Pendaftaran individu berhasil dibuat! Silakan selesaikan pembayaran.');
        }

        return redirect()
            ->route('my-events.index')
            ->with('success', 'Pendaftaran perorangan berhasil dikirim untuk verifikasi panitia.');
    }
}

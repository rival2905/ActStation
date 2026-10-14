<?php

declare(strict_types=1);

use App\Contracts\EmailDelivery;
use App\Contracts\Events\PaymentSucceeded;
use App\Models\User;
use App\Modules\Event\Exceptions\RegistrationGuardException;
use App\Modules\Event\Models\Event;
use App\Modules\Event\Models\EventCategory;
use App\Modules\Event\Models\EventParticipant;
use App\Modules\Event\Services\RegistrationGuard;
use App\Modules\Payment\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

class FakeEmailDelivery implements EmailDelivery
{
    /** @var list<array<string, mixed>> */
    public array $sent = [];

    public bool $shouldThrow = false;

    public function send(array $message): int|string
    {
        if ($this->shouldThrow) {
            throw new RuntimeException('Simulated n8n email delivery outage');
        }

        $this->sent[] = $message;

        return count($this->sent);
    }
}

beforeEach(function (): void {
    $this->fakeDelivery = new FakeEmailDelivery;
    app()->instance(EmailDelivery::class, $this->fakeDelivery);

    $sportId = DB::table('sport_categories')->insertGetId([
        'name' => 'Bola Basket',
        'slug' => 'bola-basket',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->organizer = User::factory()->create([
        'name' => 'Organizer Turnamen',
        'email' => 'organizer@actstation.test',
        'kyc_status' => 'verified',
        'bank_name' => 'BCA',
        'bank_account_number' => '1234567890',
        'is_active' => true,
    ]);

    $this->registrant = User::factory()->create([
        'name' => 'Manajer Tim',
        'email' => 'manager@club.test',
        'is_active' => true,
    ]);

    $this->event = Event::create([
        'user_id' => $this->organizer->id,
        'title' => 'Kejuaraan Basket Surabaya Cup 2026',
        'slug' => 'kejuaraan-basket-surabaya-cup-2026',
        'sport_category_id' => $sportId,
        'status' => 'published',
        'allow_manual_payment' => true,
        'manual_bank_name' => 'BCA',
        'manual_bank_account_number' => '1234567890',
        'starts_at' => Carbon::now()->addDays(10),
        'ends_at' => Carbon::now()->addDays(14),
    ]);

    $this->category = EventCategory::create([
        'event_id' => $this->event->id,
        'name' => 'Divisi Utama Putra',
        'slug' => 'divisi-utama-putra',
        'registration_fee' => '1500000.00',
        'registration_flow' => 'screening_first',
        'payment_time_limit_hours' => 24,
        'max_teams' => 16,
        'min_team_members' => 5,
        'max_team_members' => 12,
        'payment_start' => Carbon::now()->subDays(1),
        'payment_end' => Carbon::now()->addDays(5),
    ]);
});

test('creation aftercommit sends email and rollback sends no email', function (): void {
    // 1. Transaction that commits -> email sent
    DB::transaction(function (): void {
        EventParticipant::create([
            'event_id' => $this->event->id,
            'event_category_id' => $this->category->id,
            'user_id' => $this->registrant->id,
            'registrant_type' => 'community',
            'team_name' => 'Elang Sakti Surabaya',
            'contact_person' => 'Budi Santoso',
            'contact_phone' => '081234567890',
            'status' => 'registered',
        ]);
    });

    expect($this->fakeDelivery->sent)->toHaveCount(1);
    $mail = $this->fakeDelivery->sent[0];
    expect($mail['recipient_email'])->toBe('manager@club.test')
        ->and($mail['recipient_user_id'])->toBe($this->registrant->id)
        ->and($mail['subject'])->toContain('Kejuaraan Basket Surabaya Cup 2026')
        ->and($mail['subject'])->toContain('Elang Sakti Surabaya')
        ->and($mail['body'])->toContain('Elang Sakti Surabaya')
        ->and($mail['body'])->toContain('Divisi Utama Putra')
        ->and($mail['body'])->toContain('registered')
        ->and($mail['action_url'])->toBe(route('my-events.index'));

    // 2. Transaction that rolls back -> NO email sent
    $this->fakeDelivery->sent = [];

    try {
        DB::transaction(function (): void {
            EventParticipant::create([
                'event_id' => $this->event->id,
                'event_category_id' => $this->category->id,
                'user_id' => $this->registrant->id,
                'registrant_type' => 'community',
                'team_name' => 'Tim Dibatalkan',
                'contact_person' => 'Budi Santoso',
                'contact_phone' => '081234567890',
                'status' => 'registered',
            ]);

            throw new RuntimeException('Force rollback');
        });
    } catch (RuntimeException $e) {
        // Rollback happened
    }

    expect($this->fakeDelivery->sent)->toBeEmpty();
});

test('revision needed and revision notes update trigger revision emails', function (): void {
    $participant = EventParticipant::create([
        'event_id' => $this->event->id,
        'event_category_id' => $this->category->id,
        'user_id' => $this->registrant->id,
        'registrant_type' => 'community',
        'team_name' => 'Garuda Perkasa',
        'contact_person' => 'Andi Wijaya',
        'contact_phone' => '081122334455',
        'status' => 'registered',
    ]);

    $this->fakeDelivery->sent = [];

    // Update to revision_needed with notes
    $participant->update([
        'status' => 'revision_needed',
        'revision_notes' => 'Foto KTP kapten buram, silakan unggah ulang.',
    ]);

    expect($this->fakeDelivery->sent)->toHaveCount(1);
    $mail = $this->fakeDelivery->sent[0];
    expect($mail['subject'])->toContain('Perbaikan Berkas Pendaftaran')
        ->and($mail['body'])->toContain('Foto KTP kapten buram')
        ->and($mail['body'])->toContain('revision_needed')
        ->and($mail['action_url'])->toBe(route('my-events.index'));

    // Updating revision notes while staying in revision_needed triggers new email
    $this->fakeDelivery->sent = [];
    $participant->update([
        'revision_notes' => 'Surat mandat resmi klub belum ditandatangani.',
    ]);

    expect($this->fakeDelivery->sent)->toHaveCount(1);
    expect($this->fakeDelivery->sent[0]['body'])->toContain('Surat mandat resmi klub belum ditandatangani');
});

test('screening approved triggers invoice email for fee and confirmed for free category', function (): void {
    $participant = EventParticipant::create([
        'event_id' => $this->event->id,
        'event_category_id' => $this->category->id,
        'user_id' => $this->registrant->id,
        'registrant_type' => 'community',
        'team_name' => 'Ksatria Muda',
        'contact_person' => 'Dewi',
        'contact_phone' => '081298765432',
        'status' => 'registered',
    ]);

    $this->fakeDelivery->sent = [];

    // 1. Screening approved with fee -> pending_payment with invoice & deadline
    $deadline = Carbon::now()->addHours(24);
    DB::transaction(function () use ($participant, $deadline): void {
        $participant->update([
            'status' => 'pending_payment',
            'payment_deadline_at' => $deadline,
            'revision_notes' => null,
        ]);

        Transaction::create([
            'user_id' => $this->registrant->id,
            'payable_type' => 'event_participant',
            'payable_id' => $participant->id,
            'invoice_number' => 'INV-TEST-001',
            'type' => 'registration',
            'amount' => 1500000.00,
            'fee' => 0.00,
            'total' => 1500000.00,
            'status' => 'pending',
            'expired_at' => $deadline,
        ]);
    });

    expect($this->fakeDelivery->sent)->toHaveCount(1);
    $mail = $this->fakeDelivery->sent[0];
    expect($mail['subject'])->toContain('Tagihan Pembayaran Pendaftaran')
        ->and($mail['body'])->toContain('INV-TEST-001')
        ->and($mail['body'])->toContain('1.500.000')
        ->and($mail['body'])->toContain('pending_payment')
        ->and($mail['action_url'])->toBe(route('panel.community-events.checkout', $participant->id))
        ->and($mail['action_label'])->toBe('Bayar Pendaftaran');

    // 2. Screening approved for free category -> confirmed directly
    $freeCategory = EventCategory::create([
        'event_id' => $this->event->id,
        'name' => 'Divisi Komunitas Gratis',
        'slug' => 'divisi-komunitas-gratis',
        'registration_fee' => '0.00',
        'registration_flow' => 'screening_first',
        'max_teams' => 8,
        'min_team_members' => 3,
        'max_team_members' => 5,
    ]);

    $freeParticipant = EventParticipant::create([
        'event_id' => $this->event->id,
        'event_category_id' => $freeCategory->id,
        'user_id' => $this->registrant->id,
        'registrant_type' => 'community',
        'team_name' => 'Bintang Terang Gratis',
        'contact_person' => 'Rian',
        'contact_phone' => '081299998888',
        'status' => 'registered',
    ]);

    $this->fakeDelivery->sent = [];

    $freeParticipant->update([
        'status' => 'confirmed',
        'revision_notes' => null,
    ]);

    expect($this->fakeDelivery->sent)->toHaveCount(1);
    $freeMail = $this->fakeDelivery->sent[0];
    expect($freeMail['subject'])->toContain('Pendaftaran Terkonfirmasi')
        ->and($freeMail['body'])->toContain('confirmed')
        ->and($freeMail['body'])->toContain('Roster Lock')
        ->and($freeMail['action_url'])->toBe(route('my-events.index'));
});

test('manual and online payment triggers payment_verified email without duplicate status email', function (): void {
    // 1. Online payment via PaymentSucceeded event & HandleRegistrationPaymentSucceeded
    $participantOnline = EventParticipant::create([
        'event_id' => $this->event->id,
        'event_category_id' => $this->category->id,
        'user_id' => $this->registrant->id,
        'registrant_type' => 'community',
        'team_name' => 'Tim Pembayar Online',
        'contact_person' => 'Hendra',
        'contact_phone' => '081233344455',
        'status' => 'pending_payment',
        'payment_deadline_at' => Carbon::now()->addHours(24),
    ]);

    $transactionOnline = Transaction::create([
        'user_id' => $this->registrant->id,
        'payable_type' => 'event_participant',
        'payable_id' => $participantOnline->id,
        'invoice_number' => 'INV-ONLINE-001',
        'type' => 'registration',
        'amount' => 1500000.00,
        'fee' => 0.00,
        'total' => 1500000.00,
        'status' => 'paid',
        'paid_at' => Carbon::now(),
        'expired_at' => Carbon::now()->addHours(24),
    ]);

    $this->fakeDelivery->sent = [];

    // Trigger PaymentSucceeded domain event
    event(new PaymentSucceeded(
        transactionId: $transactionOnline->id,
        payableType: 'event_participant',
        payableId: $participantOnline->id,
        amount: '1500000.00'
    ));

    // Must emit exactly 1 email (payment_verified has highest priority, confirmed status is suppressed)
    expect($this->fakeDelivery->sent)->toHaveCount(1);
    $onlineMail = $this->fakeDelivery->sent[0];
    expect($onlineMail['subject'])->toContain('Pembayaran Terverifikasi')
        ->and($onlineMail['body'])->toContain('payment_verified')
        ->and($onlineMail['action_url'])->toBe(route('my-events.index'));

    // 2. Manual payment confirmation
    $participantManual = EventParticipant::create([
        'event_id' => $this->event->id,
        'event_category_id' => $this->category->id,
        'user_id' => $this->registrant->id,
        'registrant_type' => 'community',
        'team_name' => 'Tim Pembayar Manual',
        'contact_person' => 'Dedi',
        'contact_phone' => '081244455566',
        'status' => 'pending_payment',
        'payment_channel' => 'manual_organizer',
        'payment_proof' => 'manual_proofs/sample.jpg',
        'payment_deadline_at' => Carbon::now()->addHours(24),
    ]);

    $this->fakeDelivery->sent = [];

    $paidAt = Carbon::now();
    $participantManual->update([
        'status' => 'confirmed',
        'payment_verified_at' => $paidAt,
        'payment_verified_by' => $this->organizer->id,
        'revision_notes' => null,
    ]);

    // Must emit exactly 1 email (payment_verified)
    expect($this->fakeDelivery->sent)->toHaveCount(1);
    $manualMail = $this->fakeDelivery->sent[0];
    expect($manualMail['subject'])->toContain('Pembayaran Terverifikasi')
        ->and($manualMail['body'])->toContain('payment_verified');
});

test('deleted participant sends cancellation email with safe public event link', function (): void {
    $participant = EventParticipant::create([
        'event_id' => $this->event->id,
        'event_category_id' => $this->category->id,
        'user_id' => $this->registrant->id,
        'registrant_type' => 'community',
        'team_name' => 'Tim Akan Dihapus',
        'contact_person' => 'Agus',
        'contact_phone' => '081277788899',
        'status' => 'registered',
    ]);

    $this->fakeDelivery->sent = [];

    $participant->delete();

    expect($this->fakeDelivery->sent)->toHaveCount(1);
    $mail = $this->fakeDelivery->sent[0];
    expect($mail['subject'])->toContain('Pembatalan Pendaftaran')
        ->and($mail['body'])->toContain('Dibatalkan / Dihapus')
        ->and($mail['body'])->toContain('Tim Akan Dihapus')
        ->and($mail['action_url'])->toBe(route('events.show', $this->event->slug))
        ->and($mail['action_label'])->toBe('Lihat Event');
});

test('saving same participant again or updating unrelated fields emits no email', function (): void {
    $participant = EventParticipant::create([
        'event_id' => $this->event->id,
        'event_category_id' => $this->category->id,
        'user_id' => $this->registrant->id,
        'registrant_type' => 'community',
        'team_name' => 'Tim Stabil',
        'contact_person' => 'Agus',
        'contact_phone' => '081277788899',
        'status' => 'registered',
    ]);

    $this->fakeDelivery->sent = [];

    // 1. Save again with zero changes
    $participant->save();
    expect($this->fakeDelivery->sent)->toBeEmpty();

    // 2. Update unrelated fields (contact_phone, notes, waiver)
    $participant->update([
        'contact_phone' => '081299991111',
        'notes' => 'Catatan internal tim',
        'is_escrow_waived' => true,
    ]);
    expect($this->fakeDelivery->sent)->toBeEmpty();
});

test('registration guard failure emits no email and rejects registration', function (): void {
    // Draft / unpublished event cannot accept registrations
    $draftEvent = Event::create([
        'user_id' => $this->organizer->id,
        'title' => 'Event Draft Internal',
        'slug' => 'event-draft-internal',
        'sport_category_id' => $this->event->sport_category_id,
        'status' => 'draft',
    ]);

    $category = EventCategory::create([
        'event_id' => $draftEvent->id,
        'name' => 'Kategori Draft',
        'slug' => 'kategori-draft',
        'registration_fee' => '0.00',
        'max_teams' => 8,
        'min_team_members' => 3,
        'max_team_members' => 5,
    ]);

    $guard = app(RegistrationGuard::class);

    $this->fakeDelivery->sent = [];

    expect(fn () => $guard->validate($draftEvent, $category, null, 'Tim Baru'))
        ->toThrow(RegistrationGuardException::class);

    // No participant was created, no email was sent
    expect($this->fakeDelivery->sent)->toBeEmpty();
});

test('email delivery failure does not rollback participant or throw exception', function (): void {
    $this->fakeDelivery->shouldThrow = true;

    // Creating participant should NOT throw exception even when email delivery fails
    $participant = EventParticipant::create([
        'event_id' => $this->event->id,
        'event_category_id' => $this->category->id,
        'user_id' => $this->registrant->id,
        'registrant_type' => 'community',
        'team_name' => 'Tim Tangguh',
        'contact_person' => 'Agus',
        'contact_phone' => '081277788899',
        'status' => 'registered',
    ]);

    expect($participant->exists)->toBeTrue();
    expect(EventParticipant::whereKey($participant->id)->exists())->toBeTrue();
});

test('email payload contains no private vault NIK or athlete PII', function (): void {
    EventParticipant::create([
        'event_id' => $this->event->id,
        'event_category_id' => $this->category->id,
        'user_id' => $this->registrant->id,
        'registrant_type' => 'community',
        'team_name' => 'Tim Privasi Terjaga',
        'contact_person' => 'Agus',
        'contact_phone' => '081277788899',
        'status' => 'registered',
    ]);

    expect($this->fakeDelivery->sent)->toHaveCount(1);
    $payload = $this->fakeDelivery->sent[0];
    $body = $payload['body'];

    // Verify absence of sensitive tokens or PII fields
    expect($body)->not->toContain('nik')
        ->and($body)->not->toContain('NIK')
        ->and($body)->not->toContain('identity_number')
        ->and($body)->not->toContain('token')
        ->and($body)->not->toContain('password')
        ->and($payload['attachments'])->toBeEmpty();
});

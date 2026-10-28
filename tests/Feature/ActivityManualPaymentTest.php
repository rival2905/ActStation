<?php

declare(strict_types=1);

use App\Contracts\ActivityBilling;
use App\Contracts\EmailDelivery;
use App\Models\User;
use App\Modules\Event\Models\ActivityMember;
use App\Modules\Event\Models\ActivityRegistration;
use App\Modules\Event\Models\Event;
use App\Modules\Event\Models\EventActivity;
use App\Modules\Event\Services\ActivityPaymentService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

uses(DatabaseTransactions::class);

beforeEach(function (): void {
    Http::preventStrayRequests();
    $this->spy(EmailDelivery::class);
    Storage::fake('local');
    $this->payer = User::factory()->create(['is_active' => true]);
    $this->organizer = User::factory()->create(['is_active' => true]);
    $this->event = Event::factory()->create(['user_id' => $this->organizer->id, 'status' => 'published', 'allow_manual_payment' => true]);
    $this->activity = EventActivity::create(['event_id' => $this->event->id, 'name' => 'Fotografi', 'capacity' => 10]);
    $this->registration = ActivityRegistration::create(['event_activity_id' => $this->activity->id, 'user_id' => $this->payer->id, 'amount' => 75000, 'status' => 'pending_payment', 'request_key' => (string) Str::uuid()]);
    ActivityMember::create(['registration_id' => $this->registration->id, 'user_id' => $this->payer->id, 'accepted_at' => now()]);
    app(ActivityBilling::class)->invoice($this->payer->id, $this->registration->id, 75000);
});

test('manual activity payment enforces waiver stores private audit and confirms exact invoice', function (): void {
    $payments = app(ActivityPaymentService::class);
    $file = UploadedFile::fake()->image('bukti.png');
    expect(fn () => $payments->upload($this->event, $this->registration, $this->payer, ['waiver_agreed' => false, 'payment_proof' => $file], '127.0.0.1', 'test'))->toThrow(ValidationException::class);
    $payments->upload($this->event, $this->registration, $this->payer, ['waiver_agreed' => true, 'payment_proof' => $file], '127.0.0.1', 'test');
    $invoice = app(ActivityBilling::class)->find($this->registration->id);
    expect($invoice['meta']['is_escrow_waived'])->toBeTrue()->and($invoice['meta']['waiver_ip'])->toBe('127.0.0.1');
    Storage::disk('local')->assertExists($invoice['meta']['payment_proof']);
    $payments->confirm($this->event, $this->registration, $this->organizer);
    $payments->confirm($this->event, $this->registration->fresh(), $this->organizer);
    expect($this->registration->fresh()->status)->toBe('confirmed')->and(app(ActivityBilling::class)->paid($this->registration->id, 75000))->toBeTrue();
});

test('manual activity confirmation respects freshly disabled manual channel', function (): void {
    $payments = app(ActivityPaymentService::class);
    $payments->upload($this->event, $this->registration, $this->payer, ['waiver_agreed' => true, 'payment_proof' => UploadedFile::fake()->image('bukti.png')], '127.0.0.1', 'test');
    $this->event->update(['allow_manual_payment' => false]);
    expect(fn () => $payments->confirm($this->event, $this->registration, $this->organizer))->toThrow(ValidationException::class);
    expect(app(ActivityBilling::class)->paid($this->registration->id, 75000))->toBeFalse();
});

test('manual checkout renders warning locked bank and non escrow invoice watermark', function (): void {
    $role = Role::firstOrCreate(['name' => 'member', 'guard_name' => 'web'], ['panel_access' => 'external']);
    $this->payer->assignRole($role);
    $url = route('panel.activities.checkout', [$this->event, $this->registration]);
    $this->actingAs($this->payer)->get($url)->assertOk()->assertSee('activity-bank', false)->assertSee('disabled hidden', false)->assertSee('bukan escrow ActStation');
    app(ActivityPaymentService::class)->upload($this->event, $this->registration, $this->payer, ['waiver_agreed' => true, 'payment_proof' => UploadedFile::fake()->image('bukti.png')], '127.0.0.1', 'DEMO Browser');
    $this->get($url)->assertOk()->assertSee('NON-ESCROW — TRANSFER LANGSUNG KE PANITIA');
    $this->get(route('events.activities.index', $this->event->slug))->assertOk()->assertSee('NON-ESCROW');
});

test('organizer checkout offers confirmation only after proof is uploaded', function (): void {
    $role = Role::firstOrCreate(['name' => 'member', 'guard_name' => 'web'], ['panel_access' => 'external']);
    $this->organizer->assignRole($role);
    $url = route('panel.activities.checkout', [$this->event, $this->registration]);
    $confirmUrl = route('panel.activities.confirm', [$this->event, $this->registration]);
    $this->actingAs($this->organizer)->get($url)->assertOk()->assertDontSee($confirmUrl, false)->assertSee('Bukti transfer belum diunggah');
    app(ActivityPaymentService::class)->upload($this->event, $this->registration, $this->payer, ['waiver_agreed' => true, 'payment_proof' => UploadedFile::fake()->image('bukti.png')], '127.0.0.1', 'DEMO Browser');
    $this->get($url)->assertOk()->assertSee($confirmUrl, false);
});

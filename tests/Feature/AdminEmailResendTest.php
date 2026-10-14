<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Communication\Models\EmailMessage;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    Queue::fake();
    Http::preventStrayRequests();
    Config::set('services.n8n.email_enabled', true);
    Config::set('services.n8n.webhook_url', 'https://n8.fasekita.my.id/webhook/email');
    Config::set('services.n8n.webhook_secret', 'secret-token-abc');
    Config::set('services.n8n.allowed_host', 'n8.fasekita.my.id');
    Http::fake([
        'https://n8.fasekita.my.id/webhook/email' => Http::response(['success' => true], 200),
    ]);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');
    $this->admin->givePermissionTo('setting.email');
    $this->message = EmailMessage::create([
        'recipient' => ['email' => 'recipient@example.test', 'name' => 'Recipient'],
        'subject' => 'Informasi',
        'type' => 'information',
        'status' => 'submitted',
        'submitted_at' => now(),
        'payload' => ['body' => 'Pesan percobaan.', 'attachments' => []],
    ]);
});

test('admin sees resend action for submitted email and can confirm direct resend sequentially while in-flight processing is denied', function (): void {
    $this->actingAs($this->admin)->get(route('admin.emails.show', $this->message->id))
        ->assertOk()->assertSee('Kirim Ulang Manual');

    $this->postJson(route('admin.emails.retry', $this->message->id), ['confirm_resend' => 1])
        ->assertOk()
        ->assertJson([
            'success' => true,
            'status' => 'submitted',
        ]);

    expect($this->message->fresh()->status)->toBe('submitted')
        ->and($this->message->fresh()->submitted_at)->not->toBeNull();

    Queue::assertNothingPushed();

    // In-flight processing is denied with 409
    $this->message->update(['status' => 'processing']);
    $this->postJson(route('admin.emails.retry', $this->message->id), ['confirm_resend' => 1])
        ->assertStatus(409);

    // After previous synchronous resend completes as submitted, sequential resend with confirmation can legitimately succeed
    $this->message->update(['status' => 'submitted']);
    $this->postJson(route('admin.emails.retry', $this->message->id), ['confirm_resend' => 1])
        ->assertOk()
        ->assertJson(['success' => true, 'status' => 'submitted']);

    Queue::assertNothingPushed();
});

test('admin can resend legacy queued message directly without queue worker', function (): void {
    $queuedMessage = EmailMessage::create([
        'recipient' => ['email' => 'queued@example.test', 'name' => 'Queued'],
        'subject' => 'Pesan Tertunda',
        'type' => 'information',
        'status' => 'queued',
        'payload' => ['body' => 'Pesan belum dikirim.', 'attachments' => []],
    ]);

    $this->actingAs($this->admin)->postJson(route('admin.emails.retry', $queuedMessage->id))
        ->assertOk()
        ->assertJson([
            'success' => true,
            'status' => 'submitted',
        ]);

    expect($queuedMessage->fresh()->status)->toBe('submitted');
    Queue::assertNothingPushed();
});

test('submitted and unknown email require explicit resend confirmation', function (): void {
    foreach (['submitted', 'unknown'] as $status) {
        $this->message->update(['status' => $status]);
        $this->actingAs($this->admin)->postJson(route('admin.emails.retry', $this->message->id))
            ->assertUnprocessable()->assertJsonValidationErrors('confirm_resend');
        expect($this->message->fresh()->status)->toBe($status);
    }
    Queue::assertNothingPushed();
});

test('staff with email permission cannot resend', function (): void {
    $staff = User::factory()->create();
    $staff->assignRole('staff');
    $staff->givePermissionTo('setting.email');
    $this->actingAs($staff)->get(route('admin.emails.show', $this->message->id))
        ->assertOk()->assertDontSee('Kirim Ulang Manual');
    $this->postJson(route('admin.emails.retry', $this->message->id), ['confirm_resend' => 1])
        ->assertForbidden();
    Queue::assertNothingPushed();
});

test('processing and verification messages cannot be resent by admin', function (): void {
    $this->message->update(['status' => 'processing']);
    $this->actingAs($this->admin)->postJson(route('admin.emails.retry', $this->message->id), ['confirm_resend' => 1])
        ->assertStatus(409);
    $this->message->update(['status' => 'failed', 'type' => 'verification']);
    $this->postJson(route('admin.emails.retry', $this->message->id), ['confirm_resend' => 1])
        ->assertUnprocessable();
    Queue::assertNothingPushed();
});

<?php

declare(strict_types=1);

use App\Contracts\EmailDelivery;
use App\Models\User;
use App\Modules\Communication\Jobs\SendN8nEmailJob;
use App\Modules\Communication\Models\EmailMessage;
use App\Modules\Communication\Services\N8nEmailSender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

test('email delivery sends immediately to n8n without laravel queue', function (): void {
    Queue::fake();
    Config::set('services.n8n.email_enabled', true);
    Config::set('services.n8n.webhook_url', 'https://n8.fasekita.my.id/webhook/email');
    Config::set('services.n8n.webhook_secret', 'secret-token-abc');
    Config::set('services.n8n.allowed_host', 'n8.fasekita.my.id');

    Http::fake([
        'https://n8.fasekita.my.id/webhook/email' => Http::response(['success' => true], 200),
    ]);

    /** @var EmailDelivery $delivery */
    $delivery = app(EmailDelivery::class);

    $notificationId = $delivery->send([
        'recipient_email' => 'user@example.com',
        'recipient_name' => 'Budi Santoso',
        'subject' => 'Verifikasi Akun',
        'body' => 'Silakan klik tautan verifikasi akun Anda.',
        'action_url' => 'https://actstation.test/verify/token123',
        'action_label' => 'Verifikasi',
        'type' => 'verification',
    ]);

    expect($notificationId)->not->toBeEmpty();
    Queue::assertNothingPushed();

    Http::assertSentCount(1);

    $message = EmailMessage::find($notificationId);
    expect($message)->not->toBeNull()
        ->and($message->status)->toBe(EmailMessage::STATUS_SUBMITTED)
        ->and($message->getRecipientEmail())->toBe('user@example.com')
        ->and($message->getRecipientName())->toBe('Budi Santoso')
        ->and($message->type)->toBe('verification');
});

test('unconfigured or disabled n8n marks message as failed without silent pretend success', function (): void {
    Config::set('services.n8n.email_enabled', false);
    Config::set('services.n8n.webhook_url', '');

    $message = EmailMessage::create([
        'recipient' => ['email' => 'target@example.com', 'name' => 'Target'],
        'subject' => 'Informasi Kesiapan',
        'type' => EmailMessage::TYPE_READINESS,
        'status' => EmailMessage::STATUS_QUEUED,
        'payload' => [
            'body' => 'Informasi kesiapan turnamen',
            'action_url' => null,
            'action_label' => null,
            'attachments' => [],
        ],
    ]);

    $job = new SendN8nEmailJob($message->id);
    $job->handle();

    $fresh = $message->fresh();
    expect($fresh->status)->toBe(EmailMessage::STATUS_FAILED)
        ->and($fresh->error_message)->toContain('unconfigured or disabled')
        ->and($fresh->failed_at)->not->toBeNull();
});

test('submitted n8n updates status to submitted with correct payload and headers', function (): void {
    Config::set('services.n8n.email_enabled', true);
    Config::set('services.n8n.webhook_url', 'https://n8.fasekita.my.id/webhook/email');
    Config::set('services.n8n.webhook_secret', 'secret-token-abc');
    Config::set('services.n8n.allowed_host', 'n8.fasekita.my.id');

    Http::fake([
        'https://n8.fasekita.my.id/webhook/email' => Http::response(['success' => true], 200),
    ]);

    $message = EmailMessage::create([
        'recipient' => ['email' => 'official@example.com', 'name' => 'Pengawas Lapangan'],
        'subject' => 'Surat Penugasan',
        'type' => EmailMessage::TYPE_ASSIGNMENT,
        'status' => EmailMessage::STATUS_QUEUED,
        'payload' => [
            'body' => 'Berikut adalah penugasan resmi Anda.',
            'action_url' => 'https://actstation.test/panel/assignment',
            'action_label' => 'Buka Penugasan',
            'attachments' => [],
        ],
    ]);

    $job = new SendN8nEmailJob($message->id);
    $job->handle();

    $fresh = $message->fresh();
    expect($fresh->status)->toBe(EmailMessage::STATUS_SUBMITTED)
        ->and($fresh->submitted_at)->not->toBeNull()
        ->and($fresh->error_message)->toBeNull();

    $job->handle();
    Http::assertSentCount(1);

    Http::assertSent(function (Request $request) use ($message) {
        $hasToken = $request->hasHeader('X-Webhook-Token', 'secret-token-abc');
        $hasSource = $request->hasHeader('X-Webhook-Source', 'laravel-actstation');
        $data = $request->data();

        return $hasToken
            && $hasSource
            && ($data['event'] ?? '') === 'notification.email'
            && ($data['message_id'] ?? '') === (string) $message->id
            && ($data['data']['email'] ?? '') === 'official@example.com'
            && ($data['data']['name'] ?? '') === 'Pengawas Lapangan'
            && ! isset($data['data']['ip'])
            && ! isset($data['data']['user_agent'])
            && ! isset($data['data']['nik']);
    });
});

test('unauthorized user without setting email permission gets 403 on admin routes', function (): void {
    if (! Role::where('name', 'staff')->exists()) {
        Role::create(['name' => 'staff', 'guard_name' => 'web']);
    }

    $user = User::factory()->create();
    $user->assignRole('staff');

    $this->actingAs($user)
        ->get(route('admin.emails.index'))
        ->assertForbidden();

    $this->actingAs($user)
        ->post(route('admin.emails.preview'), [
            'subject' => 'Test',
            'body' => 'Body',
            'type' => 'information',
        ])
        ->assertForbidden();
});

test('signed download rejects missing signature with 403 and invalid message with 404', function (): void {
    $unsignedUrl = route('email.attachments.download', [
        'message' => 99999,
        'index' => 0,
    ]);

    $this->get($unsignedUrl)->assertForbidden();

    $signedUrl = URL::temporarySignedRoute('email.attachments.download', now()->addMinutes(10), [
        'message' => 99999,
        'index' => 0,
    ]);

    $this->get($signedUrl)->assertNotFound();
});

test('xss payload is escaped via preview and fallback html template', function (): void {
    Permission::firstOrCreate(['name' => 'setting.email', 'guard_name' => 'web']);
    if (! Role::where('name', 'admin')->exists()) {
        $role = Role::create(['name' => 'admin', 'guard_name' => 'web', 'panel_access' => 'internal']);
    } else {
        $role = Role::where('name', 'admin')->first();
    }
    $role->givePermissionTo('setting.email');

    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $xssSubject = '<script>alert("xss-title")</script>';
    $xssBody = '<script>alert("xss-body")</script>';

    $response = $this->actingAs($admin)->postJson(route('admin.emails.preview'), [
        'subject' => $xssSubject,
        'body' => $xssBody,
        'recipient_name' => '<b>Hacker</b>',
        'type' => 'information',
        'action_url' => 'https://example.com',
        'action_label' => 'Click',
    ]);

    $response->assertOk();
    $html = $response->json('html');

    expect($html)->not->toContain('<script>')
        ->and($html)->toContain('&lt;script&gt;alert(&quot;xss-title&quot;)&lt;/script&gt;')
        ->and($html)->toContain('&lt;script&gt;alert(&quot;xss-body&quot;)&lt;/script&gt;');
});

test('admin can upload multipart attachments up to 3 files and send email', function (): void {
    Queue::fake();
    Storage::fake('local');
    Config::set('services.n8n.email_enabled', true);
    Config::set('services.n8n.webhook_url', 'https://n8.fasekita.my.id/webhook/email');
    Config::set('services.n8n.webhook_secret', 'secret-token-abc');
    Config::set('services.n8n.allowed_host', 'n8.fasekita.my.id');

    Http::fake([
        'https://n8.fasekita.my.id/webhook/email' => Http::response(['success' => true], 200),
    ]);

    Permission::firstOrCreate(['name' => 'setting.email', 'guard_name' => 'web']);
    if (! Role::where('name', 'admin')->exists()) {
        $role = Role::create(['name' => 'admin', 'guard_name' => 'web', 'panel_access' => 'internal']);
    } else {
        $role = Role::where('name', 'admin')->first();
    }
    $role->givePermissionTo('setting.email');

    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $file1 = UploadedFile::fake()->createWithContent('doc1.pdf', "%PDF-1.4\n1 0 obj\n<<>>\nendobj\ntrailer\n<<>>\n%%EOF");
    $file2 = UploadedFile::fake()->createWithContent('doc2.pdf', "%PDF-1.4\n2 0 obj\n<<>>\nendobj\ntrailer\n<<>>\n%%EOF");
    $file3 = UploadedFile::fake()->image('image3.png', 200, 200);

    $response = $this->actingAs($admin)->post(route('admin.emails.store'), [
        'recipient_email' => 'client@example.com',
        'recipient_name' => 'Klien Penerima',
        'subject' => 'Dokumen Pertandingan',
        'body' => 'Berikut berkas dokumen pertandingan Anda.',
        'type' => 'information',
        'action_url' => 'https://actstation.test/dashboard',
        'action_label' => 'Buka Dashboard',
        'attachments' => [$file1, $file2, $file3],
    ]);

    $response->assertSessionHasNoErrors();
    Queue::assertNothingPushed();
    $message = EmailMessage::where('subject', 'Dokumen Pertandingan')->first();
    expect($message)->not->toBeNull()
        ->and($message->status)->toBe(EmailMessage::STATUS_SUBMITTED)
        ->and(count($message->attachments))->toBe(3);

    $firstAtt = $message->attachments[0];
    expect($firstAtt['path'])->toStartWith('email-attachments/')
        ->and($firstAtt['mime'])->toBe('application/pdf');
});

test('signed download returns 200 binary download for valid attachment', function (): void {
    Storage::fake('local');
    $disk = Storage::disk('local');
    $disk->makeDirectory('email-attachments');
    $disk->put('email-attachments/sample.pdf', '%PDF-1.4 sample content');

    $message = EmailMessage::create([
        'recipient' => ['email' => 'member@example.com', 'name' => 'Member'],
        'subject' => 'Unduhan Berkas',
        'type' => EmailMessage::TYPE_INFORMATION,
        'status' => EmailMessage::STATUS_QUEUED,
        'payload' => [
            'body' => 'Silakan unduh berkas lampiran.',
            'action_url' => null,
            'action_label' => null,
            'attachments' => [
                [
                    'path' => 'email-attachments/sample.pdf',
                    'name' => 'sample.pdf',
                    'mime' => 'application/pdf',
                    'size' => 24,
                ],
            ],
        ],
    ]);

    $signedUrl = URL::temporarySignedRoute(
        'email.attachments.download',
        now()->addMinutes(15),
        ['message' => $message->id, 'index' => 0]
    );

    $response = $this->get($signedUrl);
    $response->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});

test('signed download denies access to public storage or path traversal with 403', function (): void {
    Storage::fake('local');
    Storage::fake('public');

    Storage::disk('public')->put('secret.pdf', '%PDF-1.4 public file');
    $publicFilePath = Storage::disk('public')->path('secret.pdf');

    $message = EmailMessage::create([
        'recipient' => ['email' => 'victim@example.com', 'name' => 'Victim'],
        'subject' => 'Coba Terobos',
        'type' => EmailMessage::TYPE_INFORMATION,
        'status' => EmailMessage::STATUS_QUEUED,
        'payload' => [
            'body' => 'Test',
            'action_url' => null,
            'action_label' => null,
            'attachments' => [
                [
                    'path' => $publicFilePath,
                    'name' => 'secret.pdf',
                    'mime' => 'application/pdf',
                ],
            ],
        ],
    ]);

    $signedUrl = URL::temporarySignedRoute(
        'email.attachments.download',
        now()->addMinutes(15),
        ['message' => $message->id, 'index' => 0]
    );

    $this->get($signedUrl)->assertForbidden();
});

test('verification email hides body and action_url in show and blocks manual retry', function (): void {
    Permission::firstOrCreate(['name' => 'setting.email', 'guard_name' => 'web']);
    if (! Role::where('name', 'admin')->exists()) {
        $role = Role::create(['name' => 'admin', 'guard_name' => 'web', 'panel_access' => 'internal']);
    } else {
        $role = Role::where('name', 'admin')->first();
    }
    $role->givePermissionTo('setting.email');

    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $secretTokenUrl = 'https://actstation.test/verify-email?token=SUPER_SECRET_TOKEN_XYZ';

    $message = EmailMessage::create([
        'recipient' => ['email' => 'user@example.com', 'name' => 'Rahasia'],
        'subject' => 'Verifikasi Email Akun',
        'type' => EmailMessage::TYPE_VERIFICATION,
        'status' => EmailMessage::STATUS_FAILED,
        'payload' => [
            'body' => 'Klik link rahasia untuk verifikasi akun: '.$secretTokenUrl,
            'action_url' => $secretTokenUrl,
            'action_label' => 'Verifikasi Akun',
            'attachments' => [],
        ],
    ]);

    $jsonResponse = $this->actingAs($admin)->getJson(route('admin.emails.show', $message->id));
    $jsonResponse->assertOk()
        ->assertJsonMissing(['action_url' => $secretTokenUrl])
        ->assertJsonMissing(['body' => 'Klik link rahasia']);

    $retryResponse = $this->actingAs($admin)->postJson(route('admin.emails.retry', $message->id));
    $retryResponse->assertStatus(422)
        ->assertJsonFragment(['message' => 'Pesan verifikasi tidak dapat dikirim ulang secara manual demi keamanan.']);
});

test('index filters by type status and search query', function (): void {
    EmailMessage::query()->delete();

    Permission::firstOrCreate(['name' => 'setting.email', 'guard_name' => 'web']);
    if (! Role::where('name', 'admin')->exists()) {
        $role = Role::create(['name' => 'admin', 'guard_name' => 'web', 'panel_access' => 'internal']);
    } else {
        $role = Role::where('name', 'admin')->first();
    }
    $role->givePermissionTo('setting.email');

    $admin = User::factory()->create();
    $admin->assignRole('admin');

    EmailMessage::create([
        'recipient' => ['email' => 'a@example.com', 'name' => 'A'],
        'subject' => 'Pemberitahuan Khusus Turnamen Basket',
        'type' => EmailMessage::TYPE_INFORMATION,
        'status' => EmailMessage::STATUS_QUEUED,
        'payload' => ['body' => 'B', 'attachments' => []],
    ]);

    EmailMessage::create([
        'recipient' => ['email' => 'b@example.com', 'name' => 'B'],
        'subject' => 'Penugasan Wasit Sepak Bola',
        'type' => EmailMessage::TYPE_ASSIGNMENT,
        'status' => EmailMessage::STATUS_FAILED,
        'payload' => ['body' => 'B', 'attachments' => []],
    ]);

    $resType = $this->actingAs($admin)->getJson(route('admin.emails.index', ['type' => 'assignment']));
    $resType->assertOk();
    $data = $resType->json('data');
    expect(count($data))->toBe(1)
        ->and($data[0]['type'])->toBe('assignment');

    $resSearch = $this->actingAs($admin)->getJson(route('admin.emails.index', ['search' => 'Turnamen Basket']));
    $resSearch->assertOk();
    $searchData = $resSearch->json('data');
    expect(count($searchData))->toBe(1)
        ->and($searchData[0]['subject'])->toContain('Turnamen Basket');
});

test('delivery send rejects non-array attachments and unsafe urls', function (): void {
    /** @var EmailDelivery $delivery */
    $delivery = app(EmailDelivery::class);

    expect(fn () => $delivery->send([
        'recipient_email' => 'valid@example.com',
        'subject' => 'Test',
        'body' => 'Test body',
        'type' => 'information',
        'action_url' => 'javascript:alert(1)',
    ]))->toThrow(InvalidArgumentException::class);

    expect(fn () => $delivery->send([
        'recipient_email' => 'valid@example.com',
        'subject' => 'Test',
        'body' => 'Test body',
        'type' => 'information',
        'attachments' => 'invalid-not-array',
    ]))->toThrow(InvalidArgumentException::class, 'Attachments must be an array');
});

test('transaction aftercommit triggers handle only after commit and not on rollback', function (): void {
    Queue::fake();
    Config::set('services.n8n.email_enabled', true);
    Config::set('services.n8n.webhook_url', 'https://n8.fasekita.my.id/webhook/email');
    Config::set('services.n8n.webhook_secret', 'secret-token-abc');
    Config::set('services.n8n.allowed_host', 'n8.fasekita.my.id');

    Http::fake([
        'https://n8.fasekita.my.id/webhook/email' => Http::response(['success' => true], 200),
    ]);

    /** @var EmailDelivery $delivery */
    $delivery = app(EmailDelivery::class);

    // Rollback test: HTTP request must not be sent
    try {
        DB::transaction(function () use ($delivery): void {
            $delivery->send([
                'recipient_email' => 'rollback@example.com',
                'subject' => 'Rollback Test',
                'body' => 'Body',
                'type' => 'information',
            ]);
            throw new Exception('Rollback');
        });
    } catch (Exception $e) {
        // Expected rollback
    }

    Http::assertSentCount(0);

    // Commit test: HTTP request must be sent after commit
    $notificationId = DB::transaction(function () use ($delivery) {
        return $delivery->send([
            'recipient_email' => 'commit@example.com',
            'subject' => 'Commit Test',
            'body' => 'Body',
            'type' => 'information',
        ]);
    });

    Http::assertSentCount(1);
    $message = EmailMessage::find($notificationId);
    expect($message->status)->toBe(EmailMessage::STATUS_SUBMITTED);
});

test('registration is non-blocking and succeeds without user role rollback when n8n fails', function (): void {
    Config::set('services.n8n.email_enabled', true);
    Config::set('services.n8n.webhook_url', 'https://n8.fasekita.my.id/webhook/email');
    Config::set('services.n8n.webhook_secret', 'secret-token-abc');
    Config::set('services.n8n.allowed_host', 'n8.fasekita.my.id');

    Http::fake([
        'https://n8.fasekita.my.id/webhook/email' => Http::response(['error' => 'server error'], 500),
    ]);

    if (! Role::where('name', 'member')->exists()) {
        Role::create(['name' => 'member', 'guard_name' => 'web']);
    }

    $response = $this->post(route('register.submit'), [
        'name' => 'Nonblocking User',
        'email' => 'nonblocking@example.com',
        'phone_number' => '081234567890',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $response->assertRedirect(route('events.index'));
    $this->assertAuthenticated();

    $user = User::where('email', 'nonblocking@example.com')->first();
    expect($user)->not->toBeNull();
    expect($user->hasRole('member'))->toBeTrue();

    $email = EmailMessage::where('recipient_user_id', $user->id)->latest('id')->first();
    expect($email)->not->toBeNull()
        ->and($email->status)->toBe(EmailMessage::STATUS_FAILED);
});

test('connection exception or timeout marks message as unknown without automatic retry', function (): void {
    Config::set('services.n8n.email_enabled', true);
    Config::set('services.n8n.webhook_url', 'https://n8.fasekita.my.id/webhook/email');
    Config::set('services.n8n.webhook_secret', 'secret-token-abc');
    Config::set('services.n8n.allowed_host', 'n8.fasekita.my.id');

    Http::fake([
        'https://n8.fasekita.my.id/webhook/email' => fn () => throw new ConnectionException('Connection timed out'),
    ]);

    /** @var EmailDelivery $delivery */
    $delivery = app(EmailDelivery::class);

    $id = $delivery->send([
        'recipient_email' => 'timeout@example.com',
        'subject' => 'Timeout Test',
        'body' => 'Body',
        'type' => 'information',
    ]);

    $message = EmailMessage::find($id);
    expect($message->status)->toBe(EmailMessage::STATUS_UNKNOWN)
        ->and($message->error_message)->toContain('Connection interrupted');
});

test('concurrent calls cannot duplicate in-flight send due to atomic claim', function (): void {
    Config::set('services.n8n.email_enabled', true);
    Config::set('services.n8n.webhook_url', 'https://n8.fasekita.my.id/webhook/email');
    Config::set('services.n8n.webhook_secret', 'secret-token-abc');
    Config::set('services.n8n.allowed_host', 'n8.fasekita.my.id');

    Http::fake([
        'https://n8.fasekita.my.id/webhook/email' => Http::response(['success' => true], 200),
    ]);

    $sender = app(N8nEmailSender::class);

    $message = EmailMessage::create([
        'recipient' => ['email' => 'concurrent@example.com', 'name' => 'Concurrent'],
        'subject' => 'Concurrent Test',
        'type' => EmailMessage::TYPE_INFORMATION,
        'status' => EmailMessage::STATUS_QUEUED,
        'payload' => ['body' => 'Concurrent body', 'attachments' => []],
    ]);

    // Manually transition to PROCESSING to simulate in-flight execution by another thread
    EmailMessage::query()->whereKey($message->id)->update(['status' => EmailMessage::STATUS_PROCESSING]);

    // Another caller attempts to execute queued claim
    EmailMessage::query()->whereKey($message->id)
        ->where('status', EmailMessage::STATUS_QUEUED)
        ->update(['status' => EmailMessage::STATUS_PROCESSING]);

    // Job handler with already processing/submitted avoids sending duplicate HTTP request
    $job = new SendN8nEmailJob($message->id);
    // Since message is already PROCESSING and not QUEUED, fresh query will not re-claim
    $sender->send($message->id);
    $job->handle();

    Http::assertNothingSent();
    expect($message->fresh()->status)->toBe(EmailMessage::STATUS_PROCESSING);
});

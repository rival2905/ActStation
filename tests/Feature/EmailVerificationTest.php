<?php

declare(strict_types=1);

use App\Contracts\EmailDelivery;
use App\Models\User;
use App\Modules\Event\Models\Event;
use App\Modules\Event\Models\EventCategory;
use App\Modules\MasterData\Models\SportCategory;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

test('valid signed matching auth marks email as verified', function (): void {
    $user = User::factory()->create([
        'email' => 'unverified@example.com',
        'email_verified_at' => null,
        'is_active' => true,
    ]);
    $user->assignRole('member');

    $signedUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        [
            'id' => $user->id,
            'hash' => sha1($user->email),
        ]
    );

    $response = $this->actingAs($user)->get($signedUrl);

    $response->assertRedirect($user->getPanelHomeRoute());
    $response->assertSessionHas('verified', true);
    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
    expect($user->fresh()->email_verified_at)->not->toBeNull();
});

test('wrong id in signed url is rejected with 403', function (): void {
    $user1 = User::factory()->create([
        'email' => 'user1@example.com',
        'email_verified_at' => null,
        'is_active' => true,
    ]);
    $user1->assignRole('member');

    $user2 = User::factory()->create([
        'email' => 'user2@example.com',
        'email_verified_at' => null,
        'is_active' => true,
    ]);
    $user2->assignRole('member');

    // Signed for user2, but user1 is logged in
    $signedUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        [
            'id' => $user2->id,
            'hash' => sha1($user2->email),
        ]
    );

    $response = $this->actingAs($user1)->get($signedUrl);

    $response->assertForbidden();
    expect($user1->fresh()->hasVerifiedEmail())->toBeFalse();
    expect($user2->fresh()->hasVerifiedEmail())->toBeFalse();
});

test('wrong hash in signed url is rejected with 403', function (): void {
    $user = User::factory()->create([
        'email' => 'user@example.com',
        'email_verified_at' => null,
        'is_active' => true,
    ]);
    $user->assignRole('member');

    $signedUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        [
            'id' => $user->id,
            'hash' => sha1('tampered@example.com'),
        ]
    );

    $response = $this->actingAs($user)->get($signedUrl);

    $response->assertForbidden();
    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
});

test('expired signed url is rejected with 403', function (): void {
    $user = User::factory()->create([
        'email' => 'user@example.com',
        'email_verified_at' => null,
        'is_active' => true,
    ]);
    $user->assignRole('member');

    $expiredUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->subMinutes(10),
        [
            'id' => $user->id,
            'hash' => sha1($user->email),
        ]
    );

    $response = $this->actingAs($user)->get($expiredUrl);

    $response->assertForbidden();
    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
});

test('resend verification queues via EmailDelivery without real HTTP or SMTP', function (): void {
    $user = User::factory()->create([
        'name' => 'John Doe',
        'email' => 'johndoe@example.com',
        'email_verified_at' => null,
        'is_active' => true,
    ]);
    $user->assignRole('member');

    $capturedPayload = null;
    $fakeDelivery = new class($capturedPayload) implements EmailDelivery
    {
        public function __construct(public mixed &$target) {}

        public function send(array $message): int|string
        {
            $this->target = $message;

            return 1;
        }
    };
    app()->instance(EmailDelivery::class, $fakeDelivery);

    $response = $this->actingAs($user)->from('/email/verify')->post(route('verification.send'));

    $response->assertRedirect('/email/verify');
    $response->assertSessionHas('status', 'verification-link-sent');

    expect($capturedPayload)->not->toBeNull();
    expect($capturedPayload['recipient_user_id'])->toBe($user->id);
    expect($capturedPayload['recipient_email'])->toBe('johndoe@example.com');
    expect($capturedPayload['recipient_name'])->toBe('John Doe');
    expect($capturedPayload['type'])->toBe('verification');
    expect($capturedPayload['attachments'])->toBe([]);
    expect($capturedPayload['action_label'])->toBe('Verifikasi Email');
    expect($capturedPayload['action_url'])->toContain('/email/verify/'.$user->id.'/'.sha1('johndoe@example.com'));
});

test('resent verification HTML contains one greeting and only the button and fallback link', function (): void {
    $this->freezeTime();
    config([
        'services.n8n.email_enabled' => true,
        'services.n8n.webhook_url' => 'https://email.example.test/webhook/actstation-email',
        'services.n8n.webhook_secret' => 'test-only-secret',
        'services.n8n.allowed_host' => 'email.example.test',
    ]);
    Http::preventStrayRequests();
    Http::fake([
        'https://email.example.test/webhook/actstation-email' => Http::response(['success' => true]),
    ]);
    $user = User::factory()->create(['name' => 'Demo & Peserta', 'email_verified_at' => null, 'is_active' => true]);
    $user->assignRole('member');

    $this->actingAs($user)->from('/email/verify')->post(route('verification.send'))
        ->assertRedirect('/email/verify')->assertSessionHas('status', 'verification-link-sent');

    Http::assertSentCount(1);
    Http::assertSent(function (Illuminate\Http\Client\Request $request): bool {
        $data = $request['data'];
        expect($data['body'])->not->toContain('Halo', $data['url'])
            ->and($data['body'])->toContain('60 menit');
        $dom = new DOMDocument;
        @$dom->loadHTML($data['html']);
        $xpath = new DOMXPath($dom);
        $text = preg_replace('/\s+/', ' ', $dom->textContent);
        expect(substr_count($text, 'Halo Demo & Peserta,'))->toBe(1);
        $links = array_values(array_filter(iterator_to_array($dom->getElementsByTagName('a')), fn ($link) => $link->getAttribute('href') === $data['url']));
        expect($links)->toHaveCount(2)
            ->and(trim($links[0]->textContent))->toContain('Verifikasi Email')
            ->and(trim($links[1]->textContent))->toBe($data['url'])
            ->and($xpath->query('//div[contains(@style, "word-break: break-word")]')->item(0)->textContent)->not->toContain($data['url']);
        expect(URL::hasValidSignature(Request::create($data['url'])))->toBeTrue();
        parse_str(parse_url($data['url'], PHP_URL_QUERY), $query);
        expect((int) $query['expires'])->toBe(now()->addMinutes(60)->getTimestamp());

        return true;
    });
});

test('already verified user resend does not queue duplicate email', function (): void {
    $user = User::factory()->create([
        'email' => 'verified@example.com',
        'email_verified_at' => now(),
        'is_active' => true,
    ]);
    $user->assignRole('member');

    $called = false;
    $fakeDelivery = new class($called) implements EmailDelivery
    {
        public function __construct(public bool &$called) {}

        public function send(array $message): int|string
        {
            $this->called = true;

            return 1;
        }
    };
    app()->instance(EmailDelivery::class, $fakeDelivery);

    $response = $this->actingAs($user)->post(route('verification.send'));

    $response->assertRedirect($user->getPanelHomeRoute());
    expect($called)->toBeFalse();
});

test('changing user email clears email_verified_at', function (): void {
    $user = User::factory()->create([
        'email' => 'original@example.com',
        'email_verified_at' => now(),
        'is_active' => true,
    ]);

    expect($user->hasVerifiedEmail())->toBeTrue();

    // Updating other attributes does not clear verification
    $user->update(['name' => 'Updated Name']);
    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();

    // Changing email clears email_verified_at
    $user->update(['email' => 'changed@example.com']);
    $user->refresh();
    expect($user->email_verified_at)->toBeNull();
    expect($user->hasVerifiedEmail())->toBeFalse();
});

test('registration stays accessible when pending (soft status) and verification link is queued without PII in payload body', function (): void {
    $capturedPayload = null;
    $fakeDelivery = new class($capturedPayload) implements EmailDelivery
    {
        public function __construct(public mixed &$target) {}

        public function send(array $message): int|string
        {
            $this->target = $message;

            return 1;
        }
    };
    app()->instance(EmailDelivery::class, $fakeDelivery);

    $response = $this->post(route('register.submit'), [
        'name' => 'Alice Registrant',
        'email' => 'alice@example.com',
        'phone_number' => '08123456789',
        'password' => 'secretPassword123',
        'password_confirmation' => 'secretPassword123',
    ]);

    $response->assertRedirect(route('events.index'));
    $this->assertAuthenticated();

    $user = User::where('email', 'alice@example.com')->first();
    expect($user)->not->toBeNull();
    expect($user->hasVerifiedEmail())->toBeFalse();

    // Soft status: user can access authenticated areas without 403 or verification gate
    $myEventsResponse = $this->actingAs($user)->get(route('my-events.index'));
    $myEventsResponse->assertOk();

    // Verification link was queued
    expect($capturedPayload)->not->toBeNull();
    expect($capturedPayload['recipient_email'])->toBe('alice@example.com');
    expect($capturedPayload['action_url'])->toContain('/email/verify/'.$user->id.'/'.sha1('alice@example.com'));

    // Payload body does NOT contain sensitive PII (password)
    expect($capturedPayload['body'])->not->toContain('secretPassword123');
});

test('verification notice route is accessible when pending and redirects when verified', function (): void {
    $user = User::factory()->create([
        'email' => 'notice@example.com',
        'email_verified_at' => null,
        'is_active' => true,
    ]);
    $user->assignRole('member');

    // Unverified user can access verification notice
    $response = $this->actingAs($user)->get(route('verification.notice'));
    $response->assertOk();

    // Once verified, notice route redirects to panel home
    $user->markEmailAsVerified();
    $responseVerified = $this->actingAs($user->fresh())->get(route('verification.notice'));
    $responseVerified->assertRedirect($user->getPanelHomeRoute());
});

test('individual event registration queues verification email for newly created user', function (): void {
    $sport = SportCategory::query()->firstOrCreate(
        ['slug' => 'basketball'],
        ['name' => 'Basketball', 'is_active' => true]
    );

    $organizer = User::factory()->create(['email' => 'org@example.com']);

    $event = Event::create([
        'user_id' => $organizer->id,
        'title' => 'Kejuaraan Open 2026',
        'slug' => 'kejuaraan-open-2026',
        'sport_category_id' => $sport->id,
        'status' => 'published',
        'registration_open' => true,
        'starts_at' => now()->addMonth(),
        'ends_at' => now()->addMonth()->addDays(5),
    ]);

    $category = EventCategory::factory()->create([
        'event_id' => $event->id,
        'name' => 'Kategori Mandiri',
        'allow_club' => false,
        'allow_community' => false,
        'allow_individual' => true,
        'registration_flow' => 'instant_free',
        'registration_fee' => 0,
        'min_roster' => 1,
        'max_roster' => 1,
        'gender' => 'male',
    ]);

    $capturedPayload = null;
    $fakeDelivery = new class($capturedPayload) implements EmailDelivery
    {
        public function __construct(public mixed &$target) {}

        public function send(array $message): int|string
        {
            $this->target = $message;

            return 1;
        }
    };
    app()->instance(EmailDelivery::class, $fakeDelivery);

    $payload = [
        'registration_source' => 'individual',
        'event_category_id' => $category->id,
        'name' => 'Bambang Atlet',
        'email' => 'bambang@example.com',
        'phone_number' => '081299998888',
        'identity_number' => '3273010101909999',
        'birth_date' => '1995-05-12',
        'gender' => 'male',
        'jersey_number' => 10,
    ];

    $response = $this->post(route('events.register.individual', $event->slug), $payload);

    $response->assertRedirect(route('my-events.index'));
    $this->assertAuthenticated();

    $user = User::where('email', 'bambang@example.com')->firstOrFail();
    expect($user->hasVerifiedEmail())->toBeFalse();

    expect($capturedPayload)->not->toBeNull();
    expect($capturedPayload['recipient_email'])->toBe('bambang@example.com');
    expect($capturedPayload['action_url'])->toContain('/email/verify/'.$user->id.'/'.sha1('bambang@example.com'));
});

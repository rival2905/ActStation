<?php

declare(strict_types=1);

use App\Contracts\EmailDelivery;
use App\Models\User;
use App\Modules\Community\Models\Community;
use App\Modules\Community\Models\CommunityOfficial;
use App\Modules\Community\Models\SchoolAuthorityChange;
use App\Modules\Community\Models\SchoolRegistration;
use App\Modules\Community\Services\SchoolAuthorityService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;
use Spatie\Permission\Models\Role;

uses(DatabaseTransactions::class);

beforeEach(function (): void {
    Http::preventStrayRequests();
    $this->spy(EmailDelivery::class);
    Role::firstOrCreate(['name' => 'community_manager', 'guard_name' => 'web'], ['panel_access' => 'external']);
    $this->owner = User::factory()->create(['is_active' => true]);
    $this->owner->assignRole('community_manager');
    $this->school = Community::factory()->create(['subtype' => 'school', 'contact_email' => 'old@example.invalid']);
    $this->official = CommunityOfficial::create(['community_id' => $this->school->id, 'user_id' => $this->owner->id, 'position' => 'manager', 'status' => 'active', 'started_at' => today()]);
    $this->registration = SchoolRegistration::create(['community_id' => $this->school->id, 'user_id' => $this->owner->id, 'npsn' => '12345678', 'contact_email' => $this->school->contact_email, 'email_hash' => hash('sha256', $this->school->contact_email), 'status' => 'approved', 'admin_review_status' => 'approved', 'email_verified_at' => now()->subDay()]);
});

test('official email remains unchanged until owner confirms new inbox token', function (): void {
    $endpoint = '/panel/school-registrations/'.$this->registration->id.'/official-email';
    $this->actingAs($this->owner)->postJson($endpoint, ['new_email' => 'new@example.invalid', 'current_password' => 'wrong'])->assertUnprocessable();
    $this->post($endpoint, ['new_email' => 'new@example.invalid', 'current_password' => 'password'])->assertRedirect()->assertSessionHasNoErrors();
    $change = SchoolAuthorityChange::where('school_registration_id', $this->registration->id)->firstOrFail();
    expect($change->target_email)->toBe('new@example.invalid')->and($change->target_user_id)->toBe($this->owner->id)
        ->and($this->registration->fresh()->contact_email)->toBe('old@example.invalid')
        ->and($this->school->fresh()->contact_email)->toBe('old@example.invalid');
    $token = str_repeat('e', 64);
    $change->update(['token_hash' => hash('sha256', $token)]);
    $url = URL::temporarySignedRoute('panel.school-authority.confirm', now()->addHour(), ['change' => $change->id, 'token' => $token]);
    auth()->logout();
    $this->get($url)->assertOk()->assertSee('Masuk memakai akun pengurus')->assertDontSee('name="phone_number"', false)
        ->assertSessionHas('url.intended', function (string $intended) use ($url): bool {
            parse_str(parse_url($url, PHP_URL_QUERY), $expectedQuery);
            parse_str(parse_url($intended, PHP_URL_QUERY), $actualQuery);

            return parse_url($intended, PHP_URL_PATH) === parse_url($url, PHP_URL_PATH) && $actualQuery == $expectedQuery;
        });
    $outsider = User::factory()->create(['is_active' => true]);
    $this->actingAs($outsider)->post($url, ['token' => $token, 'consent' => '1'])->assertForbidden();
    $this->actingAs($this->owner)->post($url, ['token' => $token, 'consent' => '1'])->assertRedirect();
    expect($this->registration->fresh()->contact_email)->toBe('new@example.invalid')
        ->and($this->school->fresh()->contact_email)->toBe('new@example.invalid')
        ->and($this->registration->fresh()->user_id)->toBe($this->owner->id)
        ->and($this->official->fresh()->status)->toBe('active')
        ->and($change->fresh()->status)->toBe('accepted');
    $this->post($url, ['token' => $token, 'consent' => '1'])->assertForbidden();
});

test('official email challenge sends only to new inbox after commit and rollback sends nothing', function (): void {
    $delivery = new class implements EmailDelivery
    {
        public array $messages = [];

        public function send(array $message): int|string
        {
            $this->messages[] = $message;

            return 1;
        }
    };
    app()->instance(EmailDelivery::class, $delivery);
    $service = app(SchoolAuthorityService::class);
    try {
        DB::transaction(function () use ($service): void {
            $service->requestOfficialEmailChange($this->registration->id, $this->owner, 'rollback@example.invalid');
            throw new RuntimeException('Rollback fixture');
        });
    } catch (RuntimeException) {
    }
    expect($delivery->messages)->toBeEmpty();
    $service->requestOfficialEmailChange($this->registration->id, $this->owner, 'proven@example.invalid');
    expect($delivery->messages)->toHaveCount(1)
        ->and($delivery->messages[0]['recipient_email'])->toBe('proven@example.invalid');
    $link = $delivery->messages[0]['action_url'];
    parse_str(parse_url($link, PHP_URL_QUERY), $query);
    expect(strlen($query['token']))->toBe(64)->and(isset($query['signature']))->toBeTrue();
});

test('official email edit appears only in profile and navigation follows active school', function (): void {
    $other = Community::factory()->create(['subtype' => 'school']);
    CommunityOfficial::create(['community_id' => $other->id, 'user_id' => $this->owner->id, 'position' => 'manager', 'status' => 'active', 'started_at' => today()]);
    $latest = SchoolRegistration::create(['community_id' => $other->id, 'user_id' => $this->owner->id, 'npsn' => '87654321', 'contact_email' => 'other@example.invalid', 'email_hash' => hash('sha256', 'other@example.invalid'), 'status' => 'approved', 'admin_review_status' => 'approved', 'email_verified_at' => now()]);
    $this->actingAs($this->owner)->withSession(['current_community_id' => $this->school->id]);
    $page = $this->get('/panel/school-registrations/'.$this->registration->id)->assertOk()->assertSee('Kewenangan Sekolah')->assertDontSee('name="new_email"', false)->assertSee('aria-current="page"', false);
    $dom = new DOMDocument;
    @$dom->loadHTML($page->getContent());
    $links = (new DOMXPath($dom))->query('//nav[@aria-label="Menu panel"]//a[contains(@href, "/school-registrations/")]');
    expect($links->length)->toBe(1)->and($links->item(0)->getAttribute('href'))->toEndWith('/school-registrations/'.$this->registration->id);
    $this->get('/panel/profile')->assertOk()->assertSee('name="new_email"', false)->assertSee('/school-registrations/'.$this->registration->id.'/official-email', false);
    $this->withSession(['current_community_id' => $other->id])->get('/panel/profile')->assertOk()->assertSee('/school-registrations/'.$latest->id.'/official-email', false);
});

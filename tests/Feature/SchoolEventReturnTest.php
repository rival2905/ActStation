<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Community\Models\Community;
use App\Modules\Community\Models\CommunityOfficial;
use App\Modules\Event\Models\Event;
use App\Modules\Event\Models\EventCategory;
use App\Services\EventRegistrationReturn;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;

uses(DatabaseTransactions::class);

beforeEach(function (): void {
    Http::preventStrayRequests();
    Role::firstOrCreate(['name' => 'member', 'guard_name' => 'web', 'panel_access' => 'external']);
    $this->user = User::factory()->create(['is_active' => true]);
    $this->user->assignRole('member');
    $this->event = Event::factory()->create(['status' => 'published']);
    $this->category = EventCategory::factory()->create(['event_id' => $this->event->id]);
});

test('explicit team entry resumes onboarding with selected event and category', function (): void {
    $this->actingAs($this->user)->get(route('panel.community-events.create', ['slug' => $this->event->slug, 'event_category_id' => $this->category->id, 'return_url' => 'https://example.invalid/']))
        ->assertRedirect(route('panel.community.create'))
        ->assertSessionHas(EventRegistrationReturn::SESSION_KEY, ['event_id' => $this->event->id, 'category_id' => $this->category->id]);
});

test('new account returns to the public event and selected category after guest team entry', function (): void {
    config(['services.n8n.email_enabled' => false]);
    $this->event->update(['registration_open' => true]);
    $teamUrl = route('panel.community-events.create', ['slug' => $this->event->slug, 'event_category_id' => $this->category->id]);
    $this->get($teamUrl)->assertRedirect(route('panel.login'))->assertSessionHas('url.intended', $teamUrl);
    $this->get(route('panel.login'))->assertOk()->assertSee(route('register'));
    $this->get(route('register'))->assertOk();
    $this->post(route('register.submit'), [
        'name' => 'Peserta Baru',
        'email' => 'event-onboarding@example.test',
        'phone_number' => '089876543210',
        'password' => 'Test-only-registration-123',
        'password_confirmation' => 'Test-only-registration-123',
    ])->assertSessionHasNoErrors()
        ->assertRedirect(route('events.register', ['slug' => $this->event->slug, 'event_category_id' => $this->category->id]))
        ->assertSessionMissing('url.intended');
    $this->assertAuthenticated();
    $this->get(route('events.register', ['slug' => $this->event->slug, 'event_category_id' => $this->category->id]))->assertOk()->assertSee($this->event->title);
    $this->get($teamUrl)->assertRedirect(route('panel.community.create'));
});

test('event return rejects unrelated destinations and sanitizes category context', function (string $scenario): void {
    $this->event->update(['registration_open' => $scenario !== 'closed']);
    $params = ['slug' => $this->event->slug];
    if ($scenario === 'foreign-category') {
        $params['event_category_id'] = EventCategory::factory()->create()->id;
    } elseif ($scenario === 'array-category') {
        $params['event_category_id'] = ['bad'];
    }
    $url = route('panel.community-events.create', $params);
    $intended = match ($scenario) {
        'external' => 'https://example.invalid'.parse_url($url, PHP_URL_PATH),
        'prefix-host' => 'https://example.invalid/?next='.$url,
        'admin' => route('admin.dashboard'),
        'malformed' => ['bad'],
        'missing' => null,
        default => $url,
    };
    $request = request();
    $request->setLaravelSession(app('session')->driver());
    $request->session()->put('url.intended', $intended);
    $destination = app(EventRegistrationReturn::class)->publicDestinationFromIntended($request);
    expect($destination)->toBe(in_array($scenario, ['foreign-category', 'array-category', 'no-category']) ? route('events.register', $this->event->slug) : null);
})->with(['external', 'prefix-host', 'admin', 'malformed', 'missing', 'closed', 'foreign-category', 'array-category', 'no-category']);

test('login keeps event intent and routes through community prerequisite', function (bool $hasCommunity): void {
    $this->user->update(['password' => bcrypt('Test-only-login-123')]);
    if ($hasCommunity) {
        $community = Community::factory()->create(['is_active' => true]);
        CommunityOfficial::create([
            'community_id' => $community->id, 'user_id' => $this->user->id,
            'position' => 'Ketua', 'status' => 'active', 'started_at' => now(),
        ]);
    }
    $teamUrl = route('panel.community-events.create', ['slug' => $this->event->slug, 'event_category_id' => $this->category->id]);
    $this->get($teamUrl)->assertRedirect(route('panel.login'));
    $this->post(route('panel.login'), ['email' => $this->user->email, 'password' => 'Test-only-login-123'])->assertRedirect($teamUrl);
    $response = $this->get($teamUrl);
    if ($hasCommunity) {
        $response->assertOk()->assertSee($this->event->title);
    } else {
        $response->assertRedirect(route('panel.community.create'));
    }
})->with([false, true]);

test('registration without event intent keeps the existing event list destination', function (): void {
    config(['services.n8n.email_enabled' => false]);
    $this->post(route('register.submit'), [
        'name' => 'Peserta Biasa', 'email' => 'ordinary-registration@example.test', 'phone_number' => '089876543211',
        'password' => 'Test-only-registration-123', 'password_confirmation' => 'Test-only-registration-123',
    ])->assertSessionHasNoErrors()->assertRedirect(route('events.index'));
    $this->assertAuthenticated();
});

test('new unverified account sees direct registration and returns after institution creation', function (): void {
    Role::firstOrCreate(['name' => 'community_manager', 'guard_name' => 'web', 'panel_access' => 'external']);
    $this->user->update(['email_verified_at' => null]);
    $this->event->update(['registration_open' => true]);
    $this->actingAs($this->user)->get(route('events.show', $this->event->slug))->assertOk()
        ->assertSee('href="'.route('events.register', $this->event->slug).'"', false)->assertDontSee('Saya Ingin Ikut');
    $this->get(route('events.register', ['slug' => $this->event->slug, 'event_category_id' => $this->category->id]))
        ->assertOk()->assertSee('Daftar Tim pada Kategori Ini')->assertDontSee('Saya Ingin Ikut');
    $this->get('/panel/my-communities')->assertOk()->assertSee('Daftarkan Komunitas / Instansi');
    $teamUrl = route('panel.community-events.create', ['slug' => $this->event->slug, 'event_category_id' => $this->category->id]);
    $this->get($teamUrl)->assertRedirect(route('panel.community.create'));
    $this->get(route('panel.community.create'))->assertOk()->assertSee('Setelah disimpan, Anda kembali ke pendaftaran turnamen');
    $this->post(route('panel.community.store'), [
        'name' => 'Instansi Uji Alur', 'subtype' => 'institution', 'institution_type' => 'government',
        'province_id' => '32', 'city_id' => '32.17', 'contact_person' => 'Pengurus Uji',
        'contact_phone' => '081234567890', 'contact_email' => 'instansi@example.test',
    ])->assertRedirect($teamUrl)->assertSessionHasNoErrors();
    $this->get($teamUrl)->assertOk()->assertSee($this->event->title);
    expect($this->user->fresh()->email_verified_at)->toBeNull();
    $this->assertDatabaseHas('communities', ['name' => 'Instansi Uji Alur', 'subtype' => 'institution', 'status' => 'draft']);
});

test('malformed category returns validation error without creating a school or unsafe redirect', function (): void {
    $this->actingAs($this->user)->getJson(route('panel.community-events.create', ['slug' => $this->event->slug, 'event_category_id' => ['bad']]))->assertUnprocessable();
});

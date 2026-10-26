<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Community\Models\Community;
use App\Modules\Community\Models\CommunityAthlete;
use App\Modules\Community\Models\CommunityOfficial;
use App\Modules\Event\Models\Event;
use App\Modules\Event\Models\EventCategory;
use App\Modules\Event\Models\RegistrationDraft;
use App\Modules\Event\Services\AthleteEventInterestService;
use App\Modules\MasterData\Models\SportCategory;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpKernel\Exception\HttpException;

uses(DatabaseTransactions::class);

beforeEach(function (): void {
    Http::preventStrayRequests();
    Role::firstOrCreate(['name' => 'athlete', 'guard_name' => 'web'], ['panel_access' => 'external']);
    $this->athlete = User::factory()->create(['is_active' => true, 'birth_date' => '2010-01-01', 'gender' => 'male']);
    $this->athlete->assignRole('athlete');
    $this->community = Community::factory()->create(['is_active' => true, 'status' => 'verified']);
    $this->membership = CommunityAthlete::factory()->create(['community_id' => $this->community->id, 'user_id' => $this->athlete->id, 'status' => 'active', 'left_at' => null, 'region_status' => 'pending']);
    $this->manager = User::factory()->create(['is_active' => true]);
    $this->manager->assignRole('athlete');
    $this->official = CommunityOfficial::create(['community_id' => $this->community->id, 'user_id' => $this->manager->id, 'position' => 'Pengurus', 'status' => 'active', 'started_at' => now()]);
    $this->event = Event::factory()->create(['status' => 'published', 'registration_open' => true, 'registration_deadline' => now()->addWeek(), 'starts_at' => now()->addMonth()]);
    $this->category = EventCategory::factory()->create(['event_id' => $this->event->id, 'allow_club' => true, 'gender' => 'male', 'min_age' => 14, 'max_age' => 18]);
    $this->interestUrl = route('panel.event-interests.store', $this->event->slug);
});

test('athlete interest reaches manager once without creating team or community', function (): void {
    $communityCount = Community::count();
    $draftCount = RegistrationDraft::count();
    $this->actingAs($this->athlete)->get(route('panel.event-interests.create', $this->event->slug))->assertOk()->assertSee($this->community->name)->assertSee('Kirim Minat');
    $payload = ['community_id' => $this->community->id, 'consent' => '1', 'user_id' => $this->manager->id];
    $this->post($this->interestUrl, $payload)->assertSessionHasNoErrors()->assertRedirect();
    $this->post($this->interestUrl, $payload)->assertSessionHasNoErrors()->assertRedirect();
    $this->assertDatabaseCount('event_interests', 1);
    $this->assertDatabaseHas('event_interests', ['event_id' => $this->event->id, 'community_id' => $this->community->id, 'user_id' => $this->athlete->id]);
    expect(Community::count())->toBe($communityCount)->and(RegistrationDraft::count())->toBe($draftCount)->and($this->membership->fresh()->region_status)->toBe('pending');
    $this->get('/panel/events')->assertOk()->assertSee('Minat Sudah Dikirim');
    $this->actingAs($this->manager)->get('/panel/events')->assertOk()->assertSee('Minat Atlet')->assertSee($this->athlete->name);
});

test('interest requires own active non ended membership', function (string $scenario): void {
    match ($scenario) {
        'pending' => $this->membership->update(['status' => 'pending']),
        'rejected' => $this->membership->update(['status' => 'rejected']),
        'ended' => $this->membership->update(['left_at' => now()]),
        'foreign' => $this->membership->update(['user_id' => User::factory()->create()->id]),
        'inactive-community' => $this->community->update(['is_active' => false]),
    };
    $this->actingAs($this->athlete)->postJson($this->interestUrl, ['community_id' => $this->community->id, 'consent' => '1'])->assertUnprocessable()->assertJsonValidationErrors('community_id');
    $this->assertDatabaseCount('event_interests', 0);
})->with(['pending', 'rejected', 'ended', 'foreign', 'inactive-community']);

test('interest enforces consent and athlete category eligibility', function (string $scenario): void {
    $payload = ['community_id' => $this->community->id, 'consent' => '1'];
    match ($scenario) {
        'consent' => $payload['consent'] = '0',
        'gender' => $this->athlete->update(['gender' => 'female']),
        'age' => $this->athlete->update(['birth_date' => '1990-01-01']),
        'type' => $this->category->update(['allow_club' => false, 'allow_community' => false]),
    };
    $this->actingAs($this->athlete)->postJson($this->interestUrl, $payload)->assertUnprocessable();
    $this->assertDatabaseCount('event_interests', 0);
})->with(['consent', 'gender', 'age', 'type']);

test('closed or hidden event refuses interest', function (string $scenario): void {
    match ($scenario) {
        'closed' => $this->event->update(['registration_open' => false]),
        'expired' => $this->event->update(['registration_deadline' => now()->subMinute()]),
        'hidden' => $this->event->update(['status' => 'draft']),
    };
    $response = $this->actingAs($this->athlete)->postJson($this->interestUrl, ['community_id' => $this->community->id, 'consent' => '1']);
    expect(in_array($response->status(), [404, 422], true))->toBeTrue();
    $this->assertDatabaseCount('event_interests', 0);
})->with(['closed', 'expired', 'hidden']);

test('selected athlete returns to own team instead of sending interest again', function (): void {
    RegistrationDraft::create(['user_id' => $this->manager->id, 'community_id' => $this->community->id, 'event_id' => $this->event->id, 'event_category_id' => $this->category->id, 'payload' => ['members' => [['user_id' => $this->athlete->id]]], 'revision' => 1, 'expires_at' => now()->addWeek()]);
    $this->actingAs($this->athlete)->get(route('panel.event-interests.create', $this->event->slug))->assertRedirect(route('my-events.index'));
    $this->post($this->interestUrl, ['community_id' => $this->community->id, 'consent' => '1'])->assertRedirect(route('my-events.index'));
    $this->assertDatabaseCount('event_interests', 0);
});

test('interest discovery finds existing community and never exposes personal contacts', function (): void {
    $this->membership->update(['status' => 'pending']);
    $this->actingAs($this->athlete)->get(route('panel.event-interests.create', ['slug' => $this->event->slug, 'q' => $this->community->name]))
        ->assertOk()->assertSee('Cari Komunitas')->assertSee($this->community->name)->assertDontSee($this->manager->email)->assertDontSee('Buat Komunitas untuk Mendaftar');
});

test('manager cannot read interests after authority ended', function (): void {
    $this->actingAs($this->athlete)->post($this->interestUrl, ['community_id' => $this->community->id, 'consent' => '1'])->assertSessionHasNoErrors();
    $this->actingAs($this->manager)->get('/panel/events')->assertOk()->assertSee($this->athlete->name);
    $this->official->update(['ended_at' => now()]);
    $this->get('/panel/events')->assertOk()->assertDontSee($this->athlete->name);
});

test('interest uses age on event date and refuses another sport', function (): void {
    $this->athlete->update(['birth_date' => now()->subYears(18)->addDay()->toDateString()]);
    $this->category->update(['max_age' => 17]);
    $this->actingAs($this->athlete)->postJson($this->interestUrl, ['community_id' => $this->community->id, 'consent' => '1'])->assertUnprocessable();
    $this->category->update(['max_age' => null, 'min_age' => null]);
    $otherSport = SportCategory::create(['name' => 'Cabor Uji Minat', 'slug' => 'cabor-uji-minat', 'is_active' => true]);
    $this->community->update(['sport_category_id' => $otherSport->id]);
    $this->postJson($this->interestUrl, ['community_id' => $this->community->id, 'consent' => '1'])->assertUnprocessable();
    $this->assertDatabaseCount('event_interests', 0);
});

test('interest denies inactive users and guests', function (): void {
    $this->postJson($this->interestUrl, ['community_id' => $this->community->id, 'consent' => '1'])->assertUnauthorized();
    $this->athlete->update(['is_active' => false]);
    $this->actingAs($this->athlete)->postJson($this->interestUrl, ['community_id' => $this->community->id, 'consent' => '1'])->assertForbidden();
    $this->assertDatabaseCount('event_interests', 0);
});

test('interest inbox scopes community and expires when athlete leaves', function (): void {
    $this->actingAs($this->athlete)->post($this->interestUrl, ['community_id' => $this->community->id, 'consent' => '1'])->assertSessionHasNoErrors();
    $service = app(AthleteEventInterestService::class);
    expect($service->forManager((int) $this->manager->id, (int) $this->community->id)->total())->toBe(1);
    $other = Community::factory()->create(['is_active' => true]);
    try {
        $service->forManager((int) $this->manager->id, (int) $other->id);
        test()->fail('Foreign community inbox must be forbidden.');
    } catch (HttpException $exception) {
        expect($exception->getStatusCode())->toBe(403);
    }
    $this->membership->update(['left_at' => now()]);
    expect($service->forManager((int) $this->manager->id, (int) $this->community->id)->total())->toBe(0);
});

test('active school membership can express interest across sports without joining again', function (): void {
    $otherSport = SportCategory::create(['name' => 'Cabor Sekolah Lain', 'slug' => 'cabor-sekolah-lain', 'is_active' => true]);
    DB::table('schools')->insert(['npsn' => '99887765', 'name' => 'Sekolah Minat Multi Cabor', 'school_level' => 'sma', 'school_status' => 'negeri', 'province_id' => '32', 'city_id' => '32.73', 'dataset_year' => 2026, 'source' => 'test']);
    $this->community->update(['subtype' => 'school', 'npsn' => '99887765', 'sport_category_id' => $otherSport->id]);
    $this->category->update(['allow_club' => false, 'allow_community' => true, 'allowed_community_subtypes' => ['school_sma']]);
    $this->actingAs($this->athlete)->get('/panel/my-communities')->assertOk()->assertSee($this->community->name);
    $this->get(route('panel.event-interests.create', $this->event->slug))->assertOk()
        ->assertSee('Keanggotaan Anda sudah aktif')
        ->assertSee($this->community->name)
        ->assertSee('Bukan permintaan bergabung ulang')
        ->assertDontSee('Belum ada keanggotaan aktif');
    $this->post($this->interestUrl, ['community_id' => $this->community->id, 'consent' => '1'])->assertRedirect()->assertSessionHasNoErrors();
    $this->assertDatabaseHas('event_interests', ['event_id' => $this->event->id, 'community_id' => $this->community->id, 'user_id' => $this->athlete->id]);
    expect($this->membership->fresh()->status)->toBe('active');
});

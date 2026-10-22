<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\EmailDelivery;
use App\Models\User;
use App\Modules\Community\Models\Community;
use App\Modules\MasterData\Models\School;
use App\Modules\MasterData\Models\SportCategory;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SchoolOnboardingTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        $this->spy(EmailDelivery::class);
    }

    public function test_school_onboarding_registration_creates_pending_official_and_registration_row(): void
    {
        Role::firstOrCreate(['name' => 'member', 'guard_name' => 'web', 'panel_access' => 'external']);
        Role::firstOrCreate(['name' => 'community_manager', 'guard_name' => 'web', 'panel_access' => 'external']);

        $user = User::factory()->create([
            'is_active' => true,
        ]);
        $user->assignRole('member');

        $sport = SportCategory::query()->firstOrCreate(
            ['slug' => 'basketball'],
            ['name' => 'Basketball', 'is_active' => true]
        );

        $school = School::create([
            'npsn' => '30000003',
            'name' => 'SMA Negeri 3 Test Master',
            'school_level' => 'sma',
            'school_status' => 'negeri',
            'province_id' => '32',
            'city_id' => '32.73',
            'address' => 'Jl. Pendidikan SMA No. 3',
            'dataset_year' => 2026,
            'source' => 'test',
        ]);

        $payload = [
            'name' => 'SMA Negeri 3 Test Master',
            'subtype' => 'school_sma',
            'npsn' => $school->npsn,
            'sport_category_id' => $sport->id,
            'province_id' => '32',
            'city_id' => '32.73',
            'address' => 'Jl. Pendidikan SMA No. 3',
            'contact_person' => 'Guru Olahraga',
            'contact_phone' => '081234567890',
            'contact_email' => 'official@sman3testing.sch.id',
        ];

        $response = $this->actingAs($user)->post(route('panel.community.store'), $payload);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $this->assertDatabaseHas('school_registrations', [
            'user_id' => $user->id,
            'npsn' => $school->npsn,
            'contact_email' => 'official@sman3testing.sch.id',
            'status' => 'pending',
            'admin_review_status' => 'pending',
        ]);

        $this->assertDatabaseHas('community_officials', [
            'user_id' => $user->id,
            'status' => 'pending',
        ]);

        $this->assertDatabaseMissing('community_officials', [
            'user_id' => $user->id,
            'status' => 'active',
        ]);

        $this->assertFalse(
            $user->fresh()->hasRole('community_manager'),
            'User must not be granted community_manager role immediately upon registration.'
        );
    }

    public function test_admin_review_rejects_unverified_school_registration(): void
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'panel_access' => 'internal']);
        Role::firstOrCreate(['name' => 'member', 'guard_name' => 'web', 'panel_access' => 'external']);

        $admin = User::factory()->create([
            'is_active' => true,
        ]);
        $admin->assignRole('admin');

        $user = User::factory()->create([
            'is_active' => true,
        ]);
        $user->assignRole('member');

        $community = Community::factory()->create([
            'subtype' => 'school',
            'status' => 'draft',
        ]);

        $registrationId = DB::table('school_registrations')->insertGetId([
            'community_id' => $community->id,
            'user_id' => $user->id,
            'npsn' => '30000003',
            'contact_email' => 'official@sman3testing.sch.id',
            'email_hash' => hash('sha256', 'official@sman3testing.sch.id'),
            'email_token_hash' => null,
            'email_verified_at' => null,
            'evidence_path' => null,
            'evidence_disk' => 'local',
            'status' => 'pending',
            'admin_review_status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $url = "/admin/school-registrations/{$registrationId}/review";

        $response = $this->actingAs($admin)->post($url, [
            'action' => 'approve',
        ]);

        $this->assertTrue(
            in_array($response->status(), [403, 422], true),
            "Expected review endpoint to reject unverified/evidence-less registration with 403 or 422, got {$response->status()}"
        );
    }
}

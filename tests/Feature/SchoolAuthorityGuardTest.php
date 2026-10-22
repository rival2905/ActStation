<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\EmailDelivery;
use App\Models\User;
use App\Modules\Community\Models\Community;
use App\Modules\Community\Models\CommunityOfficial;
use App\Modules\Community\Models\SchoolRegistration;
use App\Modules\MasterData\Models\School;
use App\Modules\MasterData\Models\SportCategory;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SchoolAuthorityGuardTest extends TestCase
{
    use DatabaseTransactions;

    private static int $npsnSequence = 30000090;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        $this->spy(EmailDelivery::class);

        DB::table('wilayah')->insertOrIgnore([
            ['kode' => '32', 'nama' => 'Jawa Barat'],
            ['kode' => '32.73', 'nama' => 'Kota Bandung'],
        ]);

        School::firstOrCreate(
            ['npsn' => '30000001'],
            [
                'name' => 'SMA Master 30000001',
                'school_level' => 'sma',
                'school_status' => 'negeri',
                'province_id' => '32',
                'city_id' => '32.73',
                'address' => 'Jl. Master 1',
            ]
        );

        School::firstOrCreate(
            ['npsn' => '30000088'],
            [
                'name' => 'SMA Master 30000088',
                'school_level' => 'sma',
                'school_status' => 'negeri',
                'province_id' => '32',
                'city_id' => '32.73',
                'address' => 'Jl. Master 88',
            ]
        );

        Role::firstOrCreate(['name' => 'member', 'guard_name' => 'web', 'panel_access' => 'external']);
        Role::firstOrCreate(['name' => 'community_manager', 'guard_name' => 'web', 'panel_access' => 'external']);
    }

    /**
     * @return array{0: User, 1: Community, 2: CommunityOfficial, 3: SchoolRegistration}
     */
    private function createTrackedSchool(string $status = 'approved'): array
    {
        self::$npsnSequence++;
        $npsn = (string) self::$npsnSequence;

        School::firstOrCreate(
            ['npsn' => $npsn],
            [
                'name' => 'SMA Guard Test '.$npsn,
                'school_level' => 'sma',
                'school_status' => 'negeri',
                'province_id' => '32',
                'city_id' => '32.73',
                'address' => 'Jl. Pengawalan No. 1',
            ]
        );

        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole('community_manager');

        $community = Community::create([
            'name' => 'SMA Guard Test '.$npsn,
            'slug' => 'sma-guard-test-'.$npsn.'-'.uniqid(),
            'subtype' => 'school',
            'npsn' => $npsn,
            'status' => 'verified',
            'is_active' => true,
            'province_id' => '32',
            'city_id' => '32.73',
            'address' => 'Jl. Pengawalan No. 1',
            'contact_person' => 'Kepala Sekolah',
            'contact_phone' => '081111111111',
            'contact_email' => 'official'.$npsn.'@smaguard.sch.id',
            'sport_category_id' => null,
        ]);

        $official = CommunityOfficial::create([
            'community_id' => $community->id,
            'user_id' => $user->id,
            'position' => 'manager',
            'status' => 'active',
            'started_at' => now()->toDateString(),
            'ended_at' => null,
        ]);

        $registration = SchoolRegistration::create([
            'community_id' => $community->id,
            'user_id' => $user->id,
            'npsn' => $community->npsn,
            'contact_email' => $community->contact_email,
            'email_hash' => hash('sha256', (string) $community->contact_email),
            'status' => $status,
            'admin_review_status' => $status === 'approved' ? 'approved' : 'pending',
        ]);

        return [$user, $community, $official, $registration];
    }

    /**
     * @return array{0: User, 1: Community, 2: CommunityOfficial}
     */
    private function createLegacyCommunity(string $subtype = 'club'): array
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole('community_manager');

        $community = Community::create([
            'name' => 'Klub Legacy Test',
            'slug' => 'klub-legacy-test-'.uniqid(),
            'subtype' => $subtype,
            'npsn' => $subtype === 'school' ? '30000088' : null,
            'status' => 'verified',
            'is_active' => true,
            'province_id' => '32',
            'city_id' => '32.73',
            'address' => 'Jl. Klub Legacy No. 2',
            'contact_person' => 'Ketua Klub',
            'contact_phone' => '082222222222',
            'contact_email' => 'klub@legacy.com',
            'sport_category_id' => null,
        ]);

        $official = CommunityOfficial::create([
            'community_id' => $community->id,
            'user_id' => $user->id,
            'position' => 'manager',
            'status' => 'active',
            'started_at' => now()->toDateString(),
            'ended_at' => null,
        ]);

        return [$user, $community, $official];
    }

    public function test_new_school_profile_update_rejects_identity_alteration_pending_and_approved(): void
    {
        foreach (['pending', 'approved'] as $status) {
            [$user, $community] = $this->createTrackedSchool($status);

            // Attempt to alter contact_email
            $response = $this->actingAs($user)->put(route('panel.profile.update'), [
                'name' => $community->name,
                'subtype' => 'school_sma',
                'npsn' => $community->npsn,
                'province_id' => '32',
                'city_id' => '32.73',
                'contact_email' => 'tampered@example.com',
            ]);
            $response->assertSessionHasErrors(['contact_email']);

            // Attempt to alter npsn
            $response = $this->actingAs($user)->put(route('panel.profile.update'), [
                'name' => $community->name,
                'subtype' => 'school_sma',
                'npsn' => '30000001',
                'province_id' => '32',
                'city_id' => '32.73',
                'contact_email' => $community->contact_email,
            ]);
            $response->assertSessionHasErrors(['npsn']);

            // Attempt to alter subtype away from school
            $response = $this->actingAs($user)->put(route('panel.profile.update'), [
                'name' => $community->name,
                'subtype' => 'club',
                'province_id' => '32',
                'city_id' => '32.73',
                'contact_email' => $community->contact_email,
            ]);
            $response->assertSessionHasErrors(['subtype']);

            // Attempt to assign sport (sport stays null for new school)
            $sport = SportCategory::query()->firstOrCreate(
                ['slug' => 'basketball'],
                ['name' => 'Basketball', 'is_active' => true]
            );
            $response = $this->actingAs($user)->put(route('panel.profile.update'), [
                'name' => $community->name,
                'subtype' => 'school_sma',
                'npsn' => $community->npsn,
                'province_id' => '32',
                'city_id' => '32.73',
                'contact_email' => $community->contact_email,
                'sport_category_id' => $sport->id,
            ]);
            $response->assertSessionHasErrors(['sport_category_id']);
        }
    }

    public function test_new_school_profile_update_allows_unchanged_identity_and_unrelated_edits(): void
    {
        [$user, $community] = $this->createTrackedSchool('approved');

        $payload = [
            'name' => $community->name,
            'subtype' => 'school_sma',
            'npsn' => $community->npsn,
            'province_id' => '32',
            'city_id' => '32.73',
            'contact_email' => $community->contact_email,
            'sport_category_id' => null,
            'address' => 'Jl. Baru Diperbarui No. 99',
            'contact_person' => 'Wakasek Kesiswaan',
            'contact_phone' => '089999999999',
            'description' => 'Deskripsi sekolah telah diperbarui.',
        ];

        $response = $this->actingAs($user)->put(route('panel.profile.update'), $payload);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('panel.profile.show'));
        $this->assertDatabaseHas('communities', [
            'id' => $community->id,
            'address' => 'Jl. Baru Diperbarui No. 99',
            'contact_person' => 'Wakasek Kesiswaan',
        ]);
    }

    public function test_legacy_or_non_school_profile_update_unaffected(): void
    {
        [$user, $community] = $this->createLegacyCommunity('club');

        $payload = [
            'name' => 'Klub Legacy Diubah',
            'subtype' => 'club',
            'province_id' => '32',
            'city_id' => '32.73',
            'contact_email' => 'new-email@legacy.com',
            'address' => 'Jl. Klub Baru No. 10',
        ];

        $response = $this->actingAs($user)->put(route('panel.profile.update'), $payload);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('panel.profile.show'));
        $this->assertDatabaseHas('communities', [
            'id' => $community->id,
            'contact_email' => 'new-email@legacy.com',
        ]);
    }

    public function test_new_school_officials_store_denied_with_explanation(): void
    {
        [$user, $community] = $this->createTrackedSchool('approved');
        $newPerson = User::factory()->create(['is_active' => true]);

        $response = $this->actingAs($user)->post(route('panel.officials.store'), [
            'user_id' => $newPerson->id,
            'position' => 'manager',
        ]);

        // Must deny with explanation 'gunakan alur terkonfirmasi admin'
        if ($response->status() === 403) {
            $this->assertStringContainsStringIgnoringCase('alur terkonfirmasi admin', $response->getContent() ?: '');
        } else {
            $response->assertSessionHas('error');
            $this->assertStringContainsStringIgnoringCase('alur terkonfirmasi admin', (string) session('error'));
        }

        $this->assertDatabaseMissing('community_officials', [
            'community_id' => $community->id,
            'user_id' => $newPerson->id,
        ]);
    }

    public function test_new_school_officials_destroy_denied_with_explanation(): void
    {
        [$user, $community, $official] = $this->createTrackedSchool('approved');

        $response = $this->actingAs($user)->delete(route('panel.officials.destroy', $official));

        // Must deny with explanation 'gunakan alur terkonfirmasi admin'
        if ($response->status() === 403) {
            $this->assertStringContainsStringIgnoringCase('alur terkonfirmasi admin', $response->getContent() ?: '');
        } else {
            $response->assertSessionHas('error');
            $this->assertStringContainsStringIgnoringCase('alur terkonfirmasi admin', (string) session('error'));
        }

        $this->assertDatabaseHas('community_officials', [
            'id' => $official->id,
        ]);
    }

    public function test_legacy_official_store_and_destroy_preserved_except_last_active_responsible(): void
    {
        [$user, $community, $official] = $this->createLegacyCommunity('club');
        $secondUser = User::factory()->create(['is_active' => true]);

        // Adding an official on legacy community is allowed
        $responseStore = $this->actingAs($user)->post(route('panel.officials.store'), [
            'user_id' => $secondUser->id,
            'position' => 'staff',
        ]);
        $responseStore->assertSessionHasNoErrors();
        $responseStore->assertRedirect(route('panel.officials.index'));
        $this->assertDatabaseHas('community_officials', [
            'community_id' => $community->id,
            'user_id' => $secondUser->id,
            'position' => 'staff',
        ]);

        $secondOfficial = CommunityOfficial::where('community_id', $community->id)
            ->where('user_id', $secondUser->id)
            ->firstOrFail();

        // Deleting non-last official succeeds on legacy
        $responseDestroy = $this->actingAs($user)->delete(route('panel.officials.destroy', $secondOfficial));
        $responseDestroy->assertSessionHasNoErrors();
        $responseDestroy->assertRedirect(route('panel.officials.index'));
        $this->assertDatabaseMissing('community_officials', [
            'id' => $secondOfficial->id,
        ]);
    }
}

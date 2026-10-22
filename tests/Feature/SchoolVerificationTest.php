<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\EmailDelivery;
use App\Models\User;
use App\Modules\Community\Models\Community;
use App\Modules\Community\Models\CommunityOfficial;
use App\Modules\Community\Models\SchoolRegistration;
use App\Modules\Event\Models\Event;
use App\Modules\Event\Models\EventCategory;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SchoolVerificationTest extends TestCase
{
    use DatabaseTransactions;

    protected User $user;

    protected Community $school;

    protected CommunityOfficial $official;

    protected SchoolRegistration $registration;

    protected string $rawToken;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
        $this->spy(EmailDelivery::class);
        Storage::fake('local');

        Role::firstOrCreate(['name' => 'member', 'guard_name' => 'web', 'panel_access' => 'external']);
        Role::firstOrCreate(['name' => 'community_manager', 'guard_name' => 'web', 'panel_access' => 'external']);
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'panel_access' => 'internal']);
        Permission::firstOrCreate(['name' => 'community.verify', 'guard_name' => 'web']);

        $this->user = User::factory()->create(['is_active' => true]);
        $this->user->assignRole('member');

        $this->school = Community::factory()->create([
            'subtype' => 'school',
            'status' => 'draft',
            'npsn' => '20104050',
            'contact_email' => 'sekolah@sma1test.sch.id',
            'is_active' => true,
        ]);

        $this->official = CommunityOfficial::create([
            'community_id' => $this->school->id,
            'user_id' => $this->user->id,
            'position' => 'manager',
            'status' => 'pending',
            'started_at' => today(),
        ]);

        $this->rawToken = Str::random(64);

        $this->registration = SchoolRegistration::create([
            'community_id' => $this->school->id,
            'user_id' => $this->user->id,
            'npsn' => $this->school->npsn,
            'contact_email' => $this->school->contact_email,
            'email_hash' => hash('sha256', Str::lower($this->school->contact_email)),
            'email_token_hash' => hash('sha256', $this->rawToken),
            'expires_at' => now()->addHour(),
            'status' => 'pending',
            'admin_review_status' => 'pending',
            'evidence_disk' => 'local',
        ]);
    }

    public function test_pending_school_is_resumed_instead_of_asking_owner_to_create_again(): void
    {
        $this->registration->update(['email_verified_at' => now(), 'email_token_hash' => null, 'expires_at' => null]);
        $target = route('panel.school-registrations.show', $this->registration->id);

        $this->actingAs($this->user)->get(route('panel.profile.show'))->assertRedirect($target);
        $this->get(route('panel.community.create'))->assertRedirect($target);
        $this->get($target)->assertOk()->assertSee('Sekolah sudah tersimpan')->assertDontSee('Buat Komunitas');
        $this->assertSame('pending', $this->official->fresh()->status);
        $this->assertFalse($this->user->fresh()->hasRole('community_manager'));
    }

    public function test_other_users_cannot_resume_someone_elses_school(): void
    {
        $other = User::factory()->create(['is_active' => true]);
        $other->assignRole('member');
        $this->actingAs($other)->get(route('panel.community.create'))->assertOk();
        $this->get(route('panel.profile.show'))->assertRedirect(route('panel.community.create'));
    }

    public function test_signed_get_verify_does_not_mutate_registration_state(): void
    {
        $signedUrl = URL::temporarySignedRoute(
            'panel.school-registrations.verify',
            $this->registration->expires_at,
            ['registration' => $this->registration->id, 'token' => $this->rawToken]
        );

        $response = $this->actingAs($this->user)->get($signedUrl);

        $response->assertOk();

        $fresh = $this->registration->fresh();
        $this->assertNull($fresh->email_verified_at);
        $this->assertSame('pending', $fresh->status);
        $this->assertSame(hash('sha256', $this->rawToken), $fresh->email_token_hash);
        $this->assertNotNull($fresh->expires_at);
    }

    public function test_rendered_confirmation_action_preserves_the_signed_query_order(): void
    {
        $url = URL::temporarySignedRoute('panel.school-registrations.verify', $this->registration->expires_at, [
            'registration' => $this->registration->id, 'token' => $this->rawToken,
        ]);
        $response = $this->actingAs($this->user)->get($url)->assertOk();
        preg_match('/<form[^>]+action="([^"]*school-registrations\/[^"]*\/verify[^"]*)"/', $response->getContent(), $matches);
        $action = html_entity_decode($matches[1], ENT_QUOTES);

        $this->post($action, ['token' => $this->rawToken, 'consent' => '1'])
            ->assertRedirect(route('panel.school-registrations.show', $this->registration->id));
        $this->assertNotNull($this->registration->fresh()->email_verified_at);
    }

    public function test_immediate_resend_does_not_replace_a_just_sent_token(): void
    {
        $before = $this->registration->fresh()->email_token_hash;
        $this->actingAs($this->user)->post(route('panel.school-registrations.resend', $this->registration->id))
            ->assertSessionHasErrors('email');
        $this->assertSame($before, $this->registration->fresh()->email_token_hash);
        app(EmailDelivery::class)->shouldNotHaveReceived('send');
    }

    public function test_resend_after_cooldown_rotates_token(): void
    {
        $before = $this->registration->email_token_hash;
        $this->travel(61)->seconds();

        $this->actingAs($this->user)->post(route('panel.school-registrations.resend', $this->registration->id))
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertNotSame($before, $this->registration->fresh()->email_token_hash);
    }

    public function test_post_signed_verify_verifies_email_only_not_granting_manager(): void
    {
        $signedUrl = URL::temporarySignedRoute(
            'panel.school-registrations.consume',
            $this->registration->expires_at,
            ['registration' => $this->registration->id, 'token' => $this->rawToken]
        );

        $response = $this->actingAs($this->user)->post($signedUrl, [
            'token' => $this->rawToken,
            'consent' => '1',
        ]);

        $response->assertRedirect(route('panel.school-registrations.show', $this->registration->id));
        $response->assertSessionHas('success');

        $fresh = $this->registration->fresh();
        $this->assertNotNull($fresh->email_verified_at);
        $this->assertNull($fresh->email_token_hash);
        $this->assertNull($fresh->expires_at);
        $this->assertSame('pending', $fresh->status);

        $this->assertSame('pending', $this->official->fresh()->status);
        $this->assertFalse($this->user->fresh()->hasRole('community_manager'));
        $this->assertSame('draft', $this->school->fresh()->status);
    }

    public function test_replay_verification_is_rejected_with_403(): void
    {
        $signedUrl = URL::temporarySignedRoute(
            'panel.school-registrations.consume',
            $this->registration->expires_at,
            ['registration' => $this->registration->id, 'token' => $this->rawToken]
        );

        $this->actingAs($this->user)->post($signedUrl, [
            'token' => $this->rawToken,
            'consent' => '1',
        ])->assertRedirect();

        $replay = $this->actingAs($this->user)->post($signedUrl, [
            'token' => $this->rawToken,
            'consent' => '1',
        ]);

        $replay->assertStatus(403);
    }

    public function test_wrong_actor_access_returns_404(): void
    {
        $otherUser = User::factory()->create(['is_active' => true]);
        $otherUser->assignRole('member');

        $signedUrl = URL::temporarySignedRoute(
            'panel.school-registrations.verify',
            $this->registration->expires_at,
            ['registration' => $this->registration->id, 'token' => $this->rawToken]
        );

        $this->actingAs($otherUser)->get($signedUrl)->assertNotFound();

        $consumeUrl = URL::temporarySignedRoute(
            'panel.school-registrations.consume',
            $this->registration->expires_at,
            ['registration' => $this->registration->id, 'token' => $this->rawToken]
        );

        $this->actingAs($otherUser)->post($consumeUrl, [
            'token' => $this->rawToken,
            'consent' => '1',
        ])->assertNotFound();
    }

    public function test_tampered_or_expired_signature_returns_403(): void
    {
        $signedUrl = URL::temporarySignedRoute(
            'panel.school-registrations.verify',
            $this->registration->expires_at,
            ['registration' => $this->registration->id, 'token' => $this->rawToken]
        );

        $this->actingAs($this->user)->get($signedUrl.'tamper')->assertStatus(403);

        $expiredUrl = URL::temporarySignedRoute(
            'panel.school-registrations.verify',
            now()->subMinutes(10),
            ['registration' => $this->registration->id, 'token' => $this->rawToken]
        );
        $this->actingAs($this->user)->get($expiredUrl)->assertStatus(403);
    }

    public function test_evidence_upload_stores_private_mime_and_rejects_bad_files(): void
    {
        $endpoint = route('panel.school-registrations.evidence', $this->registration->id);

        $badFile = UploadedFile::fake()->create('malicious.exe', 500, 'application/x-msdownload');
        $failResponse = $this->actingAs($this->user)->post($endpoint, [
            'evidence' => $badFile,
        ]);
        $failResponse->assertSessionHasErrors('evidence');

        $pdfFile = UploadedFile::fake()->create('surat_tugas.pdf', 300, 'application/pdf');
        $successResponse = $this->actingAs($this->user)->post($endpoint, [
            'evidence' => $pdfFile,
        ]);
        $successResponse->assertRedirect();
        $successResponse->assertSessionHas('success');

        $fresh = $this->registration->fresh();
        $this->assertNotNull($fresh->evidence_path);
        $this->assertSame('local', $fresh->evidence_disk);
        $this->assertStringStartsWith('school-authorizations/'.$this->registration->id.'/', $fresh->evidence_path);

        Storage::disk('local')->assertExists($fresh->evidence_path);
    }

    public function test_admin_review_requires_permission_verified_email_and_physical_evidence(): void
    {
        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole('admin');
        $admin->givePermissionTo('community.verify');

        $unauthorizedAdmin = User::factory()->create(['is_active' => true]);
        $unauthorizedAdmin->assignRole('admin');

        $reviewEndpoint = route('admin.school-registrations.review', $this->registration->id);

        $this->actingAs($unauthorizedAdmin)->post($reviewEndpoint, [
            'action' => 'approve',
        ])->assertStatus(403);

        $this->user->givePermissionTo('community.verify');
        $this->actingAs($this->user)->post($reviewEndpoint, [
            'action' => 'approve',
        ])->assertStatus(403);

        $invalidApproval = $this->actingAs($admin)->post($reviewEndpoint, [
            'action' => 'approve',
        ]);
        $invalidApproval->assertSessionHasErrors('evidence');

        $evidencePath = 'school-authorizations/'.$this->registration->id.'/surat.pdf';
        Storage::disk('local')->put($evidencePath, '%PDF-1.4 test content');

        $this->registration->update([
            'email_verified_at' => now(),
            'evidence_path' => $evidencePath,
            'evidence_disk' => 'local',
        ]);

        $approvalResponse = $this->actingAs($admin)->post($reviewEndpoint, [
            'action' => 'approve',
        ]);
        $approvalResponse->assertRedirect();
        $approvalResponse->assertSessionHas('success');

        $fresh = $this->registration->fresh();
        $this->assertSame('approved', $fresh->status);
        $this->assertSame('approved', $fresh->admin_review_status);
        $this->assertSame($admin->id, $fresh->reviewed_by);

        $this->assertSame('active', $this->official->fresh()->status);
        $this->assertTrue($this->user->fresh()->hasRole('community_manager'));
        $this->assertSame('draft', $this->school->fresh()->status);
    }

    public function test_safe_resume_context_persists(): void
    {
        $event = Event::factory()->create(['status' => 'published']);
        $category = EventCategory::factory()->create(['event_id' => $event->id]);
        $this->registration->update([
            'event_id' => $event->id,
            'event_category_id' => $category->id,
        ]);

        $responseBefore = $this->actingAs($this->user)->get(route('panel.school-registrations.show', $this->registration->id));
        $responseBefore->assertOk();
        $responseBefore->assertViewHas('resumeUrl', null);

        $this->registration->update(['status' => 'approved', 'admin_review_status' => 'approved']);

        $responseAfter = $this->actingAs($this->user)->get(route('panel.school-registrations.show', $this->registration->id));
        $responseAfter->assertOk();
        $resumeUrl = $responseAfter->viewData('resumeUrl');
        $this->assertNotNull($resumeUrl);
        $this->assertSame(route('panel.community-events.create', ['slug' => $event->slug, 'event_category_id' => $category->id]), $resumeUrl);
    }
}

<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

uses(DatabaseTransactions::class);

test('personal profile shows own education and vault without community membership', function (): void {
    Http::preventStrayRequests();
    $owner = User::factory()->create(['is_active' => true]);
    $other = User::factory()->create();
    $owner->educations()->create(['institution_name' => 'Sekolah Pemilik', 'education_type' => 'school', 'identifier_type' => 'nisn', 'identifier_value' => '0012345678', 'education_detail' => 'XI / IPA']);
    $other->educations()->create(['institution_name' => 'Sekolah Rahasia Orang Lain', 'education_type' => 'school', 'identifier_type' => 'nisn', 'identifier_value' => '9999999999']);
    $owner->documents()->create(['category' => 'family_card', 'name' => 'KK Pemilik', 'file_path' => 'athlete_documents/owner.pdf', 'status' => 'pending', 'is_public' => false]);
    $other->documents()->create(['category' => 'family_card', 'name' => 'KK Orang Lain', 'file_path' => 'athlete_documents/other.pdf', 'status' => 'pending', 'is_public' => false]);
    $this->actingAs($owner)->get('/user/profile')->assertOk()->assertSee('Sekolah Pemilik')->assertSee('0012345678')->assertSee('XI / IPA')->assertSee('KK Pemilik')->assertDontSee('Sekolah Rahasia Orang Lain')->assertDontSee('KK Orang Lain')->assertDontSee('athlete_documents/owner.pdf');
});

test('personal documents are private and scoped by user morph relation', function (): void {
    Storage::fake('local');
    Storage::disk('local')->put('athlete_documents/owner.pdf', '%PDF-1.4 test');
    $owner = User::factory()->create(['is_active' => true]);
    $other = User::factory()->create(['is_active' => true]);
    $doc = $owner->documents()->create(['category' => 'kk', 'name' => 'KK.pdf', 'file_path' => 'athlete_documents/owner.pdf', 'status' => 'pending', 'is_public' => false]);
    $this->get('/user/profile')->assertRedirect();
    $this->actingAs($other)->get('/user/profile/documents/'.$doc->id)->assertNotFound();
    $this->actingAs($owner)->get('/user/profile/documents/'.$doc->id)->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
    $doc->update(['file_path' => '../private.pdf']);
    $this->get('/user/profile/documents/'.$doc->id)->assertNotFound();
    $doc->update(['file_path' => 'athlete_documents/missing.pdf']);
    $this->get('/user/profile/documents/'.$doc->id)->assertNotFound();
    $doc->update(['is_public' => true, 'file_path' => 'athlete_documents/owner.pdf']);
    $this->get('/user/profile/documents/'.$doc->id)->assertNotFound();
    $doc->update(['is_public' => false, 'documentable_type' => 'App\Modules\Community\Models\Community']);
    $this->get('/user/profile/documents/'.$doc->id)->assertNotFound();
});

test('personal profile updates only basic fields and clears old email verification', function (): void {
    Http::preventStrayRequests();
    $owner = User::factory()->create(['is_active' => true, 'birth_date' => '2000-01-01', 'gender' => 'male', 'email_verified_at' => now()]);
    $this->actingAs($owner)->put('/user/profile', ['name' => 'Nama Baru', 'email' => 'profile-changed@example.test', 'phone_number' => '08123456789', 'birth_date' => '2010-01-01', 'gender' => 'female', 'is_active' => false])->assertRedirect('/user/profile')->assertSessionHasNoErrors();
    expect($owner->fresh()->name)->toBe('Nama Baru')->and($owner->fresh()->email_verified_at)->toBeNull()->and($owner->fresh()->birth_date->format('Y-m-d'))->toBe('2000-01-01')->and($owner->fresh()->gender)->toBe('male')->and($owner->fresh()->is_active)->toBeTrue();
});

test('inactive user cannot access personal profile', function (): void {
    $this->actingAs(User::factory()->create(['is_active' => false]))->get('/user/profile')->assertForbidden();
});

<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Community\Models\Document;
use App\Modules\Event\Models\Event;
use App\Modules\Event\Models\EventCategory;
use App\Modules\MasterData\Models\DocumentType;
use App\Modules\MasterData\Models\SportCategory;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(DatabaseTransactions::class);

beforeEach(function (): void {
    Role::firstOrCreate(['name' => 'superadmin', 'guard_name' => 'web', 'panel_access' => 'both']);

    Permission::firstOrCreate(['name' => 'document-type.view', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'document-type.create', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'document-type.update', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'document-type.delete', 'guard_name' => 'web']);

    $this->sport = SportCategory::query()->firstOrCreate(
        ['slug' => 'basketball'],
        ['name' => 'Basketball', 'is_active' => true]
    );

    $this->admin = User::factory()->create();
    $this->admin->assignRole('superadmin');
});

test('referenced category with no user documents freezes code scope and applicable_entities but allows name update', function (): void {
    $docType = DocumentType::create([
        'code' => 'ref_cat_doc',
        'name' => 'Dokumen Referensi Kategori',
        'scope' => 'athlete',
        'applicable_entities' => ['club'],
        'description' => 'Deskripsi lama',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $event = Event::factory()->create([
        'sport_category_id' => $this->sport->id,
        'user_id' => $this->admin->id,
        'status' => 'draft',
    ]);

    EventCategory::factory()->create([
        'event_id' => $event->id,
        'name' => 'Kategori Uji Dokumen',
        'required_documents' => ['ref_cat_doc'],
    ]);

    expect($docType->isInUse())->toBeTrue();

    // Rejected when attempting to mutate frozen fields
    $response = $this->actingAs($this->admin)
        ->from(route('admin.document-types.edit', $docType))
        ->put(route('admin.document-types.update', $docType), [
            'code' => 'mutated_ref_cat_doc',
            'name' => 'Dokumen Coba Ubah',
            'scope' => 'team',
            'applicable_entities' => ['institution'],
            'description' => 'Deskripsi baru',
            'sort_order' => 2,
            'is_active' => 1,
        ]);

    $response->assertSessionHasErrors(['code', 'scope', 'applicable_entities']);

    $docType->refresh();
    expect($docType->code)->toBe('ref_cat_doc')
        ->and($docType->scope)->toBe('athlete')
        ->and($docType->applicable_entities)->toBe(['club'])
        ->and($docType->name)->toBe('Dokumen Referensi Kategori');

    // Allowed when retaining same code, scope, applicable_entities while mutating name, description, sort_order, is_active
    $responseSuccess = $this->actingAs($this->admin)
        ->put(route('admin.document-types.update', $docType), [
            'code' => 'ref_cat_doc',
            'name' => 'Dokumen Referensi Kategori Diperbarui',
            'scope' => 'athlete',
            'applicable_entities' => ['club'],
            'description' => 'Deskripsi diperbarui',
            'sort_order' => 10,
            'is_active' => 0,
        ]);

    $responseSuccess->assertRedirect(route('admin.document-types.index'));
    $responseSuccess->assertSessionHas('success');

    $docType->refresh();
    expect($docType->name)->toBe('Dokumen Referensi Kategori Diperbarui')
        ->and($docType->description)->toBe('Deskripsi diperbarui')
        ->and($docType->sort_order)->toBe(10)
        ->and($docType->is_active)->toBeFalse();

    // Deletion blocked when in use
    $responseDelete = $this->actingAs($this->admin)
        ->from(route('admin.document-types.index'))
        ->delete(route('admin.document-types.destroy', $docType));

    $responseDelete->assertSessionHas('error');
    expect(DocumentType::where('id', $docType->id)->exists())->toBeTrue();
});

test('evidence only referenced document type freezes code scope and applicable_entities and blocks deletion', function (): void {
    $docType = DocumentType::create([
        'code' => 'ref_evidence_doc',
        'name' => 'Dokumen Bukti Vault',
        'scope' => 'athlete',
        'applicable_entities' => ['school'],
        'is_active' => true,
    ]);

    Document::create([
        'documentable_type' => User::class,
        'documentable_id' => $this->admin->id,
        'category' => 'ref_evidence_doc',
        'name' => 'Bukti Dokumen Atlet',
        'file_path' => 'documents/test.pdf',
        'status' => 'verified',
    ]);

    expect($docType->isInUse())->toBeTrue();

    // Rejected when attempting to mutate frozen fields
    $response = $this->actingAs($this->admin)
        ->from(route('admin.document-types.edit', $docType))
        ->put(route('admin.document-types.update', $docType), [
            'code' => 'mutated_ref_evidence_doc',
            'name' => 'Dokumen Bukti Diubah',
            'scope' => 'team',
            'applicable_entities' => ['club'],
        ]);

    $response->assertSessionHasErrors(['code', 'scope', 'applicable_entities']);

    $docType->refresh();
    expect($docType->code)->toBe('ref_evidence_doc')
        ->and($docType->scope)->toBe('athlete')
        ->and($docType->applicable_entities)->toBe(['school']);

    // Deletion blocked
    $responseDelete = $this->actingAs($this->admin)
        ->from(route('admin.document-types.index'))
        ->delete(route('admin.document-types.destroy', $docType));

    $responseDelete->assertSessionHas('error');
    expect(DocumentType::where('id', $docType->id)->exists())->toBeTrue();
});

test('unused document type allows full mutation and deletion', function (): void {
    $docType = DocumentType::create([
        'code' => 'unused_doc_type',
        'name' => 'Dokumen Belum Digunakan',
        'scope' => 'athlete',
        'applicable_entities' => ['free_association'],
        'is_active' => true,
    ]);

    expect($docType->isInUse())->toBeFalse();

    // Full mutation allowed
    $responseUpdate = $this->actingAs($this->admin)
        ->put(route('admin.document-types.update', $docType), [
            'code' => 'unused_doc_type_mutated',
            'name' => 'Dokumen Unused Berhasil Diubah',
            'scope' => 'team',
            'applicable_entities' => ['university'],
            'is_active' => 1,
            'sort_order' => 5,
        ]);

    $responseUpdate->assertRedirect(route('admin.document-types.index'));
    $responseUpdate->assertSessionHas('success');

    $docType->refresh();
    expect($docType->code)->toBe('unused_doc_type_mutated')
        ->and($docType->name)->toBe('Dokumen Unused Berhasil Diubah')
        ->and($docType->scope)->toBe('team')
        ->and($docType->applicable_entities)->toBe(['university']);

    // Deletion allowed
    $responseDelete = $this->actingAs($this->admin)
        ->delete(route('admin.document-types.destroy', $docType));

    $responseDelete->assertRedirect(route('admin.document-types.index'));
    $responseDelete->assertSessionHas('success');

    expect(DocumentType::where('id', $docType->id)->exists())->toBeFalse();
});

test('applicable_entities treats null and empty array as universal semantic and permits set reordering', function (): void {
    $docTypeUniversal = DocumentType::create([
        'code' => 'universal_safety_doc',
        'name' => 'Dokumen Universal',
        'scope' => 'athlete',
        'applicable_entities' => null,
        'is_active' => true,
    ]);

    $event = Event::factory()->create([
        'sport_category_id' => $this->sport->id,
        'user_id' => $this->admin->id,
        'status' => 'draft',
    ]);

    EventCategory::factory()->create([
        'event_id' => $event->id,
        'name' => 'Kategori Universal',
        'required_documents' => ['universal_safety_doc'],
    ]);

    expect($docTypeUniversal->isInUse())->toBeTrue();

    // Sending empty array [] for null model should match universal semantic and allow update
    $response = $this->actingAs($this->admin)
        ->put(route('admin.document-types.update', $docTypeUniversal), [
            'code' => 'universal_safety_doc',
            'name' => 'Dokumen Universal Updated',
            'scope' => 'athlete',
            'applicable_entities' => [],
        ]);

    $response->assertRedirect(route('admin.document-types.index'));
    $response->assertSessionHas('success');

    // Test reordered set
    $docTypeMulti = DocumentType::create([
        'code' => 'multi_entity_doc',
        'name' => 'Dokumen Multi Entitas',
        'scope' => 'team',
        'applicable_entities' => ['club', 'school'],
        'is_active' => true,
    ]);

    EventCategory::factory()->create([
        'event_id' => $event->id,
        'name' => 'Kategori Multi',
        'required_documents' => ['multi_entity_doc'],
    ]);

    $responseReorder = $this->actingAs($this->admin)
        ->put(route('admin.document-types.update', $docTypeMulti), [
            'code' => 'multi_entity_doc',
            'name' => 'Dokumen Multi Diperbarui',
            'scope' => 'team',
            'applicable_entities' => ['school', 'club'],
        ]);

    $responseReorder->assertRedirect(route('admin.document-types.index'));
    $responseReorder->assertSessionHas('success');
});

test('document type scope validates exact allowed enum athlete and team', function (): void {
    $response = $this->actingAs($this->admin)
        ->post(route('admin.document-types.store'), [
            'code' => 'invalid_scope_doc',
            'name' => 'Invalid Scope',
            'scope' => 'community',
        ]);

    $response->assertSessionHasErrors(['scope']);
});

test('new category rejects inactive document type while existing category retains referenced inactive type', function (): void {
    $inactiveDoc = DocumentType::create([
        'code' => 'inactive_legacy_doc',
        'name' => 'Dokumen Lama Nonaktif',
        'scope' => 'team',
        'applicable_entities' => ['club'],
        'is_active' => false,
    ]);

    $event = Event::factory()->create([
        'sport_category_id' => $this->sport->id,
        'user_id' => $this->admin->id,
        'status' => 'draft',
    ]);

    // Reject adding inactive doc type to new category
    $responseNew = $this->actingAs($this->admin)
        ->post(route('admin.events.categories.store', $event), [
            'name' => 'Kategori Baru Dengan Doc Inactive',
            'match_type' => '5x5',
            'gender' => 'male',
            'registration_flow' => 'screening_first',
            'registration_fee' => 0,
            'allow_club' => 1,
            'required_documents' => ['inactive_legacy_doc'],
        ]);

    $responseNew->assertSessionHasErrors(['required_documents']);

    // Existing category already having inactive_legacy_doc is preserved on update
    $existingCategory = EventCategory::factory()->create([
        'event_id' => $event->id,
        'name' => 'Kategori Historis',
        'match_type' => '5x5',
        'gender' => 'male',
        'registration_flow' => 'screening_first',
        'registration_fee' => 0,
        'allow_club' => true,
        'required_documents' => ['inactive_legacy_doc'],
    ]);

    $responseUpdate = $this->actingAs($this->admin)
        ->put(route('admin.events.categories.update', [$event, $existingCategory]), [
            'name' => 'Kategori Historis Diperbarui',
            'match_type' => '5x5',
            'gender' => 'male',
            'registration_flow' => 'screening_first',
            'registration_fee' => 0,
            'allow_club' => 1,
            'required_documents' => ['inactive_legacy_doc'],
        ]);

    $responseUpdate->assertSessionHasNoErrors();
    expect($existingCategory->fresh()->name)->toBe('Kategori Historis Diperbarui');
});

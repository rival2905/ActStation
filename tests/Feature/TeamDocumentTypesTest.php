<?php

declare(strict_types=1);

use App\Modules\MasterData\Models\DocumentType;
use Database\Seeders\DocumentTypeSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;

uses(DatabaseTransactions::class);

test('document entity applicability separates schools from institutions while retaining universal legacy types', function (): void {
    $school = new DocumentType(['scope' => 'team', 'applicable_entities' => ['school']]);
    expect($school->appliesToEntity('school'))->toBeTrue()
        ->and($school->appliesToEntity('school_smp'))->toBeTrue()
        ->and($school->appliesToEntity('institution'))->toBeFalse()
        ->and($school->appliesToEntity('club'))->toBeFalse();
    $assignment = new DocumentType(['scope' => 'team', 'applicable_entities' => ['institution']]);
    expect($assignment->appliesToEntity('institution'))->toBeTrue()
        ->and($assignment->appliesToEntity('school'))->toBeFalse();
    expect((new DocumentType(['applicable_entities' => null]))->appliesToEntity('institution'))->toBeTrue()
        ->and((new DocumentType(['applicable_entities' => []]))->appliesToEntity('school'))->toBeTrue();
});

test('school team letters and assignments are team documents not individual athlete evidence', function (): void {
    $this->seed(DocumentTypeSeeder::class);
    foreach (['school_team_letter', 'team_assignment_letter', 'team_mandate_letter'] as $code) {
        $type = DocumentType::where('code', $code)->first();
        expect($type)->not->toBeNull();
        expect($type->scope)->toBe('team')->and($type->is_active)->toBeTrue();
    }
    expect(DocumentType::where('code', 'recommendation_letter')->value('scope'))->toBe('team')
        ->and(DocumentType::where('code', 'id_card')->value('scope'))->toBe('athlete')
        ->and(DocumentType::where('code', 'student_card')->value('scope'))->toBe('athlete');
});

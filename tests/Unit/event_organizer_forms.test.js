import test from 'node:test';
import assert from 'node:assert/strict';
import {
    filterEligibleParents,
    getParentOptions,
    getContentCreatorSchoolPreset,
    getOpeningWillingnessPreset,
    getOpeningArtDetailPreset,
    resolveScopedOldInput
} from '../../public/assets/js/event-organizer-forms.js';

test('filterEligibleParents only allows select questions with same target and strictly earlier order', () => {
    const questions = [
        { id: 1, target: 'team', type: 'select', order: 1, label: 'Berminat opening?', options: ['Ya', 'Tidak'] },
        { id: 2, target: 'institution', type: 'select', order: 2, label: 'Tipe instansi', options: ['Negeri', 'Swasta'] },
        { id: 3, target: 'team', type: 'text', order: 3, label: 'Nama tim kesenian' },
        { id: 4, target: 'team', type: 'select', order: 4, label: 'Peralatan musik bawa sendiri?', options: ['Ya', 'Tidak'] },
        { id: 5, target: 'team', type: 'select', order: 5, label: 'Kategori penampilan', options: ['Tari', 'Musik'] },
    ];

    // Current question: target 'team', order 5, id 5
    const eligibleForQ5 = filterEligibleParents(questions, { id: 5, target: 'team', order: 5 });
    assert.deepEqual(eligibleForQ5.map(q => q.id), [1, 4]);

    // Current question: target 'team', order 1
    const eligibleForQ1 = filterEligibleParents(questions, { id: 1, target: 'team', order: 1 });
    assert.deepEqual(eligibleForQ1, []);

    // New question with target 'institution' at order 10
    const eligibleForNewInst = filterEligibleParents(questions, { id: null, target: 'institution', order: 10 });
    assert.deepEqual(eligibleForNewInst.map(q => q.id), [2]);
});

test('getParentOptions returns options array for the selected parent question ID', () => {
    const questions = [
        { id: 10, target: 'team', type: 'select', options: ['Sedia Tampil', 'Tidak Sedia'] },
        { id: 20, target: 'team', type: 'text', options: [] }
    ];

    assert.deepEqual(getParentOptions(questions, 10), ['Sedia Tampil', 'Tidak Sedia']);
    assert.deepEqual(getParentOptions(questions, '10'), ['Sedia Tampil', 'Tidak Sedia']);
    assert.deepEqual(getParentOptions(questions, 20), []);
    assert.deepEqual(getParentOptions(questions, 99), []);
    assert.deepEqual(getParentOptions(questions, null), []);
});

test('getContentCreatorSchoolPreset returns exact required defaults for academic free quota', () => {
    const preset = getContentCreatorSchoolPreset();
    assert.equal(preset.name, 'Content Creator');
    assert.equal(preset.eligibility_scope, 'school');
    assert.equal(preset.fee_mode, 'included_institution');
    assert.equal(preset.included_slots, 1);
    assert.equal(preset.fee, 0);
    assert.equal(preset.allow_extra, false);
    assert.equal(preset.is_open, false); // draft
    assert.equal(preset.min_members, 1);
    assert.equal(preset.max_members, 5);
    assert.equal(preset.capacity, 0);
    assert.ok(preset.description.length > 0);
    assert.ok(preset.requirements.length > 0);
});

test('getOpeningWillingnessPreset returns team select question for school opening ceremony', () => {
    const preset = getOpeningWillingnessPreset(3);
    assert.equal(preset.target, 'team');
    assert.equal(preset.type, 'select');
    assert.ok(preset.label.toLowerCase().includes('opening'));
    assert.deepEqual(preset.options, ['Ya, bersedia', 'Tidak bersedia']);
    assert.equal(preset.is_required, true);
    assert.equal(preset.order, 4);
    assert.deepEqual(preset.institution_types, ['school']);
});

test('getOpeningArtDetailPreset links to parent and sets conditional trigger value', () => {
    const preset = getOpeningArtDetailPreset(12, 'Ya, bersedia', 4);
    assert.equal(preset.target, 'team');
    assert.equal(preset.type, 'text');
    assert.equal(preset.depends_on_question_id, 12);
    assert.equal(preset.conditional_value, 'Ya, bersedia');
    assert.equal(preset.order, 5);
    assert.deepEqual(preset.institution_types, ['school']);
});

test('resolveScopedOldInput only uses old flashed input if formKey matches submitted form', () => {
    const flashed = { _form_key: 'new', name: 'Draft Activity', fee: '50000' };

    // When form matches submitted form
    assert.equal(resolveScopedOldInput(flashed, 'new', 'name', 'Original'), 'Draft Activity');
    assert.equal(resolveScopedOldInput(flashed, 'new', 'fee', 0), '50000');

    // When form does NOT match submitted form (e.g. edit form for existing activity)
    assert.equal(resolveScopedOldInput(flashed, 'activity-4', 'name', 'Original'), 'Original');
    assert.equal(resolveScopedOldInput(flashed, 'activity-4', 'fee', 0), 0);
});

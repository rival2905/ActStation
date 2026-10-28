import test from 'node:test';
import assert from 'node:assert/strict';
import { shouldShowQuestion, validateQuestionFile } from '../../public/assets/js/event-questions.js';

test('shouldShowQuestion returns true when question has no dependency', () => {
    assert.equal(shouldShowQuestion(null, null, {}), true);
    assert.equal(shouldShowQuestion(undefined, undefined, {}), true);
});

test('shouldShowQuestion returns true only when dependent answer matches conditional_value', () => {
    const answers = { 101: 'Yes' };
    assert.equal(shouldShowQuestion(101, 'Yes', answers), true);
    assert.equal(shouldShowQuestion(101, 'yes', answers), false);
    assert.equal(shouldShowQuestion(101, 'No', answers), false);
    assert.equal(shouldShowQuestion(999, 'Yes', answers), false);
});

test('validateQuestionFile validates type and 2MB limit', () => {
    assert.equal(validateQuestionFile({ type: 'application/pdf', size: 1024 * 1024 }).valid, true);
    assert.equal(validateQuestionFile({ type: 'image/png', size: 2 * 1024 * 1024 }).valid, true);
    assert.equal(validateQuestionFile({ type: 'image/gif', size: 1024 }).valid, false);
    assert.equal(validateQuestionFile({ type: 'application/pdf', size: 3 * 1024 * 1024 }).valid, false);
});

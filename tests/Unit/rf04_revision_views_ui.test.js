import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';

test('roster blade enforces RF04 privacy, plain status names, and authorized revision link', () => {
    const content = fs.readFileSync('resources/views/panel/registrations/roster.blade.php', 'utf8');

    // Plain status names: no "dikurasi"
    assert.doesNotMatch(content, /dikurasi/, 'Should not use term dikurasi');
    assert.match(content, /diperiksa/, 'Should use plain status terminology');

    // Waiver IP privacy: only manager sees waiver IP
    assert.match(content, /@if\(\$isManager && \$participant->waiver_ip\)/);

    // WA Contact privacy: only manager sees contact phone
    assert.match(content, /@if\(\$isManager\)\s*<tr>\s*<td class="text-muted">Kontak WA:<\/td>/);

    // Other athlete email privacy: only visible to manager or self
    assert.match(content, /@if\(\$canViewDetail\)\s*<small class="text-muted">\{\{ \$member->user\?->email \?\? '-' \}\}<\/small>/);

    // Other athlete reasons privacy: only visible to manager or self
    assert.match(content, /@if\(\$canViewDetail && !empty\(\$userSummary\['reasons'\]\)\)/);

    // Revision button guarded by canRevise
    assert.match(content, /@if\(\$canRevise\)\s*<div class="ms-3">\s*<a href="\{\{ route\('panel\.community-events\.revise', \$participant\) \}\}"/);
});

test('revise blade contains add/remove candidates, companion enable/disable, captain hidden0, and scoped uploads', () => {
    const content = fs.readFileSync('resources/views/panel/registrations/revise.blade.php', 'utf8');

    // Error summary
    assert.match(content, /@if\(\$errors->any\(\)\)/);

    // Captain hidden 0 guard
    assert.match(content, /<input type="hidden" name="members\[\{\{ \$idx \}\}\]\[is_captain\]" value="0"/);
    assert.match(content, /<input type="checkbox" class="[^"]*captain-check[^"]*" name="members\[\{\{ \$idx \}\}\]\[is_captain\]" value="1"/);

    // Companion checkbox with enable/disable disabled attribute
    assert.match(content, /class="form-check-input athlete-check"/);
    assert.match(content, /\{\{ \$isSelected \? '' : 'disabled' \}\}/);

    // Scoped team and athlete upload inputs
    assert.match(content, /name="team_documents\[\{\{ \$code \}\}\]"/);
    assert.match(content, /name="athlete_documents\[\{\{ \$uid \}\}\]\[\{\{ \$acode \}\}\]"/);

    // File reselect hint
    assert.match(content, /Pilih ulang berkas jika ingin mengganti/);

    // JavaScript companion toggle
    assert.match(content, /chk\.addEventListener\('change'/);
    assert.match(content, /inputs\.forEach\(input => input\.removeAttribute\('disabled'\)\)/);
    assert.match(content, /inputs\.forEach\(input => input\.setAttribute\('disabled', 'disabled'\)\)/);
});

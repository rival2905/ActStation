import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';

const sidebar = fs.readFileSync('resources/views/layouts/partials/panel-sidebar.blade.php', 'utf8');
test('athlete navigation exposes own community and event workspaces outside manager branch', () => {
    const endManager = sidebar.indexOf('<!-- Daftar Turnamen -->');
    const athlete = sidebar.indexOf('<!-- Permintaan Saya -->');
    assert.ok(athlete > endManager);
    const personal = sidebar.slice(athlete);
    assert.match(personal, /Komunitas Saya/);
    assert.match(personal, /panel\.my-communities\.index/);
    assert.match(personal, /Turnamen Saya/);
    assert.match(personal, /my-events\.index/);
});

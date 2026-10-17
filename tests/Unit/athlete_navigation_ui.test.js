import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';

const sidebar = fs.readFileSync('resources/views/layouts/partials/panel-sidebar.blade.php', 'utf8');
test('panel navigation prioritizes registration and separates personal from managed context', () => {
    assert.match(sidebar, /aria-label="Menu panel"/);
    assert.ok(sidebar.indexOf('<!-- Daftar Turnamen -->') < sidebar.indexOf('<!-- Profil Komunitas -->'));
    assert.match(sidebar, /Panel aktif/);
    assert.match(sidebar, /Akun Pribadi/);
    assert.match(sidebar, /Dokumen Legalitas/);
    assert.match(sidebar, /request\(\)->routeIs\('panel.organizer.events.index'/);
});
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

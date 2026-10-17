import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';

const view = fs.readFileSync('resources/views/panel/events/register.blade.php', 'utf8');

test('registration uses three Falcon progress tabs without incompatible theme auto-init', () => {
    assert.match(view, /nav-wizard/);
    assert.match(view, /data-registration-progress/);
    assert.match(view, /data-registration-step-prev/);
    assert.match(view, /data-registration-step-next/);
    assert.doesNotMatch(view, /class="[^"]*theme-wizard/);
    assert.doesNotMatch(view, /data-registration-step-pane="category team"/);
    assert.match(view, /<form id="registration-form"[\s\S]*?novalidate/);
});

test('step 2 splits into explicit Dokumen and Roster sections with invitation controls in roster', () => {
    const roster = view.indexOf('data-registration-step-pane="roster"');
    const review = view.indexOf('data-registration-step-pane="review"');
    assert.ok(roster >= 0 && review > roster);
    const rosterView = view.substring(roster, review);

    const docHeading = rosterView.search(/<h6\b[^>]*>\s*Dokumen Tim & Legalitas\s*<\/h6>/i);
    const rosterHeading = rosterView.search(/<h6\b[^>]*>\s*Roster Atlet\s*<\/h6>/i);
    const teamDocs = rosterView.indexOf('id="team_documents_wrapper"');
    const entityDocs = rosterView.indexOf('id="entity_documents_container"');
    const inviteLink = rosterView.indexOf('data-registration-athlete-invite-link');

    assert.ok(docHeading !== -1, 'explicit h6 Dokumen heading must exist in step 2');
    assert.ok(rosterHeading !== -1, 'explicit h6 Roster heading must exist in step 2');
    assert.ok(docHeading < teamDocs, 'team documents must be under Dokumen section');
    assert.ok(teamDocs < rosterHeading, 'team documents must precede Roster section');
    assert.ok(entityDocs < rosterHeading, 'entity documents must precede Roster section');
    assert.ok(rosterHeading < inviteLink, 'invitation link must be in Roster section, not Dokumen');

    assert.equal(rosterView.indexOf('athlete_invitation_name'), -1, 'athlete_invitation_name input must be removed');
    assert.match(rosterView, /<button\b[^>]*data-registration-athlete-invite-link[^>]*>[\s\S]*?Link Roster Atlet[\s\S]*?<\/button>/);
    assert.match(rosterView, /<input\b[^>]*\bid=["']registration-team-invitation-url["'][^>]*\breadonly\b[^>]*\bhidden\b/);
    assert.match(rosterView, /<button\b[^>]*data-registration-copy-invitation\b[^>]*\bhidden\b/);
    assert.match(rosterView, /id=["']registration-invitation-message["']/);

    assert.match(rosterView, /<button\b[^>]*data-registration-add-athlete\b[^>]*\baria-expanded="false"/);
    assert.match(rosterView, /<div\b[^>]*data-registration-athlete-picker\b[^>]*\bhidden\b/);
});

test('active team and category remain explicit with guarded multi-team actions', () => {
    assert.match(view, /data-registration-active-team/);
    assert.match(view, /data-registration-active-category/);
    assert.match(view, /data-registration-team-switch/);
    assert.match(view, /'new_team' => 1/);
    assert.match(view, /Tim lain tetap draft/);
    assert.match(view, /data-min-roster/);
    assert.match(view, /data-max-roster/);
});

test('step1 renders add team action and accordion with active editable item and collapsed draft items', () => {
    const categoryPane = view.indexOf('id="registration-pane-category"');
    const rosterPane = view.indexOf('id="registration-pane-roster"');
    assert.ok(categoryPane >= 0 && rosterPane > categoryPane);
    const categoryView = view.substring(categoryPane, rosterPane);

    // Accordion container in step 1
    assert.match(categoryView, /id="registration-teams-accordion"/);
    assert.match(categoryView, /class="[^"]*accordion/);

    // Add team action in step 1
    assert.match(categoryView, /data-registration-team-switch[^>]*href="[^"]*new_team/);
    assert.match(categoryView, /data-registration-go-roster/);

    // Active item header has team name, category, draft status, and is open
    assert.match(categoryView, /class="accordion-button"[^>]*data-bs-toggle="collapse"[^>]*data-bs-target="#collapse-active-team"/);
    assert.match(categoryView, /Draft Aktif/);
    assert.match(categoryView, /id="collapse-active-team"[^>]*class="[^"]*accordion-collapse\s+collapse\s+show[^"]*"[^>]*data-bs-parent="#registration-teams-accordion"/);

    // Inactive draft items: collapsed, header button has toggle collapse and lazy draft data
    assert.match(categoryView, /<button[^>]*class="[^"]*accordion-button\s+collapsed[^"]*"[^>]*data-bs-toggle="collapse"[^>]*data-bs-target="#collapse-draft-/);
    assert.doesNotMatch(categoryView, /<button[^>]*class="[^"]*accordion-button[^"]*"[^>]*data-registration-team-switch/);
    assert.match(categoryView, /class="[^"]*accordion-collapse\s+collapse(?!\s+show)[^"]*"[\s\S]*?data-bs-parent="#registration-teams-accordion"/);
    assert.match(categoryView, /data-registration-lazy-draft=/);

    // Active form identity fields inside active item, single form, no duplicate IDs
    const activeCollapse = categoryView.indexOf('id="collapse-active-team"');
    assert.ok(categoryView.indexOf('id="event_category_id"') > activeCollapse);
    assert.ok(categoryView.indexOf('id="team_name"') > activeCollapse);
    assert.ok(categoryView.indexOf('id="contact_person"') > activeCollapse);
    assert.ok(categoryView.indexOf('id="contact_phone"') > activeCollapse);

    assert.equal((view.match(/id="team_name"/g) || []).length, 1);
    assert.equal((view.match(/id="event_category_id"/g) || []).length, 1);
    assert.equal((view.match(/id="contact_person"/g) || []).length, 1);
    assert.equal((view.match(/id="contact_phone"/g) || []).length, 1);

    // Strict string comparison for active draft ID
    assert.match(view, /\(string\)\s*\$draft->id\s*===\s*\$activeDraftId/);

    // Overview tables remain native details
    assert.match(categoryView, /<details class="mb-3">/);
});

test('registration form has data-autosave="false" and removes automatic saving copy', () => {
    assert.match(
        view,
        /<form\b[^>]*\bid=["']registration-form["'][^>]*data-autosave=["']false["']/s,
        'registration form must declare data-autosave="false"'
    );
    assert.doesNotMatch(
        view,
        /tersimpan\s+otomatis/i,
        'blade template must not claim changes are saved automatically'
    );
});

test('cancel new team stays beside draft status outside all collapsible panes', () => {
    const status = view.indexOf('id="draft-status-text"');
    const cancel = view.indexOf('id="btn-cancel-new-team"');
    const categoryPane = view.indexOf('id="registration-pane-category"');
    assert.ok(status < cancel && cancel < categoryPane);
    assert.equal((view.match(/id="btn-cancel-new-team"/g) || []).length, 1);
});

test('draft status indicator is visible beyond step 1 in header area', () => {
    const categoryPaneIndex = view.indexOf('id="registration-pane-category"');
    const statusIndicatorIndex = view.indexOf('id="draft-status-indicator"');
    assert.ok(statusIndicatorIndex !== -1, 'draft-status-indicator must exist');
    assert.ok(categoryPaneIndex !== -1, 'registration-pane-category must exist');
    assert.ok(
        statusIndicatorIndex < categoryPaneIndex,
        'draft-status-indicator must be outside/before step 1 pane so it remains visible on all steps'
    );
});

test('active accordion in step 1 has manual save button with conditional label and draft helper text', () => {
    const categoryPane = view.indexOf('id="registration-pane-category"');
    const rosterPane = view.indexOf('id="registration-pane-roster"');
    const categoryView = view.substring(categoryPane, rosterPane);
    const activeCollapse = categoryView.indexOf('id="collapse-active-team"');
    const inactiveDraftSection = categoryView.indexOf('@foreach($registrationDrafts as $draft)', activeCollapse);

    const activeCollapseView = inactiveDraftSection > -1
        ? categoryView.substring(activeCollapse, inactiveDraftSection)
        : categoryView.substring(activeCollapse);

    assert.match(
        activeCollapseView,
        /<button\b[^>]*type=["']button["'][^>]*data-registration-save-team/s,
        'active accordion must have button type="button" data-registration-save-team'
    );
    assert.match(
        activeCollapseView,
        /data-registration-save-team[\s\S]*?\{\{\s*\$activeDraft\s*\?\s*['"]Simpan & Lengkapi Tim['"]\s*:\s*['"]Simpan & Daftarkan Tim['"]\s*\}\}/,
        'button label must be "Simpan & Lengkapi Tim" if $activeDraft exists else "Simpan & Daftarkan Tim"'
    );
    assert.match(
        activeCollapseView,
        /data-registration-save-team[\s\S]*?draft/i,
        'helper text explaining save draft must accompany save button'
    );

    // Inactive collapsed drafts must NOT contain the save button
    if (inactiveDraftSection > -1) {
        const inactiveDraftView = categoryView.substring(inactiveDraftSection);
        assert.doesNotMatch(
            inactiveDraftView,
            /data-registration-save-team/,
            'inactive drafts must not contain data-registration-save-team button'
        );
    }
});

test('step 2 roster pane has second accordion registration-roster-teams-accordion with active item, dynamic header, and step=roster resume link', () => {
    const rosterPane = view.indexOf('id="registration-pane-roster"');
    const reviewPane = view.indexOf('id="registration-pane-review"');
    assert.ok(rosterPane >= 0 && reviewPane > rosterPane);
    const rosterView = view.substring(rosterPane, reviewPane);

    assert.match(rosterView, /id=["']registration-roster-teams-accordion["']/);
    assert.match(rosterView, /id=["']collapse-roster-active-team["']/);

    // Active item header in roster accordion has dynamic team name and category
    const activeRosterHeader = rosterView.substring(
        rosterView.indexOf('id="heading-roster-active-team"'),
        rosterView.indexOf('id="collapse-roster-active-team"')
    );
    assert.match(activeRosterHeader, /data-registration-active-team/);
    assert.match(activeRosterHeader, /data-registration-active-category/);

    // Inactive draft headers in roster accordion have collapse toggle without auto-switch, and have lazy draft trigger
    assert.match(rosterView, /<button[^>]*class="[^"]*accordion-button\s+collapsed[^"]*"[^>]*data-bs-toggle="collapse"[^>]*data-bs-target="#collapse-roster-draft-/);
    assert.doesNotMatch(rosterView, /<button[^>]*class="[^"]*accordion-button[^"]*"[^>]*data-registration-team-switch/);
    assert.match(rosterView, /data-registration-lazy-draft=/);

    // Exactly one active payload in roster pane (no duplicate roster inputs or team doc wrappers)
    assert.equal((rosterView.match(/id="team_documents_wrapper"/g) || []).length, 1);
    assert.equal((rosterView.match(/id="entity_documents_container"/g) || []).length, 1);
    assert.equal((rosterView.match(/id="no-category-selected-message"/g) || []).length, 1);
});

test('roster pane explains automatic draft saving for roster and documents without manual save button', () => {
    const rosterPane = view.indexOf('id="registration-pane-roster"');
    const reviewPane = view.indexOf('id="registration-pane-review"');
    const rosterView = view.substring(rosterPane, reviewPane);

    assert.doesNotMatch(
        rosterView,
        /data-registration-save-draft/,
        'roster pane must not have button data-registration-save-draft'
    );
    assert.doesNotMatch(
        rosterView,
        /Simpan Roster & Dokumen/,
        'roster pane must not have "Simpan Roster & Dokumen"'
    );
    assert.match(
        rosterView,
        /Perubahan roster dan dokumen disimpan otomatis\./,
        'roster pane must display status text "Perubahan roster dan dokumen disimpan otomatis."'
    );
});

test('each category roster section unconditionally renders table and tbody even when athletes collection is empty', () => {
    const rosterPane = view.indexOf('id="registration-pane-roster"');
    const reviewPane = view.indexOf('id="registration-pane-review"');
    const rosterView = view.substring(rosterPane, reviewPane);

    assert.doesNotMatch(
        rosterView,
        /@if\s*\(\s*\$athletes->isEmpty\(\)\s*\)[\s\S]*?<div[^>]*>[\s\S]*?@else[\s\S]*?<tbody>/s,
        'tbody must not be conditioned inside @else of $athletes->isEmpty()'
    );
});

test('step 3 review pane has accordion registration-review-teams-accordion with lazy draft triggers', () => {
    const reviewPane = view.indexOf('id="registration-pane-review"');
    const reviewView = view.substring(reviewPane);

    assert.match(reviewView, /id="registration-review-teams-accordion"/);
    assert.match(reviewView, /id="review-accordion-item-active"/);
    assert.match(reviewView, /id="collapse-review-active-team"/);
    assert.match(reviewView, /id="review-form-body-content"/);
    assert.match(reviewView, /data-registration-lazy-draft=/);
    assert.match(reviewView, /Daftarkan Tim Ini/);
});

test('register blade prioritizes first existing draft when arriving without draft_id or new_team', () => {
    assert.match(
        view,
        /elseif\s*\(\s*!\s*\$isNewTeam\s*&&\s*\$registrationDrafts->isNotEmpty\(\)\s*\)\s*\{\s*\$activeDraft\s*=\s*\$registrationDrafts->first\(\);/s,
        'blade must select first saved draft when new_team is false and drafts exist'
    );
});


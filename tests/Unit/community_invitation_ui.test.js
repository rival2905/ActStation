import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';

const invite = fs.readFileSync('resources/views/public/invite.blade.php', 'utf8');
const athletes = fs.readFileSync('resources/views/panel/athletes/index.blade.php', 'utf8');

test('invitation view has separate authenticated membership join branch', () => {
    const authStart = invite.indexOf("@elseif(auth('web')->check())");
    const guestStart = invite.indexOf('</form>', authStart);
    assert.notEqual(authStart, -1, 'authenticated branch missing');
    assert.notEqual(guestStart, -1, 'guest branch missing');

    const authBranch = invite.slice(authStart, guestStart);
    assert.match(authBranch, /route\('invitations\.public\.join', \$token\)/);
    assert.match(authBranch, /@csrf/);
    assert.match(authBranch, /name="consent" value="1" required/);
    assert.doesNotMatch(authBranch, /name="(?:name|email|password)/);
});

test('guest registration requires membership consent and offers existing-account login', () => {
    assert.match(invite, /route\('invitations\.public\.login', \$token\)/);
    assert.match(invite, /name="consent" value="1" required/);
    assert.doesNotMatch(invite, /Anda diundang untuk bergabung ke dalam roster tim dan komunitas/);
    assert.match(invite, /keanggotaan komunitas/);
    assert.match(invite, /tim kompetisi.*terpisah/i);
});

test('context invitation explains automatic draft enrollment and keeps consent explicit', () => {
    assert.ok(invite.includes('$invitation->registration_draft_id'), 'context-specific branch missing');
    assert.ok(invite.includes('otomatis masuk roster draft tim'), 'provisional enrollment explanation missing');
    assert.ok(invite.includes('bukan roster resmi'), 'official roster boundary missing');
});

test('regional verification remains informational rather than a registration prerequisite', () => {
    const registration = fs.readFileSync('resources/views/panel/events/register.blade.php', 'utf8');
    assert.doesNotMatch(invite, /menunggu persetujuan pengurus dan verifikasi wilayah|Pemeriksaan dokumen dan verifikasi wilayah tetap berlaku/);
    assert.match(invite, /Verifikasi wilayah tidak menjadi syarat pendaftaran/);
    assert.doesNotMatch(registration, /anggota komunitas aktif terverifikasi/);
});

test('athlete page names community or school extracurricular membership', () => {
    assert.match(athletes, /\(\$community->subtype \?\? null\) === 'school'/);
    assert.doesNotMatch(athletes, /school_sd.*school_smp.*school_sma/);
    assert.match(athletes, /Anggota Ekskul/);
    assert.match(athletes, /Anggota Komunitas/);
    assert.match(athletes, /tim kompetisi.*terpisah/i);
});

test('invitation fields expose validation errors to assistive technology', () => {
    assert.match(invite, /subtype \?\? null\) === 'school' \? 'Sekolah'/);
    assert.match(invite, /maxlength="255"/g);
    assert.match(invite, /maxlength="20"/);
    assert.match(invite, /aria-invalid="\{\{ \$errors->has\('name'\)/);
    assert.match(invite, /aria-describedby="name-error"/);
    assert.match(invite, /id="name-error" role="alert"/);
    assert.match(invite, /aria-describedby="registration_consent-error"/);
    assert.match(invite, /id="registration_consent-error" role="alert"/);
    assert.match(invite, /aria-describedby="join_consent-error"/);
    assert.match(invite, /id="join_consent-error" role="alert"/);
});

test('distinct headings and explanatory target reflect invitation context without inventing names', () => {
    assert.match(invite, /Gabung Roster Kejuaraan/);
    assert.match(invite, /Gabung Anggota Komunitas/);
    assert.match(invite, /\$invitationContext/);
    assert.match(invite, /\$invitationContext\['is_roster'\]/);
});

test('global community hides jersey number while roster context keeps optional jersey', () => {
    const authStart = invite.indexOf("@elseif(auth('web')->check())");
    const guestStart = invite.indexOf('</form>', authStart);
    const authBranch = invite.slice(authStart, guestStart);
    const guestBranch = invite.slice(guestStart);

    assert.match(authBranch, /@if\s*\(\s*(?:\$isRoster|\$invitationContext\['is_roster'\])[^)]*\)[\s\S]*?name="jersey_number"[\s\S]*?@endif/);
    assert.match(guestBranch, /@if\s*\(\s*(?:\$isRoster|\$invitationContext\['is_roster'\])[^)]*\)[\s\S]*?name="jersey_number"[\s\S]*?@endif/);
});

test('both auth join and guest forms require birth_place and birth_date with safe prefill', () => {
    const authStart = invite.indexOf("@elseif(auth('web')->check())");
    const guestStart = invite.indexOf('</form>', authStart);
    const authBranch = invite.slice(authStart, guestStart);
    const guestBranch = invite.slice(guestStart);

    // Auth branch has birth_place and birth_date with required and only authenticated user prefill
    assert.match(authBranch, /name="birth_place"[^>]*required/);
    assert.match(authBranch, /name="birth_date"[^>]*required/);
    assert.match(authBranch, /auth\('web'\)->user\(\)\?->birth_place/);
    assert.match(authBranch, /auth\('web'\)->user\(\)\?->birth_date/);

    // Guest branch has birth_place and birth_date with required and no invitation PII prefill
    assert.match(guestBranch, /name="birth_place"[^>]*required/);
    assert.match(guestBranch, /name="birth_date"[^>]*required/);
    assert.doesNotMatch(guestBranch, /old\('birth_place',\s*\$invitation/);
    assert.doesNotMatch(guestBranch, /old\('birth_date',\s*\$invitation/);
});

test('academic or roster context renders required identity field with server authoritative identity_type', () => {
    assert.match(invite, /identity_field/);
    assert.match(invite, /identity_label/);
    assert.match(invite, /identity_number/);

    const authStart = invite.indexOf("@elseif(auth('web')->check())");
    const guestStart = invite.indexOf('</form>', authStart);
    const authBranch = invite.slice(authStart, guestStart);
    const guestBranch = invite.slice(guestStart);

    // Identity field visibility is bound to identity_type !== null, not is_roster
    assert.match(authBranch, /@if\s*\(\s*(?:\(\$invitationContext\['identity_type'\]\s*\?\?\s*null\)\s*!==\s*null|\$invitationContext\['identity_type'\]\s*!==\s*null)[\s\S]*?name="\{\{\s*\$invitationContext\['identity_field'\]/);
    assert.match(guestBranch, /@if\s*\(\s*(?:\(\$invitationContext\['identity_type'\]\s*\?\?\s*null\)\s*!==\s*null|\$invitationContext\['identity_type'\]\s*!==\s*null)[\s\S]*?name="\{\{\s*\$invitationContext\['identity_field'\]/);
    assert.doesNotMatch(guestBranch, /old\([^,]+,\s*\$invitation->identity/);

    // Jersey number strictly remains bound to is_roster
    assert.match(authBranch, /@if\s*\(\s*(?:\$isRoster|\$invitationContext\['is_roster'\])[^)]*\)[\s\S]*?name="jersey_number"/);
    assert.match(guestBranch, /@if\s*\(\s*(?:\$isRoster|\$invitationContext\['is_roster'\])[^)]*\)[\s\S]*?name="jersey_number"/);
});

test('identity number input has inputmode, pattern, minlength, maxlength and educational history helper', () => {
    assert.match(invite, /inputmode=/);
    assert.match(invite, /pattern=/);
    assert.match(invite, /maxlength=/);
    assert.match(invite, /aria-describedby="[^"]*identity_number-help/);
    assert.match(invite, /id="identity_number-help"/);
    assert.match(invite, /NISN\/NIM dicatat pada histori pendidikan akun;\s*NIK tetap identitas pribadi terpisah\./);
    assert.match(invite, /NISN harus 10 digit angka/);
    assert.match(invite, /NIK harus 16 digit angka/);
    assert.match(invite, /nol di/i);
});

test('validatedAthleteDetails enforces Indonesian messages and excludes identity only when identity_type is null', () => {
    const serviceContent = fs.readFileSync('app/Modules/Community/Services/InvitationService.php', 'utf8');
    const methodStart = serviceContent.indexOf('function validatedAthleteDetails');
    assert.notEqual(methodStart, -1);
    const methodBody = serviceContent.slice(methodStart, serviceContent.indexOf('function saveAthleteIdentity'));

    // Exclude identity only when identity_type is null, not !is_roster
    assert.match(methodBody, /unset\(\$data\['identity_number'\]\)/);
    assert.match(methodBody, /identity_type.*null/);
    assert.doesNotMatch(methodBody, /if\s*\(\s*!\s*\$context\['is_roster'\]\s*\)\s*\{\s*unset\(\$data\['jersey_number'\],\s*\$data\['identity_number'\]\);/);

    // Specific Indonesian messages
    assert.match(methodBody, /NISN harus 10 digit angka/);
    assert.match(methodBody, /NIK harus 16 digit angka/);
    assert.match(methodBody, /NIM/);

    // Field labels validator attributes
    assert.match(methodBody, /'identity_number'\s*=>/);
    assert.match(methodBody, /'birth_place'\s*=>\s*'Tempat Lahir'/);
    assert.match(methodBody, /'birth_date'\s*=>\s*'Tanggal Lahir'/);
});

test('accessibility attributes exist for new athlete fields', () => {
    assert.match(invite, /aria-describedby="[^"]*birth_place-error"/);
    assert.match(invite, /aria-describedby="[^"]*birth_date-error"/);
    assert.match(invite, /id="[^"]*birth_place-error" role="alert"/);
    assert.match(invite, /id="[^"]*birth_date-error" role="alert"/);
});

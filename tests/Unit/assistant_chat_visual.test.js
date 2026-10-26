import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';

const bladePath = 'resources/views/layouts/partials/assistant-chat.blade.php';
const cssPath = 'public/assets/css/assistant-chat.css';

test('Blade partial implements floating chat window, header, details notice, and accessible composer', () => {
    assert.ok(fs.existsSync(bladePath), 'Blade file exists');
    const blade = fs.readFileSync(bladePath, 'utf8');

    // Auth & Permission guards
    assert.match(blade, /@auth/, 'must have @auth guard');
    assert.match(blade, /@can\(\s*['"]assistant\.use['"]\s*\)/, 'must have @can(assistant.use) guard');
    assert.doesNotMatch(blade, /config\(/i, 'no config() hiding');
    assert.doesNotMatch(blade, /env\(/i, 'no env() hiding');

    // Offcanvas configuration
    assert.match(blade, /class="[^"]*offcanvas offcanvas-end[^"]*"/, 'must have offcanvas offcanvas-end');
    assert.match(blade, /data-bs-backdrop=["']false["']/, 'must configure desktop data-bs-backdrop=false');
    assert.match(blade, /data-bs-scroll=["']true["']/, 'must configure data-bs-scroll=true');

    // Header elements: brand avatar AS, title, subtitle Asisten AI, no fake online, header Hapus riwayat
    assert.match(blade, /class="[^"]*offcanvas-header[^"]*"/, 'must have offcanvas header');
    const headerHtml = blade.slice(blade.indexOf('offcanvas-header'), blade.indexOf('offcanvas-body'));
    assert.match(headerHtml, />\s*AS\s*</, 'header must include brand avatar initials AS');
    assert.match(headerHtml, /Asisten ActStation/, 'header must include title Asisten ActStation');
    assert.match(headerHtml, /Asisten AI/, 'header must include subtitle Asisten AI');
    assert.doesNotMatch(headerHtml, /online/i, 'header must not claim fake online');
    assert.match(headerHtml, /data-assistant-clear/, 'header must include clear button data-assistant-clear');
    assert.match(headerHtml, /data-bs-dismiss=["']offcanvas["']/, 'header must include close button');

    // Notice: collapsible native details
    assert.match(blade, /<details\b/, 'must use collapsible native <details>');
    assert.match(blade, /<summary[^>]*>[\s\S]*?Tentang asisten AI[\s\S]*?<\/summary>/, 'summary must be Tentang asisten AI');
    assert.match(blade, /Jawaban AI dapat keliru\. Jangan kirim data pribadi atau dokumen\. Pesan dikirim ke penyedia AI\./, 'details must contain exact disclaimer text');

    // Composer elements
    assert.match(blade, /<label[^>]*for=["']assistant-chat-input["'][^>]*class="[^"]*visually-hidden[^"]*"|<label[^>]*class="[^"]*visually-hidden[^"]*"[^>]*for=["']assistant-chat-input["']/, 'accessible visually-hidden label');
    assert.match(blade, /<textarea[^>]*id=["']assistant-chat-input["'][^>]*rows=["']1["'][^>]*>/, 'textarea must have id assistant-chat-input and rows="1"');
    assert.match(blade, /<textarea[^>]*\brequired\b[^>]*>/, 'textarea must be required');
    assert.match(blade, /<textarea[^>]*\bmaxlength=["']1000["'][^>]*>/, 'textarea must have maxlength 1000');
    assert.match(blade, /<button[^>]*type=["']submit["'][^>]*>[\s\S]*?(?:Kirim|<svg)/, 'composer must have submit button');

    // Cache busting
    assert.match(blade, /asset\(\s*['"]assets\/css\/assistant-chat\.css['"]\s*\)\s*\.\s*['"]\?v=['"]\s*\.\s*filemtime\(\s*public_path\(\s*['"]assets\/css\/assistant-chat\.css['"]\s*\)\s*\)/, 'cache-bust CSS via filemtime');
    assert.match(blade, /asset\(\s*['"]assets\/js\/assistant-chat\.js['"]\s*\)\s*\.\s*['"]\?v=['"]\s*\.\s*filemtime\(\s*public_path\(\s*['"]assets\/js\/assistant-chat\.js['"]\s*\)\s*\)/, 'cache-bust JS via filemtime');
});

test('CSS implements floating window desktop, mobile 100dvh, human chat bubbles, ::before AI avatar, and >=44px touch targets', () => {
    assert.ok(fs.existsSync(cssPath), 'CSS file exists');
    const css = fs.readFileSync(cssPath, 'utf8');

    // Floating window desktop dimensions
    assert.match(css, /min\(\s*420px\s*,\s*100vw\s*\)/, 'width min(420px, 100vw)');
    assert.match(css, /min\(\s*680px\s*,\s*calc\(\s*100dvh\s*-\s*7rem\s*\)\s*\)/, 'desktop height min(680px, calc(100dvh - 7rem))');

    // Mobile <=575px full screen
    assert.match(css, /575(?:\.98)?px/, 'media query for <=575px');
    assert.match(css, /100dvh/, 'mobile 100dvh full screen');

    // Safe area support
    assert.match(css, /safe-area-inset-bottom/, 'supports safe-area-inset-bottom');

    // Bubble styling & tails
    assert.match(css, /\.assistant-chat-msg-user[\s\S]*?\.assistant-chat-bubble/, 'user bubble styling exists');
    assert.match(css, /\.assistant-chat-msg-assistant[\s\S]*?\.assistant-chat-bubble/, 'assistant bubble styling exists');
    assert.match(css, /border-radius:\s*[^;]*?(?:1\.125rem|1rem|18px|16px)/, 'bubbles have rounded chat corners/tails');

    // AI avatar via ::before (decorative, screen reader hidden)
    assert.match(css, /\.assistant-chat-msg-assistant::before/, 'assistant AI avatar rendered via ::before');

    // Accessible touch targets >= 44px
    assert.match(css, /44px/, 'touch targets enforced with 44px minimum');

    // History & 320px mobile overflow prevention
    assert.match(css, /\.assistant-chat-history-a/, 'bounded preview for history answers exists');
    assert.match(css, /overflow/, 'overflow handling present in CSS');
});

test('Blade partial implements history toggle, data-archive-url, compose form, and distinct search UI', () => {
    assert.ok(fs.existsSync(bladePath), 'Blade file exists');
    const blade = fs.readFileSync(bladePath, 'utf8');

    assert.match(blade, /data-archive-url=/, 'must bind data-archive-url');
    assert.match(blade, /route\(\s*['"]assistant\.archive['"]\s*\)/, 'must use route(assistant.archive)');
    assert.match(blade, /data-assistant-compose/, 'must have explicit data-assistant-compose attribute on form');
    assert.match(blade, /data-assistant-history-toggle/, 'must have history toggle button in header');
    assert.match(blade, /data-assistant-search-form/, 'must have distinct search form');
    assert.match(blade, /data-assistant-history-list/, 'must have history list container');
    assert.match(blade, /data-assistant-history-load-more/, 'must have load more button');
});

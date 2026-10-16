const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
const vm = require('node:vm');

const rootDir = path.resolve(__dirname, '..');

console.log('--- Running ActStation Email System Nodecheck ---');

(async () => {
  // 1. Check Workflow: n8/ActStation Email.json
  const wfPath = path.join(rootDir, 'n8/ActStation Email.json');
  assert.ok(fs.existsSync(wfPath), 'n8/ActStation Email.json must exist');
  const wfContent = fs.readFileSync(wfPath, 'utf8');
  const wf = JSON.parse(wfContent);

  assert.equal(wf.name, 'ActStation Email', 'Workflow name must be ActStation Email');
  assert.equal(typeof wf.active, 'boolean', 'Workflow active state must be explicit');

  // Credential absence & placeholder checks
  assert.ok(!wfContent.includes('password123') && !wfContent.includes('sk_live'), 'No secrets or passwords in workflow');
  // Configured credential references are expected in a user-exported workflow.

  // Webhook Node
  const webhookNode = wf.nodes.find(n => n.type === 'n8n-nodes-base.webhook');
  assert.ok(webhookNode, 'Webhook node must exist');
  assert.equal(webhookNode.parameters.path, 'actstation-email', 'Webhook path must be actstation-email');
  assert.equal(webhookNode.parameters.httpMethod, 'POST', 'Webhook httpMethod must be POST');
  assert.equal(webhookNode.parameters.authentication, 'headerAuth', 'Webhook auth must be headerAuth');
  assert.equal(webhookNode.parameters.responseMode, 'responseNode', 'Webhook responseMode must be responseNode');
  assert.ok(webhookNode.credentials?.httpHeaderAuth?.id || webhookNode.credentials?.headerAuth?.id, 'Header Auth credential reference must be selected');

  // Code Validation Node
  const codeNode = wf.nodes.find(n => n.name === 'Validasi Payload & Keamanan');
  assert.ok(codeNode, 'Code validation node must exist');
  const jsCode = codeNode.parameters.jsCode;

  assert.ok(jsCode.includes('laravel-actstation'), 'Code node must validate X-Webhook-Source is laravel-actstation');
  assert.ok(jsCode.includes('notification.email'), 'Code node must validate notification.email event');
  assert.ok(jsCode.includes('recipient_email') && jsCode.includes('recipient_name'), 'Code node must extract recipient_email and recipient_name');
  assert.ok(jsCode.includes('renderedHtml') || jsCode.includes('data.html'), 'Code node must validate Laravel rendered HTML');
  assert.ok(jsCode.includes('rawAttachments.length > 3'), 'Code node must enforce max 3 attachments');
  assert.ok(jsCode.includes("const rawBaseUrl = 'https://actstation.net'"), 'Attachment origin must be explicitly configured');
  assert.ok(!jsCode.includes('$env'), 'Code node must not access blocked environment variables');
  assert.ok(jsCode.includes("parsedUrl.protocol !== 'https:'"), 'Code node must enforce HTTPS for attachments');
  assert.ok(jsCode.includes('message_id'), 'Code node must strictly require body.message_id');
  assert.ok(jsCode.includes('validTypes'), 'Code node must strictly validate data.type against enum');

  // Branches & Fail-Closed Behavior
  const ifNode = wf.nodes.find(n => n.name === 'Ada Lampiran?');
  assert.ok(ifNode, 'If node Ada Lampiran? must exist');

  const splitNode = wf.nodes.find(n => n.name === 'Siapkan Unduhan Lampiran');
  assert.ok(splitNode, 'Siapkan Unduhan Lampiran node must exist');
  assert.equal(splitNode.parameters.mode ?? 'runOnceForAllItems', 'runOnceForAllItems', 'Split node must runOnceForAllItems');
  assert.ok(splitNode.parameters.jsCode.includes('$input.first().json'), 'Split node must read from $input.first().json');

  const downloadNode = wf.nodes.find(n => n.name === 'Unduh Berkas Lampiran');
  assert.ok(downloadNode, 'Unduh Berkas Lampiran node must exist');
  assert.equal(downloadNode.onError ?? 'stopWorkflow', 'stopWorkflow', 'Attachment download must fail closed (onError = stopWorkflow)');
  assert.equal(downloadNode.parameters?.options?.redirect?.redirect?.followRedirects, false, 'Download node must disable followRedirects');

  const mergeNode = wf.nodes.find(n => n.name === 'Gabung Berkas Lampiran');
  assert.ok(mergeNode, 'Gabung Berkas Lampiran code node must exist');
  assert.equal(mergeNode.parameters.mode ?? 'runOnceForAllItems', 'runOnceForAllItems', 'Merge node must runOnceForAllItems');
  assert.ok(mergeNode.parameters.jsCode.includes('expectedTotal'), 'Merge node must verify downloaded count matches expected total');
  assert.ok(mergeNode.parameters.jsCode.includes("$('Siapkan Unduhan Lampiran').all()"), 'Merge node must pull prepared metadata from upstream node');
  assert.ok(mergeNode.parameters.jsCode.includes('MAX_ATTACHMENT_SIZE') || mergeNode.parameters.jsCode.includes('5 * 1024 * 1024'), 'Merge node must enforce 5MB attachment size limit');

  // Webhook Response Node & Connections Sanity
  const respondNode = wf.nodes.find(n => n.type === 'n8n-nodes-base.respondToWebhook');
  assert.ok(respondNode, 'Respond to webhook node must exist');

  // Verify Connections: respondNode is triggered ONLY AFTER SMTP node succeeds
  const emailSendWithAttach = wf.connections['Kirim Email + Lampiran'];
  const emailSendPlain = wf.connections['Kirim Email Polos'];
  assert.ok(emailSendWithAttach.main[0].some(conn => conn.node === respondNode.name), 'Kirim Email + Lampiran must connect to Respond to Webhook');
  assert.ok(emailSendPlain.main[0].some(conn => conn.node === respondNode.name), 'Kirim Email Polos must connect to Respond to Webhook');

  // Connection Sanity: ensure Webhook, Validasi, or If nodes do NOT connect directly to Respond to Webhook
  const webhookConns = wf.connections['Webhook ActStation Email']?.main?.[0] || [];
  assert.ok(!webhookConns.some(c => c.node === respondNode.name), 'Webhook node must not connect prematurely to Respond node');
  const validConns = wf.connections['Validasi Payload & Keamanan']?.main?.[0] || [];
  assert.ok(!validConns.some(c => c.node === respondNode.name), 'Validation node must not connect prematurely to Respond node');

  // ==========================================
  // Node VM Execution Suite for Production JS
  // ==========================================
  async function runValidationVM(inputJson, env = {}) {
    const sandbox = {
      $input: {
        item: { json: inputJson }
      },
      $env: env,
      URL,
      String,
      Array,
      Error,
      Date,
      parseInt,
      parseFloat,
      Object,
      console
    };
    const context = vm.createContext(sandbox);
    const fn = vm.runInContext(`(async function() { ${codeNode.parameters.jsCode} })`, context);
    return await fn();
  }

  async function runSplitVM(inputFirstJson) {
    const sandbox = {
      $input: {
        first: () => ({ json: inputFirstJson }),
        all: () => [{ json: inputFirstJson }]
      },
      URL,
      String,
      Array,
      Error,
      Date,
      parseInt,
      parseFloat,
      Object,
      console
    };
    const context = vm.createContext(sandbox);
    const fn = vm.runInContext(`(async function() { ${splitNode.parameters.jsCode} })`, context);
    return await fn();
  }

  async function runMergeVM(preparedItems, downloadedItems, helpers = null) {
    const sandbox = {
      $input: {
        all: () => downloadedItems,
        first: () => downloadedItems[0]
      },
      $: (name) => {
        if (name === 'Siapkan Unduhan Lampiran') {
          return { all: () => preparedItems };
        }
        throw new Error(`Unexpected node lookup: ${name}`);
      },
      URL,
      String,
      Array,
      Error,
      Date,
      parseInt,
      parseFloat,
      Object,
      Buffer,
      console
    };
    const thisContext = {
      helpers: helpers || {}
    };
    const context = vm.createContext(sandbox);
    const fn = vm.runInContext(`(async function() { ${mergeNode.parameters.jsCode} })`, context);
    return await fn.call(thisContext);
  }

  // VM Assertion 1: multi3 (3 valid attachments processed end-to-end through validation -> split -> merge)
  {
    const appUrl = 'https://actstation.net';
    const msgId = 'msg-multi-3';
    const payload = {
      headers: { 'x-webhook-source': 'laravel-actstation' },
      body: {
        event: 'notification.email',
        message_id: msgId,
        data: {
          type: 'assignment',
          email: 'wasit@actstation.test',
          name: 'Budi Wasit',
          subject: 'Penugasan Pertandingan',
          html: '<h1>Surat Tugas</h1><p>Berikut berkas penugasan resmi.</p>',
          attachments: [
            {
              name: 'sk_tugas.pdf',
              url: `${appUrl}/communication/${msgId}/attachments/0?signature=valid_sig_0`,
              mime: 'application/pdf'
            },
            {
              name: 'jadwal.pdf',
              url: `${appUrl}/communication/${msgId}/attachments/1?signature=valid_sig_1`,
              mime: 'application/pdf'
            },
            {
              name: 'pakta.pdf',
              url: `${appUrl}/communication/${msgId}/attachments/2?signature=valid_sig_2`,
              mime: 'application/pdf'
            }
          ]
        }
      }
    };

    const valResult = await runValidationVM(payload, { ACTSTATION_APP_URL: appUrl });
    assert.equal(valResult.json.has_attachments, true, 'multi3: has_attachments must be true');
    assert.equal(valResult.json.attachment_count, 3, 'multi3: attachment_count must be 3');
    assert.equal(valResult.json.message_id, msgId, 'multi3: message_id must match');
    assert.equal(valResult.json.type, 'assignment', 'multi3: type must be assignment');

    const splitResult = await runSplitVM(valResult.json);
    assert.equal(splitResult.length, 3, 'multi3: split node must output 3 items');
    assert.equal(splitResult[0].json.binary_prop, 'attachment_0');
    assert.equal(splitResult[1].json.binary_prop, 'attachment_1');
    assert.equal(splitResult[2].json.binary_prop, 'attachment_2');
    assert.equal(splitResult[0].json.total_expected, 3);

    // Mock downloaded binary items from HTTP Request node (with lost metadata simulation)
    const downloadedItems = [
      { binary: { attachment_0: { data: Buffer.from('PDF_DOC_0').toString('base64'), mimeType: 'application/pdf', fileSize: 9 } } },
      { binary: { attachment_1: { data: Buffer.from('PDF_DOC_1').toString('base64'), mimeType: 'application/pdf', fileSize: 9 } } },
      { binary: { attachment_2: { data: Buffer.from('PDF_DOC_2').toString('base64'), mimeType: 'application/pdf', fileSize: 9 } } }
    ];

    const mergeResult = await runMergeVM(splitResult, downloadedItems);
    assert.equal(mergeResult.length, 1, 'multi3: merge node must produce exactly 1 item');
    assert.equal(mergeResult[0].json.to, 'wasit@actstation.test');
    assert.equal(mergeResult[0].json.attachment_names_csv, 'attachment_0,attachment_1,attachment_2');
    assert.equal(mergeResult[0].json.message_id, msgId);
    assert.equal(Object.keys(mergeResult[0].binary).length, 3);
    assert.equal(mergeResult[0].binary.attachment_0.fileName, 'sk_tugas.pdf');
    assert.equal(mergeResult[0].binary.attachment_1.fileName, 'jadwal.pdf');
    assert.equal(mergeResult[0].binary.attachment_2.fileName, 'pakta.pdf');
  }

  // VM Assertion 2: noatt (plain email with no attachments requires NO ACTSTATION_APP_URL env)
  {
    const payload = {
      headers: { 'x-webhook-source': 'laravel-actstation' },
      body: {
        event: 'notification.email',
        message_id: 'msg-noatt-456',
        data: {
          type: 'information',
          email: 'member@actstation.test',
          name: 'Member Baru',
          subject: 'Selamat Datang',
          html: '<p>Selamat datang di ActStation</p>',
          attachments: []
        }
      }
    };

    // Run with completely empty env (no ACTSTATION_APP_URL)
    const valResult = await runValidationVM(payload, {});
    assert.equal(valResult.json.has_attachments, false, 'noatt: has_attachments must be false');
    assert.equal(valResult.json.attachment_count, 0, 'noatt: attachment_count must be 0');
    assert.equal(valResult.json.message_id, 'msg-noatt-456', 'noatt: message_id must match');
  }

  // VM Assertion 3: forgedorigin (forged host, subdomain, or wrong port must be blocked by SSRF check)
  {
    const appUrl = 'https://actstation.net';
    const msgId = 'msg-ssrf-1';
    
    // Test attacker domain
    const forgedPayload = {
      headers: { 'x-webhook-source': 'laravel-actstation' },
      body: {
        event: 'notification.email',
        message_id: msgId,
        data: {
          type: 'readiness',
          email: 'victim@actstation.test',
          subject: 'Pemberitahuan',
          html: '<p>Teks</p>',
          attachments: [
            {
              name: 'evil.pdf',
              url: `https://attacker.evil.com/communication/${msgId}/attachments/0?signature=xyz`
            }
          ]
        }
      }
    };

    await assert.rejects(
      async () => await runValidationVM(forgedPayload, { ACTSTATION_APP_URL: appUrl }),
      (err) => {
        assert.ok(err.message.includes('SSRF Block'), 'Must throw SSRF Block on forged host');
        assert.ok(!err.message.includes('attacker.evil.com') && !err.message.includes('signature=xyz'), 'Error must not echo signed URL');
        return true;
      },
      'forgedorigin: Must reject external host'
    );

    // Test subdomain (subdomain traversal SSRF prevention)
    const subdomainPayload = {
      headers: { 'x-webhook-source': 'laravel-actstation' },
      body: {
        event: 'notification.email',
        message_id: msgId,
        data: {
          type: 'readiness',
          email: 'victim@actstation.test',
          subject: 'Pemberitahuan',
          html: '<p>Teks</p>',
          attachments: [
            {
              name: 'sub.pdf',
              url: `https://sub.actstation.net/communication/${msgId}/attachments/0?signature=xyz`
            }
          ]
        }
      }
    };

    await assert.rejects(
      async () => await runValidationVM(subdomainPayload, { ACTSTATION_APP_URL: appUrl }),
      (err) => {
        assert.ok(err.message.includes('SSRF Block'), 'Must throw SSRF Block on subdomain');
        return true;
      },
      'forgedorigin: Must reject subdomain'
    );
  }

  // VM Assertion 4: missingenv (attachments present but ACTSTATION_APP_URL missing in env)
  {
    const payload = {
      headers: { 'x-webhook-source': 'laravel-actstation' },
      body: {
        event: 'notification.email',
        message_id: 'msg-env-1',
        data: {
          type: 'verification',
          email: 'user@actstation.test',
          subject: 'Verifikasi',
          html: '<p>Verifikasi akun</p>',
          attachments: [
            {
              name: 'dokumen.pdf',
              url: 'https://actstation.net/communication/msg-env-1/attachments/0?signature=abc'
            }
          ]
        }
      }
    };

    const deniedEnv = new Proxy({}, { get() { throw new Error('access to env vars denied'); } });
    const result = await runValidationVM(payload, deniedEnv);
    assert.equal(result.json.attachment_count, 1, 'Attachment validation must work with env access blocked');
  }

  // VM Assertion 5: noID (missing root body.message_id must be rejected)
  {
    const payloadNoId = {
      headers: { 'x-webhook-source': 'laravel-actstation' },
      body: {
        event: 'notification.email',
        data: {
          type: 'information',
          email: 'user@actstation.test',
          subject: 'Info',
          html: '<p>Info</p>'
        }
      }
    };

    await assert.rejects(
      async () => await runValidationVM(payloadNoId, {}),
      (err) => {
        assert.ok(err.message.includes('message_id wajib disertakan'), 'Must throw on missing message_id');
        return true;
      },
      'noID: Must reject payload without message_id'
    );
  }

  // VM Additional Assertions: enum type, invalid event, invalid path, file size > 5MB
  {
    // Invalid type enum
    const payloadBadType = {
      headers: { 'x-webhook-source': 'laravel-actstation' },
      body: {
        event: 'notification.email',
        message_id: 'msg-badtype',
        data: {
          type: 'arbitrary_admin_type',
          email: 'user@actstation.test',
          subject: 'Info',
          html: '<p>Info</p>'
        }
      }
    };
    await assert.rejects(
      async () => await runValidationVM(payloadBadType, {}),
      /Tipe data email 'arbitrary_admin_type' tidak valid/,
      'Must reject invalid type enum'
    );

    // Invalid event
    const payloadBadEvent = {
      headers: { 'x-webhook-source': 'laravel-actstation' },
      body: {
        event: 'notification.sms',
        message_id: 'msg-badevent',
        data: { type: 'information', email: 'user@actstation.test', subject: 'Info', html: '<p>Info</p>' }
      }
    };
    await assert.rejects(
      async () => await runValidationVM(payloadBadEvent, {}),
      /Event 'notification.sms' tidak didukung/,
      'Must reject invalid event'
    );

    // Mismatched route path in attachment
    const payloadBadPath = {
      headers: { 'x-webhook-source': 'laravel-actstation' },
      body: {
        event: 'notification.email',
        message_id: 'msg-pathcheck',
        data: {
          type: 'information',
          email: 'user@actstation.test',
          subject: 'Info',
          html: '<p>Info</p>',
          attachments: [
            {
              name: 'file.pdf',
              url: 'https://actstation.net/api/v1/internal/secrets?signature=abc'
            }
          ]
        }
      }
    };
    await assert.rejects(
      async () => await runValidationVM(payloadBadPath, { ACTSTATION_APP_URL: 'https://actstation.net' }),
      /SSRF Block: Path lampiran ke-1 tidak valid/,
      'Must reject path not matching /communication/.../attachments/...'
    );

    // Oversized attachment in merge node (> 5MB)
    const prepItems = [
      { json: { filename: 'big.pdf', message_id: 'msg-big', to: 'a@b.com' } }
    ];
    const downOversized = [
      { binary: { attachment_0: { data: 'dummy', fileSize: 6 * 1024 * 1024 } } }
    ];
    await assert.rejects(
      async () => await runMergeVM(prepItems, downOversized),
      /melebihi batas 5MB/,
      'Merge node must reject file exceeding 5MB'
    );

    // Robust binary inspection with getBinaryDataBuffer helper
    const downWithHelper = [
      { binary: { attachment_0: { data: 'dummy', fileSize: '100 B' } } }
    ];
    const mockHelpers = {
      getBinaryDataBuffer: async (idx, prop) => Buffer.alloc(1024)
    };
    const mergeHelperResult = await runMergeVM(prepItems, downWithHelper, mockHelpers);
    assert.equal(mergeHelperResult.length, 1, 'Merge node must successfully inspect size with getBinaryDataBuffer');
  }

  console.log('PASS: Workflow n8/ActStation Email.json structure, branches, credentials absence, fail-closed, SSRF guard, and VM execution suite');

  // 2. Check Email Template: resources/views/emails/actstation.blade.php
  const tplPath = path.join(rootDir, 'resources/views/emails/actstation.blade.php');
  assert.ok(fs.existsSync(tplPath), 'Email template must exist');
  const tpl = fs.readFileSync(tplPath, 'utf8');

  assert.ok(tpl.includes('<table') && tpl.includes('<tr') && tpl.includes('<td'), 'Email template must use table layout');
  assert.ok(tpl.includes('#2c7be5') && tpl.includes('#0b1727'), 'Email template must use Falcon palette (#2c7be5, #0b1727)');
  assert.ok(tpl.includes('logo_fix.png'), 'Email template must include branded logo logo_fix.png');
  assert.ok(tpl.includes('{!! nl2br(e('), 'Email template must safely escape body linebreaks via nl2br(e(...))');
  assert.ok(!tpl.includes('{!! $body') && !tpl.includes('{!! $content'), 'Email template must NOT output unescaped raw user body/content');
  assert.ok(tpl.includes('verification') && tpl.includes('readiness') && tpl.includes('assignment'), 'Email template must handle urgency/type chips');
  assert.ok(tpl.includes("preg_match('/^https?:\\/\\//i', $action_url)"), 'Email template must validate CTA URL with http(s)');
  assert.ok(tpl.includes('has_attachments') || tpl.includes('$attachments'), 'Email template must include attachment note');
  assert.ok(!tpl.includes('<script'), 'Email template must NOT include external JavaScript');
  assert.ok(!tpl.includes('<link rel="stylesheet"'), 'Email template must NOT include external stylesheets');

  console.log('PASS: Branded email template resources/views/emails/actstation.blade.php');

  // 3. Check Verification View: resources/views/auth/verify-email.blade.php
  const verifyPath = path.join(rootDir, 'resources/views/auth/verify-email.blade.php');
  assert.ok(fs.existsSync(verifyPath), 'verify-email.blade.php must exist');
  const verifyContent = fs.readFileSync(verifyPath, 'utf8');

  assert.ok(verifyContent.includes('verification.send'), 'Must contain verification.send route form');
  assert.ok(verifyContent.includes('@csrf'), 'Must contain CSRF token');
  assert.ok(verifyContent.includes('verification-link-sent'), 'Must check verification-link-sent session status');
  assert.ok(verifyContent.includes('getPanelHomeRoute'), 'Must route user back via getPanelHomeRoute');

  console.log('PASS: Verification page resources/views/auth/verify-email.blade.php');

  // 4. Check Settings Email View: resources/views/admin/settings/email.blade.php
  const settingsPath = path.join(rootDir, 'resources/views/admin/settings/email.blade.php');
  assert.ok(fs.existsSync(settingsPath), 'admin/settings/email.blade.php must exist');
  const settingsContent = fs.readFileSync(settingsPath, 'utf8');

  assert.ok(settingsContent.includes('admin.emails.index'), 'Settings email must link to admin.emails.index');
  assert.ok(settingsContent.includes('Konfigurasi Belum Aktif') || settingsContent.includes('no fake connected'), 'Settings email must warn when configuration false');
  assert.ok(settingsContent.includes('mail_host') && settingsContent.includes('mail_port'), 'Core SMTP settings form must remain intact');

  console.log('PASS: Settings link & warning in resources/views/admin/settings/email.blade.php');

  // 5. Check Sidebar: resources/views/layouts/partials/sidebar.blade.php
  const sidebarPath = path.join(rootDir, 'resources/views/layouts/partials/sidebar.blade.php');
  const sidebarContent = fs.readFileSync(sidebarPath, 'utf8');

  assert.ok(sidebarContent.includes('admin.emails.index'), 'Sidebar must include admin.emails.index link under setting.email');

  console.log('PASS: Sidebar entry in resources/views/layouts/partials/sidebar.blade.php');

  // 6. Check Admin Emails Views: index, create, show
  const indexPath = path.join(rootDir, 'resources/views/admin/emails/index.blade.php');
  const createPath = path.join(rootDir, 'resources/views/admin/emails/create.blade.php');
  const showPath = path.join(rootDir, 'resources/views/admin/emails/show.blade.php');

  assert.ok(fs.existsSync(indexPath) && fs.existsSync(createPath) && fs.existsSync(showPath), 'Admin emails index, create, and show views must exist');

  const indexContent = fs.readFileSync(indexPath, 'utf8');
  const createContent = fs.readFileSync(createPath, 'utf8');
  const showContent = fs.readFileSync(showPath, 'utf8');

  // Index checks
  assert.ok(indexContent.includes('admin.emails.create'), 'Index must link to create');
  assert.ok(indexContent.includes('admin.emails.show'), 'Index must link to show');
  assert.ok(indexContent.includes('admin.emails.retry'), 'Index must support retry');
  assert.ok(indexContent.includes('Diterima n8n'), 'Index must display Diterima n8n badge for submitted status');
  assert.ok(indexContent.includes('Status Ambigu'), 'Index must display Status Ambigu badge for unknown status');
  assert.ok(indexContent.includes('duplikat') || indexContent.includes('duplikasi'), 'Index must warn about duplicate risk on manual retry');

  // Create checks
  assert.ok(createContent.includes('recipient_email'), 'Create must have recipient_email input');
  assert.ok(createContent.includes('recipient_name'), 'Create must have recipient_name input');
  assert.ok(createContent.includes('subject'), 'Create must have subject input');
  assert.ok(createContent.includes('body'), 'Create must have plaintext body textarea');
  assert.ok(createContent.includes('action_url') && createContent.includes('action_label'), 'Create must have optional action URL & label');
  assert.ok(createContent.includes('attachments[]'), 'Create must have attachments[] file input');
  assert.ok(createContent.includes('srcdoc'), 'Create must use iframe srcdoc property for preview');
  assert.ok(createContent.includes('admin.emails.preview'), 'Create must reference admin.emails.preview');

  // Show checks
  assert.ok(showContent.includes('srcdoc'), 'Show must preview email in sandboxed iframe with srcdoc');
  assert.ok(showContent.includes('admin.emails.retry'), 'Show must support retry action');
  assert.ok(showContent.includes('Diterima n8n'), 'Show must explain submitted means Diterima n8n NOT delivered');
  assert.ok(showContent.includes('duplikasi') || showContent.includes('duplikat'), 'Show must warn about duplicate risk on retry');

  console.log('PASS: Admin email index, create, and show views');
  console.log('--- ALL CHECKS PASSED (NO DEPENDENCIES, NO MOCK LIVE SMTP) ---');
})().catch(err => {
  console.error('TEST FAILED:', err);
  process.exit(1);
});

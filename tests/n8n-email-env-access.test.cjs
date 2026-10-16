const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const workflow = JSON.parse(fs.readFileSync(path.join(__dirname, '../n8/ActStation Email.json'), 'utf8'));
const code = workflow.nodes.find(node => node.name === 'Validasi Payload & Keamanan').parameters.jsCode;

function validate(origin = 'https://actstation.net') {
    const sandbox = {
        $input: { item: { json: {
            headers: { 'x-webhook-source': 'laravel-actstation' },
            body: { event: 'notification.email', message_id: '42', data: {
                type: 'assignment', email: 'recipient@example.test', title: 'Penugasan',
                html: '<p>Dokumen penugasan</p>', attachments: [{
                    name: 'penugasan.pdf', mime: 'application/pdf',
                    url: `${origin}/communication/42/attachments/0?signature=test-signature`
                }]
            } }
        } } },
        URL
    };
    sandbox.$env = new Proxy({}, { get() { throw new Error('access to env vars denied'); } });
    return vm.runInNewContext(`(function () { ${code} })()`, sandbox);
}

test('attachment validation works with n8n environment access blocked', () => {
    const result = validate();
    assert.equal(result.json.attachment_count, 1);
    assert.equal(result.json.message_id, '42');
});

test('fixed origin still rejects other origins and URL credentials', () => {
    for (const origin of ['https://evil.example', 'https://sub.actstation.net', 'http://actstation.net', 'https://actstation.net:444', 'https://user:pass@actstation.net']) {
        assert.throws(() => validate(origin));
    }
});

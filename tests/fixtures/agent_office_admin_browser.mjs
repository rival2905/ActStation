import { spawn, spawnSync } from 'node:child_process';
import { mkdtemp, rm, access } from 'node:fs/promises';
import { join } from 'node:path';
import { pathToFileURL } from 'node:url';

export async function cleanupAdminBrowser(chrome, socket, profile) {
    try {
        if (chrome?.pid) {
            spawnSync('C:/Windows/System32/taskkill.exe', ['/F', '/T', '/PID', String(chrome.pid)], { stdio: 'ignore', windowsHide: true });
            if (chrome.exitCode === null) {
                await Promise.race([
                    new Promise(r => chrome.once('exit', r)),
                    new Promise(r => setTimeout(r, 2000))
                ]);
            }
            if (chrome.exitCode === null) throw new Error('browser_cleanup_failed');
        }
    } finally {
        try {
            socket?.close();
        } finally {
            if (profile) {
                await rm(profile, { recursive: true, force: true, maxRetries: 10, retryDelay: 100 });
                try {
                    await access(profile);
                    throw new Error('profile_cleanup_failed');
                } catch (e) {
                    if (e.code !== 'ENOENT') throw e;
                }
            }
        }
    }
}

// ponytail: fixture targets loopback synthetic admin flow; multi-entry CRUD or hardware WebAuthn needs separate driver.
export async function runAgentOfficeAdminBrowser(input, { signal } = {}) {
    const { origin, email, password, otp, timeout = 30000 } = input ?? {};
    if (!origin || !/^http:\/\/127\.0\.0\.1:[1-9][0-9]{3,4}$/.test(origin)) throw new Error('origin_denied');
    if (!email || typeof email !== 'string' || !password || typeof password !== 'string' || !otp || !/^[0-9]{6}$/.test(otp)) {
        throw new Error('credentials_denied');
    }
    if (signal?.aborted) throw new Error('runner_cancelled');

    const scratch = process.env.TMPDIR;
    if (!scratch || !/hermes[\\/]cache[\\/]scratch/i.test(scratch)) throw new Error('scratch_denied');
    const profile = await mkdtemp(join(scratch, 'agent-office-admin-browser-'));

    let chrome, socket, timer, sessionId, fatal, blocked = 0;
    const pending = new Map();
    const fail = reason => {
        fatal ??= new Error(reason);
        for (const p of pending.values()) p.reject(fatal);
        pending.clear();
    };

    const abort = () => fail('runner_cancelled');
    signal?.addEventListener('abort', abort, { once: true });
    timer = setTimeout(() => fail('runner_timeout'), timeout);

    let seq = 0;
    const call = (method, params = {}, scoped = true) => new Promise((resolve, reject) => {
        if (fatal) return reject(fatal);
        const id = ++seq;
        pending.set(id, { resolve, reject });
        socket.send(JSON.stringify({ id, method, params, ...(scoped && sessionId ? { sessionId } : {}) }));
    });

    const evaluate = async expression => {
        const r = await call('Runtime.evaluate', { expression, returnByValue: true, awaitPromise: true });
        if (r.exceptionDetails) {
            throw new Error(r.exceptionDetails.exception?.description || 'evaluation_denied');
        }
        return r.result.value;
    };

    const wait = async (condition, timeoutMs = 15000) => {
        const start = Date.now();
        while (!(await condition())) {
            if (fatal) throw fatal;
            if (Date.now() - start > timeoutMs) throw new Error('wait_timeout');
            await new Promise(r => setTimeout(r, 40));
        }
    };

    try {
        const env = Object.fromEntries(
            ['SystemRoot', 'WINDIR', 'PATH', 'TEMP', 'TMP']
                .filter(k => process.env[k])
                .map(k => [k, process.env[k]])
        );
        chrome = spawn('C:/Program Files/Google/Chrome/Application/chrome.exe', [
            '--headless=new',
            '--remote-debugging-port=0',
            '--remote-debugging-address=127.0.0.1',
            '--user-data-dir=' + profile,
            '--no-first-run',
            '--no-default-browser-check',
            '--disable-background-networking',
            '--disable-component-update',
            '--disable-sync',
            '--disable-extensions',
            '--disable-default-apps',
            '--no-proxy-server',
            '--host-resolver-rules=MAP * ~NOTFOUND, EXCLUDE 127.0.0.1',
            'about:blank',
        ], { env, stdio: ['ignore', 'ignore', 'pipe'], windowsHide: true });

        let endpoint;
        chrome.stderr.on('data', data => {
            endpoint ??= /DevTools listening on (ws:\/\/127\.0\.0\.1:\d+\/devtools\/browser\/[^\s]+)/.exec(String(data))?.[1];
        });
        chrome.on('error', () => fail('browser_unavailable'));
        await wait(() => Boolean(endpoint));

        socket = new WebSocket(endpoint);
        await new Promise((resolve, reject) => {
            socket.onopen = resolve;
            socket.onerror = () => reject(new Error('browser_unavailable'));
        });

        socket.onmessage = ({ data }) => {
            const r = JSON.parse(data);
            if (r.id) {
                const p = pending.get(r.id);
                if (!p) return;
                pending.delete(r.id);
                r.error ? p.reject(new Error(r.error.message || 'browser_protocol_failed')) : p.resolve(r.result);
            } else if (r.method === 'Fetch.requestPaused') {
                (async () => {
                    const p = r.params;
                    if (p.responseErrorReason) {
                        fail('response_transport_error');
                        return;
                    }
                    const reqUrl = p.request.url;
                    const allowed = reqUrl.startsWith(origin + '/') || reqUrl === origin || reqUrl === 'about:blank';
                    if (!allowed) {
                        blocked++;
                        await call('Fetch.failRequest', { requestId: p.requestId, errorReason: 'BlockedByClient' });
                        return;
                    }
                    await call('Fetch.continueRequest', { requestId: p.requestId });
                })().catch(() => fail('network_guard_failed'));
            }
        };

        const context = await call('Target.createBrowserContext', {}, false);
        const target = await call('Target.createTarget', { url: 'about:blank', browserContextId: context.browserContextId }, false);
        ({ sessionId } = await call('Target.attachToTarget', { targetId: target.targetId, flatten: true }, false));

        await call('Page.enable');
        await call('Network.enable');
        await call('Network.setBypassServiceWorker', { bypass: true });
        await call('Fetch.enable', {
            patterns: [{ urlPattern: '*', requestStage: 'Request' }],
        });

        // 1. Navigate login, fill DOM, requestSubmit
        await call('Page.navigate', { url: origin + '/admin/login' });
        await wait(() => evaluate("document.readyState==='complete' && !!(document.querySelector('form[action*=\"/admin/login\"]') || document.querySelector('form'))"));

        await evaluate(`(()=>{
            const f = document.querySelector('form[action*="/admin/login"]') || document.querySelector('form');
            if (!f) throw new Error('login_form_missing');
            const e = f.querySelector('input[name="email"]');
            const p = f.querySelector('input[name="password"]');
            if (!e || !p) throw new Error('login_fields_missing');
            e.value = ${JSON.stringify(email)};
            e.dispatchEvent(new Event('input', { bubbles: true }));
            e.dispatchEvent(new Event('change', { bubbles: true }));
            p.value = ${JSON.stringify(password)};
            p.dispatchEvent(new Event('input', { bubbles: true }));
            p.dispatchEvent(new Event('change', { bubbles: true }));
            f.requestSubmit();
        })()`);

        // 2. Wait admin redirect, navigate /admin/settings/agent-office
        await wait(() => evaluate("document.readyState==='complete' && location.pathname !== '/admin/login'"));
        await call('Page.navigate', { url: origin + '/admin/settings/agent-office' });
        await wait(() => evaluate("document.readyState==='complete' && location.pathname === '/admin/settings/agent-office'"));

        // 3. Assert title/real form, mobile 393px overflow <= viewport, labels/focus
        const formOk = await evaluate(`(()=>{
            const titleOk = document.title.includes('Keamanan Gateway Office') || (document.querySelector('h1')?.textContent || '').includes('Keamanan Gateway Office');
            const reauthForm = !!document.querySelector('form[action*="/admin/settings/agent-office/reauth"]');
            const updateForm = !!document.querySelector('input[name="_method"][value="PUT"]')?.closest('form');
            return titleOk && reauthForm && updateForm;
        })()`);
        if (!formOk) throw new Error('agent_office_form_missing');

        await call('Emulation.setDeviceMetricsOverride', {
            width: 393,
            height: 852,
            deviceScaleFactor: 1,
            mobile: true,
        });

        const overflowOk = await evaluate(`(()=>{
            const doc = document.documentElement;
            return doc.scrollWidth <= 393 && doc.scrollWidth <= window.innerWidth;
        })()`);
        if (!overflowOk) throw new Error('mobile_viewport_overflow');

        const labelsAndFocusOk = await evaluate(`(()=>{
            const pwd = document.getElementById('gateway-password');
            const otp = document.getElementById('gateway-otp');
            const labelPwd = document.querySelector('label[for="gateway-password"]');
            const labelOtp = document.querySelector('label[for="gateway-otp"]');
            if (!pwd || !otp || !labelPwd || !labelOtp) return false;
            pwd.focus();
            if (document.activeElement !== pwd) return false;
            otp.focus();
            if (document.activeElement !== otp) return false;

            const enabled0 = document.getElementById('enabled-0');
            const label0 = document.querySelector('label[for="enabled-0"]');
            if (!enabled0 || !label0) return false;
            enabled0.focus();
            if (document.activeElement !== enabled0) return false;

            return true;
        })()`);
        if (!labelsAndFocusOk) throw new Error('labels_focus_assertion_failed');

        // 4. Fill password + otp, submit /reauth, wait success
        await evaluate(`(()=>{
            const f = document.querySelector('form[action*="/admin/settings/agent-office/reauth"]');
            if (!f) throw new Error('reauth_form_missing');
            const p = f.querySelector('input[name="password"]');
            const o = f.querySelector('input[name="otp"]');
            if (!p || !o) throw new Error('reauth_fields_missing');
            p.value = ${JSON.stringify(password)};
            p.dispatchEvent(new Event('input', { bubbles: true }));
            p.dispatchEvent(new Event('change', { bubbles: true }));
            o.value = ${JSON.stringify(otp)};
            o.dispatchEvent(new Event('input', { bubbles: true }));
            o.dispatchEvent(new Event('change', { bubbles: true }));
            f.requestSubmit();
        })()`);

        await wait(() => evaluate("document.readyState==='complete' && document.body.innerText.includes('Verifikasi berlaku 120 detik')"));

        // 5. Uncheck entry enabled checkbox, submit PUT form native
        await evaluate(`(()=>{
            const cb = document.getElementById('enabled-0');
            if (!cb) throw new Error('enabled_0_missing');
            cb.checked = false;
            cb.dispatchEvent(new Event('change', { bubbles: true }));
            const f = cb.closest('form');
            if (!f) throw new Error('put_form_missing');
            f.requestSubmit();
        })()`);

        // 6. Assert Status applied/Versi efektif 2
        await wait(() => evaluate("document.readyState==='complete' && document.body.innerText.includes('Status applied') && document.body.innerText.includes('Versi efektif 2')"));

        return {
            success: true,
            login_verified: true,
            title_verified: true,
            mobile_viewport_verified: true,
            labels_focus_verified: true,
            reauth_verified: true,
            status_applied_verified: true,
            effective_version: 2,
            blocked_requests: blocked,
        };
    } finally {
        clearTimeout(timer);
        signal?.removeEventListener('abort', abort);
        await cleanupAdminBrowser(chrome, socket, profile);
    }
}

if (process.argv[1] && import.meta.url === pathToFileURL(process.argv[1]).href) {
    try {
        let raw = '';
        for await (const chunk of process.stdin) {
            raw += chunk;
            if (raw.length > 65536) throw new Error('input_too_large');
        }
        const input = JSON.parse(raw);
        const result = await runAgentOfficeAdminBrowser(input);
        console.log(JSON.stringify(result));
    } catch (err) {
        console.log(JSON.stringify({ success: false, error: err.message || 'admin_browser_failed' }));
        process.exitCode = 1;
    }
}

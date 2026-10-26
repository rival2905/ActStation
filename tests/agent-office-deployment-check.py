"""Native loopback nginx/TLS proof; no Laravel bootstrap or runtime config."""
import http.client
import json
import os
from pathlib import Path
import shutil
import socket
import ssl
import subprocess
import tempfile
import time

ROOT = Path(__file__).resolve().parents[1]
SCRATCH = Path('C:/Users/mr1ri/AppData/Local/hermes/cache/scratch')
os.environ['TMPDIR'] = str(SCRATCH)
PHP = 'C:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe'
NGINX = shutil.which('nginx')
OPENSSL = shutil.which('openssl')
assert NGINX and OPENSSL

def port():
    with socket.socket() as s:
        s.bind(('127.0.0.1', 0))
        return s.getsockname()[1]

def listening(p):
    with socket.socket() as s:
        s.settimeout(.2)
        return s.connect_ex(('127.0.0.1', p)) == 0

work = Path(tempfile.mkdtemp(prefix='gateway-tls-', dir=SCRATCH))
processes = []
ports = [port(), port()]
try:
    (work / 'public').mkdir()
    (work / 'private').mkdir()
    (work / 'logs').mkdir()
    (work / 'temp').mkdir()
    for name in ['registry.enc', 'state.json', 'audit.jsonl', 'admin-mfa.enc']:
        (work / 'private' / name).write_text('PRIVATE-FIXTURE-CANARY', encoding='ascii')
    (work / 'public' / 'index.html').write_text('public-fixture', encoding='ascii')
    subprocess.run([OPENSSL, 'req', '-x509', '-newkey', 'rsa:2048', '-nodes', '-days', '1', '-subj', '/CN=localhost', '-addext', 'subjectAltName=IP:127.0.0.1', '-keyout', str(work/'key.pem'), '-out', str(work/'cert.pem')], check=True, stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL, env={**os.environ, 'MSYS_NO_PATHCONV': '1'})
    backend, frontend = ports
    php = subprocess.Popen([PHP, '-S', f'127.0.0.1:{backend}', str(ROOT/'tests/fixtures/agent_office_peer_router.php')], cwd=work/'public', stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
    processes.append(php)
    conf = f'''pid "{work.as_posix()}/nginx.pid";
error_log "{work.as_posix()}/logs/error.log";
events {{ worker_connections 32; }}
http {{ access_log off; server {{ listen 127.0.0.1:{frontend} ssl; server_name localhost;
ssl_certificate "{work.as_posix()}/cert.pem"; ssl_certificate_key "{work.as_posix()}/key.pem";
root "{(work/'public').as_posix()}";
location ~ ^/(trusted|direct)$ {{ proxy_pass http://127.0.0.1:{backend};
proxy_set_header Host localhost;
proxy_set_header X-Forwarded-For $remote_addr; proxy_set_header X-Forwarded-Proto $scheme;
proxy_set_header Forwarded ""; proxy_set_header X-Forwarded-Host ""; proxy_set_header X-Forwarded-Port ""; proxy_set_header X-Forwarded-Prefix "";
}}
location / {{ try_files $uri =404; }}
}} }}'''
    (work/'nginx.conf').write_text(conf, encoding='utf-8')
    check = subprocess.run([NGINX, '-t', '-p', work.as_posix()+'/', '-c', (work/'nginx.conf').as_posix()], capture_output=True, text=True)
    if check.returncode:
        raise RuntimeError(check.stderr)
    nginx = subprocess.Popen([NGINX, '-p', work.as_posix()+'/', '-c', str(work/'nginx.conf'), '-g', 'daemon off;'], cwd=work, stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
    processes.append(nginx)
    for p in ports:
        for _ in range(60):
            if listening(p):
                break
            time.sleep(.1)
        assert listening(p), 'fixture_listener_missing'
    ctx = ssl.create_default_context(cafile=str(work/'cert.pem'))
    def request(target, headers=None, tls=True):
        c = http.client.HTTPSConnection('127.0.0.1', frontend, context=ctx, timeout=5) if tls else http.client.HTTPConnection('127.0.0.1', backend, timeout=5)
        try:
            c.request('GET', target, headers=headers or {})
            r = c.getresponse()
            return r.status, r.read()
        finally:
            c.close()
    spoof = {'X-Forwarded-For': '203.0.113.9', 'X-Forwarded-Proto': 'https', 'Forwarded': 'for=203.0.113.9;proto=https'}
    status, body = request('/direct', spoof, False)
    assert status == 200 and json.loads(body)['ip'] == '127.0.0.1' and json.loads(body)['secure'] is False
    target = '/trusted?z=%C3%A9&b=%2B&a=1'
    status, body = request(target, spoof)
    assert status == 200 and json.loads(body) == {'ip':'127.0.0.1', 'secure':True, 'raw_target':target}, 'proxy_raw_bytes_or_peer_changed'
    status, _ = request('/trusted', spoof, False)
    assert status == 403, 'trusted_proxy_accepted_forbidden_header'
    count = 3
    for target in ['/registry.enc', '/state.json', '/audit.jsonl', '/admin-mfa.enc', '/storage/registry.enc', '/private/registry.enc', '/../private/registry.enc', '/%2e%2e/private/registry.enc', '/storage/../private/admin-mfa.enc']:
        status, body = request(target)
        assert status in [400,403,404] and b'PRIVATE-FIXTURE-CANARY' not in body, 'private_file_exposed'
        count += 1
    print(f'native nginx TLS: {count} requests passed; verified certificate, overwritten spoof, direct spoof ignored, raw target unchanged, private files denied')
finally:
    for proc in reversed(processes):
        if proc.poll() is None:
            subprocess.run(['taskkill.exe', '/F', '/T', '/PID', str(proc.pid)], stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
            proc.wait(timeout=10)
    assert all(not listening(p) for p in ports), 'fixture_listener_survived'
    shutil.rmtree(work)
    assert not work.exists(), 'fixture_directory_survived'
    print('TLS fixture cleanup readback: processes exited, listeners closed, directory absent')

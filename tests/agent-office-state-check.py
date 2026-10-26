"""Single-node replay/quota integrity across native PHP processes."""
import concurrent.futures
import os
import pathlib
import subprocess
import sys
import tempfile

php = sys.argv[1]
fixture = pathlib.Path(__file__).resolve().parent / 'fixtures' / 'agent_office_state.php'
with tempfile.TemporaryDirectory(prefix='gateway-race-', dir=os.environ['TMPDIR']) as directory:
    for mode in ('claim', 'hit'):
        state = str(pathlib.Path(directory) / (mode + '.json'))
        def call(_):
            p = subprocess.run([php, str(fixture), state, mode], capture_output=True, text=True, timeout=20)
            assert p.returncode == 0, 'state_process_failed'
            assert p.stdout in ('winner', 'denied'), 'state_output_invalid'
            return p.stdout
        with concurrent.futures.ThreadPoolExecutor(max_workers=6) as pool:
            results = list(pool.map(call, range(6)))
        assert results.count('winner') == 1, 'multiple_winners'
        assert call(None) == 'denied', 'replay_after_process_restart'
print('native shared replay and quota: one winner each, restart denied')

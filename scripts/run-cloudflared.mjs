import { existsSync } from 'node:fs';
import { spawn } from 'node:child_process';
import { resolve } from 'node:path';

const localBinary = resolve('tools', 'cloudflared.exe');
const command = existsSync(localBinary) ? localBinary : 'cloudflared';
const args = ['tunnel', '--url', 'http://127.0.0.1:8000'];

const child = spawn(command, args, {
    stdio: 'inherit',
    shell: process.platform === 'win32' && command === 'cloudflared',
});

child.on('exit', (code, signal) => {
    if (signal) {
        process.exit(1);
    }

    process.exit(code ?? 1);
});

for (const signal of ['SIGINT', 'SIGTERM']) {
    process.on(signal, () => {
        child.kill(signal);
    });
}

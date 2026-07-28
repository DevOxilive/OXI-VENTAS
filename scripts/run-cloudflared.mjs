import { existsSync, mkdirSync, readFileSync, rmSync, writeFileSync } from 'node:fs';
import { spawn } from 'node:child_process';
import { dirname, resolve } from 'node:path';

const localBinary = resolve('tools', 'cloudflared.exe');
const command = existsSync(localBinary) ? localBinary : 'cloudflared';
const realtimeConfigPath = resolve('storage', 'framework', 'realtime-tunnel.json');
const children = [];
let shuttingDown = false;

function removeOwnedRealtimeConfig(force = false) {
    if (!existsSync(realtimeConfigPath)) return;

    try {
        const config = JSON.parse(readFileSync(realtimeConfigPath, 'utf8'));

        if (force || Number(config.ownerPid) === process.pid) {
            rmSync(realtimeConfigPath);
        }
    } catch {
        // Un archivo incompleto no debe bloquear el siguiente arranque.
        rmSync(realtimeConfigPath);
    }
}

function saveRealtimeUrl(publicUrl) {
    const url = new URL(publicUrl);

    mkdirSync(dirname(realtimeConfigPath), { recursive: true });
    writeFileSync(realtimeConfigPath, JSON.stringify({
        host: url.hostname,
        port: 443,
        scheme: 'https',
        ownerPid: process.pid,
    }, null, 2));

    console.log(`[reverb-tunnel] Realtime público: ${publicUrl}`);
}

function startTunnel(label, localUrl, onPublicUrl = null) {
    const child = spawn(command, [
        'tunnel',
        '--no-autoupdate',
        '--url',
        localUrl,
    ], {
        stdio: ['ignore', 'pipe', 'pipe'],
        shell: process.platform === 'win32' && command === 'cloudflared',
    });
    let bufferedOutput = '';
    let publicUrlFound = false;

    const forwardOutput = (stream, target) => {
        stream.on('data', (chunk) => {
            const text = chunk.toString();
            target.write(`[${label}] ${text}`);

            if (publicUrlFound) return;

            bufferedOutput = `${bufferedOutput}${text}`.slice(-12000);
            const match = bufferedOutput.match(/https:\/\/[a-z0-9-]+\.trycloudflare\.com/i);

            if (!match) return;

            publicUrlFound = true;
            onPublicUrl?.(match[0]);
        });
    };

    forwardOutput(child.stdout, process.stdout);
    forwardOutput(child.stderr, process.stderr);

    child.on('exit', (code, signal) => {
        if (shuttingDown) return;

        shutdown(signal ? 1 : (code ?? 1));
    });

    children.push(child);
}

function shutdown(exitCode = 0) {
    if (shuttingDown) return;
    shuttingDown = true;

    for (const child of children) {
        if (!child.killed) {
            child.kill();
        }
    }

    removeOwnedRealtimeConfig();
    process.exit(exitCode);
}

removeOwnedRealtimeConfig(true);
startTunnel('reverb-tunnel', 'http://127.0.0.1:8080', saveRealtimeUrl);
startTunnel('app-tunnel', 'http://127.0.0.1:8000');

for (const signal of ['SIGINT', 'SIGTERM']) {
    process.on(signal, () => {
        shutdown(0);
    });
}

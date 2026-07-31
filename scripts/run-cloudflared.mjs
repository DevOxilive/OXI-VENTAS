import { existsSync, mkdirSync, readFileSync, rmSync, writeFileSync } from 'node:fs';
import { spawn } from 'node:child_process';
import { dirname, resolve } from 'node:path';

const localBinary = resolve('tools', 'cloudflared.exe');
const command = existsSync(localBinary) ? localBinary : 'cloudflared';
const appConfigPath = resolve('storage', 'framework', 'app-tunnel.json');
const realtimeConfigPath = resolve('storage', 'framework', 'realtime-tunnel.json');
const children = [];
let shuttingDown = false;

function removeOwnedConfig(path, force = false) {
    if (!existsSync(path)) return;

    try {
        const config = JSON.parse(readFileSync(path, 'utf8'));

        if (force || Number(config.ownerPid) === process.pid) {
            rmSync(path);
        }
    } catch {
        // An incomplete config file should not block the next startup.
        rmSync(path);
    }
}

function saveTunnelConfig(path, label, publicUrl) {
    const url = new URL(publicUrl);

    mkdirSync(dirname(path), { recursive: true });
    writeFileSync(path, JSON.stringify({
        url: publicUrl,
        host: url.hostname,
        port: 443,
        scheme: 'https',
        ownerPid: process.pid,
    }, null, 2));

    console.log(`[${label}] URL publica: ${publicUrl}`);
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

    removeOwnedConfig(appConfigPath);
    removeOwnedConfig(realtimeConfigPath);
    process.exit(exitCode);
}

removeOwnedConfig(appConfigPath, true);
removeOwnedConfig(realtimeConfigPath, true);
startTunnel('reverb-tunnel', 'http://127.0.0.1:8080', (publicUrl) => saveTunnelConfig(realtimeConfigPath, 'reverb-tunnel', publicUrl));
startTunnel('app-tunnel', 'http://127.0.0.1:8000', (publicUrl) => saveTunnelConfig(appConfigPath, 'app-tunnel', publicUrl));

for (const signal of ['SIGINT', 'SIGTERM']) {
    process.on(signal, () => {
        shutdown(0);
    });
}

import { existsSync, unlinkSync } from 'node:fs';
import { spawn, spawnSync } from 'node:child_process';
import { resolve } from 'node:path';

const processes = [];

const prepare = spawnSync('node', ['scripts/prepare-dev.mjs'], {
    stdio: 'inherit',
    shell: process.platform === 'win32',
});

if (prepare.status !== 0) {
    process.exit(prepare.status ?? 1);
}

const run = (command, args, options = {}) => {
    const child = spawn(command, args, {
        stdio: 'inherit',
        shell: process.platform === 'win32',
        ...options,
    });

    processes.push(child);
    return child;
};

const hotFile = resolve('public', 'hot');

if (existsSync(hotFile)) {
    unlinkSync(hotFile);
}

run('php', ['artisan', 'serve', '--host=127.0.0.1', '--port=8000']);
run('php', ['artisan', 'reverb:start']);
run('npm', ['run', 'dev:watch']);
run('npm', ['run', 'dev:tunnel']);

const shutdown = (signal) => {
    for (const child of processes) {
        if (!child.killed) {
            child.kill(signal);
        }
    }

    process.exit(0);
};

for (const signal of ['SIGINT', 'SIGTERM']) {
    process.on(signal, () => shutdown(signal));
}

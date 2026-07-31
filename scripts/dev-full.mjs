import { existsSync, unlinkSync } from 'node:fs';
import { spawn, spawnSync } from 'node:child_process';
import { resolve } from 'node:path';

const processes = new Set();
const restartAttempts = new Map();
let shuttingDown = false;
const maxRestartAttempts = 3;

const prepare = spawnSync('node', ['scripts/prepare-dev.mjs'], {
    stdio: 'inherit',
    shell: process.platform === 'win32',
});

if (prepare.status !== 0) {
    process.exit(prepare.status ?? 1);
}

const run = (label, command, args, options = {}) => {
    const { restart = false, ...spawnOptions } = options;
    const child = spawn(command, args, {
        stdio: 'inherit',
        shell: process.platform === 'win32',
        ...spawnOptions,
    });

    processes.add(child);

    child.on('exit', (code, signal) => {
        processes.delete(child);

        if (shuttingDown) return;

        if (restart) {
            const attempts = (restartAttempts.get(label) ?? 0) + 1;
            restartAttempts.set(label, attempts);

            if (attempts <= maxRestartAttempts) {
                console.error(
                    `[${label}] El proceso se cerró inesperadamente. ` +
                    `Reinicio ${attempts} de ${maxRestartAttempts} en 2 segundos.`,
                );
                setTimeout(() => run(label, command, args, options), 2000);
                return;
            }
        }

        console.error(
            `[${label}] El proceso terminó${signal ? ` por ${signal}` : ` con código ${code ?? 1}`}. ` +
            'Se detendrá el entorno para evitar trabajar sin servicios esenciales.',
        );
        shutdown(signal ?? 'SIGTERM', code ?? 1);
    });

    return child;
};

const hotFile = resolve('public', 'hot');

if (existsSync(hotFile)) {
    unlinkSync(hotFile);
}

run('laravel', 'php', ['artisan', 'serve', '--host=127.0.0.1', '--port=8000']);
run('reverb', 'php', ['artisan', 'reverb:start'], { restart: true });
run('vite', 'npm', ['run', 'dev:watch']);
run('tunnel', 'npm', ['run', 'dev:tunnel']);

const shutdown = (signal, exitCode = 0) => {
    if (shuttingDown) return;
    shuttingDown = true;

    for (const child of processes) {
        if (!child.killed) {
            child.kill(signal);
        }
    }

    process.exit(exitCode);
};

for (const signal of ['SIGINT', 'SIGTERM']) {
    process.on(signal, () => shutdown(signal));
}

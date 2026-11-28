import { existsSync, readFileSync, statSync, unlinkSync } from 'node:fs';
import { spawn, spawnSync } from 'node:child_process';
import { createConnection } from 'node:net';
import { resolve } from 'node:path';

const isWindows = process.platform === 'win32';
const npmCommand = isWindows ? (process.env.ComSpec || 'cmd.exe') : 'npm';
const npmRunArgs = (script) => (
    isWindows ? ['/d', '/s', '/c', `npm run ${script}`] : ['run', script]
);
const phpCommand = process.env.PHP_BINARY
    || (isWindows ? 'C:\\laragon\\bin\\php\\php-8.3.30-Win32-vs16-x64\\php.exe' : 'php');
const processes = new Set();
const restartAttempts = new Map();
let shuttingDown = false;
const maxRestartAttempts = 3;

const prepare = spawnSync(process.execPath, ['scripts/prepare-dev.mjs'], {
    stdio: 'inherit',
});

if (prepare.status !== 0) {
    process.exit(prepare.status ?? 1);
}

// La búsqueda actual usa Meilisearch. Este comando solo comprueba o inicia el
// servicio y luego devuelve el control; los demás servicios sí quedan vigilados.
const meilisearch = spawnSync(npmCommand, npmRunArgs('dev:meilisearch'), {
    stdio: 'inherit',
    windowsHide: true,
});

if (meilisearch.status !== 0) {
    // La aplicación tiene búsqueda MySQL de respaldo. Un bloqueo local de
    // Meilisearch no debe impedir que la PWA ni el resto del entorno arranquen.
    console.warn(
        '[meilisearch] No se pudo iniciar; se usará la búsqueda de respaldo configurada en la aplicación.',
    );
}

const isPortAvailable = (port) => new Promise((resolvePort) => {
    const socket = createConnection({ host: '127.0.0.1', port });

    socket.once('connect', () => {
        socket.destroy();
        resolvePort(true);
    });
    socket.once('error', () => resolvePort(false));
});

const run = (label, command, args, options = {}) => {
    const { restart = false, ...spawnOptions } = options;
    const child = spawn(command, args, {
        stdio: 'inherit',
        windowsHide: true,
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
const appTunnelConfig = resolve('storage', 'framework', 'app-tunnel.json');
const buildManifest = resolve('public', 'build', 'manifest.json');
const startedAt = Date.now();

if (existsSync(hotFile)) {
    unlinkSync(hotFile);
}

// Evita mostrar la URL que dejó un túnel de una ejecución anterior mientras
// cloudflared crea la URL pública de este arranque.
if (existsSync(appTunnelConfig)) {
    unlinkSync(appTunnelConfig);
}

if (await isPortAvailable(8000)) {
    console.log('[laravel] Ya hay un servidor en http://127.0.0.1:8000; se reutilizara.');
} else {
    run('laravel', phpCommand, ['artisan', 'serve', '--host=127.0.0.1', '--port=8000']);
}

if (await isPortAvailable(8080)) {
    console.log('[reverb] Ya hay un servidor en ws://127.0.0.1:8080; se reutilizara.');
} else {
    run('reverb', phpCommand, ['artisan', 'reverb:start'], { restart: true });
}

run('vite', npmCommand, npmRunArgs('dev:watch'));
run('inventory', process.execPath, ['scripts/inventory-worker.mjs']);
run('tunnel', npmCommand, npmRunArgs('dev:tunnel'));

const wait = (milliseconds) => new Promise((done) => setTimeout(done, milliseconds));

const waitForPort = async (port, timeout = 30000) => {
    const deadline = Date.now() + timeout;

    while (Date.now() < deadline) {
        if (await isPortAvailable(port)) return true;
        await wait(250);
    }

    return false;
};

const getPublicUrl = () => {
    if (!existsSync(appTunnelConfig)) return null;

    try {
        const { url } = JSON.parse(readFileSync(appTunnelConfig, 'utf8'));
        return typeof url === 'string' && url.startsWith('https://') ? url : null;
    } catch {
        return null;
    }
};

const waitForFirstBuild = async (timeout = 90000) => {
    const deadline = Date.now() + timeout;

    while (Date.now() < deadline) {
        try {
            if (statSync(buildManifest).mtimeMs >= startedAt) return true;
        } catch {
            // Vite todavía no ha generado el manifiesto de la compilación inicial.
        }

        await wait(250);
    }

    return false;
};

const showStartupUrl = async () => {
    await Promise.all([
        waitForPort(8000),
        waitForFirstBuild(),
    ]);

    // El manifiesto se escribe justo antes de que Vite termine de listar los
    // archivos generados. Esta pausa corta deja la URL como el último bloque
    // visible del arranque, en lugar de perderla entre el listado de assets.
    await wait(1000);

    const deadline = Date.now() + 30000;
    let publicUrl = getPublicUrl();

    while (!publicUrl && Date.now() < deadline) {
        await wait(250);
        publicUrl = getPublicUrl();
    }

    console.log('\n══════════════ PWA LISTA ══════════════');
    console.log(`  Abre: ${publicUrl ?? 'http://127.0.0.1:8000'}`);

    if (publicUrl) {
        console.log('  Local: http://127.0.0.1:8000');
    } else {
        console.log('  El túnel público no respondió; usa la URL local.');
    }

    console.log('════════════════════════════════════════\n');
};

void showStartupUrl();

const stopChild = (child, signal) => {
    if (child.killed || child.pid === undefined) return;

    if (isWindows) {
        // npm.cmd y PowerShell crean procesos descendientes. Terminar el árbol
        // evita que Reverb, Vite o cloudflared queden ocupando puertos al salir.
        spawnSync('taskkill', ['/pid', String(child.pid), '/t', '/f'], {
            stdio: 'ignore',
            windowsHide: true,
        });
        return;
    }

    child.kill(signal);
};

const shutdown = (signal, exitCode = 0) => {
    if (shuttingDown) return;
    shuttingDown = true;

    for (const child of processes) {
        stopChild(child, signal);
    }

    process.exit(exitCode);
};

for (const signal of ['SIGINT', 'SIGTERM']) {
    process.on(signal, () => shutdown(signal));
}

import { copyFileSync, existsSync, readFileSync } from 'node:fs';
import { spawnSync } from 'node:child_process';
import { resolve } from 'node:path';

const envPath = resolve('.env');
const envExamplePath = resolve('.env.example');

if (!existsSync(envPath)) {
    copyFileSync(envExamplePath, envPath);
    console.log('[prepare-dev] Created .env from .env.example');
}

const envContents = readFileSync(envPath, 'utf8');
const appKeyLine = envContents
    .split(/\r?\n/)
    .find((line) => line.startsWith('APP_KEY='));

if (!appKeyLine || appKeyLine === 'APP_KEY=') {
    const keyResult = spawnSync('php', ['artisan', 'key:generate', '--force'], {
        stdio: 'inherit',
        shell: process.platform === 'win32',
    });

    if (keyResult.status !== 0) {
        process.exit(keyResult.status ?? 1);
    }
}

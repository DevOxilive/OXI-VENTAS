import { spawn } from 'node:child_process';

let stopping = false;
let child;
function work() {
    if (stopping) return;
    child = spawn('php', ['artisan', 'inventory:work', '--wait', '--max-time=45'], {
        stdio: 'inherit', windowsHide: true,
    });
    child.on('error', (error) => console.error(error.message));
    child.on('close', () => { if (!stopping) setTimeout(work, 1000); });
}
for (const signal of ['SIGINT', 'SIGTERM']) {
    process.on(signal, () => { stopping = true; child?.kill(); });
}
work();

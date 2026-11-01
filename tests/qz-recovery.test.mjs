import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import vm from 'node:vm';

const source = fs.readFileSync(new URL('../resources/js/Composables/useQzTray.js', import.meta.url), 'utf8')
  .replace('import qz from "qz-tray";', '')
  .replace(/export /g, '');

function setup() {
  let active = false;
  let connections = 0;
  const timers = [];
  const qz = {
    security: { setCertificatePromise() {}, setSignatureAlgorithm() {}, setSignaturePromise() {} },
    websocket: {
      isActive: () => active,
      connect: async () => { connections++; active = true; },
      disconnect: async () => { active = false; },
      setClosedCallbacks(callbacks) { this.closed = callbacks; },
      setErrorCallbacks() {},
    },
    printers: { find: async () => ['Ticket'] },
    configs: { create: () => ({}) },
    print: async () => {},
  };
  const context = vm.createContext({ qz, console, window: {
    setTimeout(fn) { const timer = { fn }; timers.push(timer); return timer; },
    clearTimeout(timer) { timer.cancelled = true; },
  }});
  vm.runInContext(source, context);
  return { context, qz, timers, connections: () => connections, drop: () => { active = false; qz.websocket.closed.forEach(fn => fn()); } };
}
const flush = async () => { for (let i = 0; i < 30; i++) await Promise.resolve(); };

test('startup recovery runs and reconnects after QZ closes without page reload', async () => {
  const s = setup();
  vm.runInContext('subscribeToQzTrayRecovery(async () => { await getQzPrinters(); return true; })', s.context);
  s.timers.find(t => !t.cancelled).fn();
  await flush();
  assert.equal(s.connections(), 1);
  s.drop();
  s.timers.at(-1).fn();
  await flush();
  assert.equal(s.connections(), 2);
});

test('concurrent callers share a single connection', async () => {
  const s = setup();
  await vm.runInContext('Promise.all([connectQzTray(), connectQzTray(), connectQzTray()])', s.context);
  assert.equal(s.connections(), 1);
});

test('a print with unknown result is not submitted twice', async () => {
  const s = setup();
  let sends = 0;
  s.qz.print = async () => { sends++; throw new Error('Connection closed before response received'); };
  await assert.rejects(vm.runInContext('printEscPosTicket("Ticket", ["drawer"])', s.context), /closed/);
  assert.equal(sends, 1);
});

test('a printer query that never responds releases its caller on timeout', async () => {
  const s = setup();
  s.qz.printers.find = () => new Promise(() => {});
  const pending = vm.runInContext('withTimeout(qz.printers.find(), 30, "timeout")', s.context);
  const rejected = assert.rejects(pending, /timeout/);
  s.timers.at(-1).fn();
  await rejected;
});

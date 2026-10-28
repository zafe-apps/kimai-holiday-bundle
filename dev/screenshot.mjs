// Gestaltete Screenshots einer Seite des Dev-Stacks mit Headless-Chrome über CDP, ohne Playwright.
// Aufruf: node dev/screenshot.mjs <out.png> <url> [width] [--user admin] [--password admin1234]
// Meldet sich per Formular-Login an (Cookie KIMAI_SESSION) und fotografiert die Seite in voller Höhe.
// Umgebung: CHROME_BIN überschreibt den Chrome-Pfad (Standard: macOS-Installation). Debug-Port 9333.
// Braucht Node >= 22 und keine Pakete.
import { spawn } from 'node:child_process';
import { writeFileSync } from 'node:fs';
import { parseArgs } from 'node:util';

const { values, positionals } = parseArgs({
  allowPositionals: true,
  options: { user: { type: 'string', default: 'admin' }, password: { type: 'string', default: 'admin1234' } },
});
const [out, url, widthArg] = positionals;
if (!out || !url) {
  console.error('Aufruf: node dev/screenshot.mjs <out.png> <url> [width] [--user u] [--password p]');
  process.exit(2);
}
const width = Number(widthArg || 1440);
const origin = new URL(url).origin;
const CHROME = process.env.CHROME_BIN || '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';
const port = 9333;

// Login wie curl: CSRF-Token der Login-Seite holen, Formular senden, Session-Cookie lesen.
const cookieOf = (res) => res.headers.getSetCookie().map((c) => c.split(';')[0]).join('; ');
async function login() {
  const page = await fetch(`${origin}/en/login`);
  const jar = cookieOf(page);
  const html = await page.text();
  const token = html.match(/name="_csrf_token"[^>]*value="([^"]+)"/)?.[1] ?? html.match(/value="([^"]+)"[^>]*name="_csrf_token"/)?.[1];
  if (!token) {
    throw new Error('CSRF-Token auf /en/login nicht gefunden');
  }
  const res = await fetch(`${origin}/en/login_check`, {
    method: 'POST',
    redirect: 'manual',
    headers: { cookie: jar, 'content-type': 'application/x-www-form-urlencoded' },
    body: new URLSearchParams({ _username: values.user, _password: values.password, _csrf_token: token }),
  });
  const session = cookieOf(res).split('; ').concat(jar.split('; ')).find((c) => c.startsWith('KIMAI_SESSION='));
  if (!session) {
    throw new Error(`Login fehlgeschlagen (HTTP ${res.status})`);
  }
  return session.slice('KIMAI_SESSION='.length);
}
const sid = await login();

const chrome = spawn(CHROME, ['--headless=new', `--remote-debugging-port=${port}`, '--no-first-run', '--no-default-browser-check', `--window-size=${width},1200`, '--user-data-dir=/tmp/kimai-shot-profile', 'about:blank'], { stdio: 'ignore' });
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
try {
  let targets;
  for (let i = 0; i < 40; i++) {
    try { targets = await (await fetch(`http://127.0.0.1:${port}/json`)).json(); break; } catch { await sleep(250); }
  }
  const page = targets.find((t) => t.type === 'page');
  const ws = new WebSocket(page.webSocketDebuggerUrl);
  let id = 0;
  const pending = new Map();
  const send = (method, params = {}) => new Promise((res, rej) => {
    const i = ++id;
    pending.set(i, { res, rej });
    ws.send(JSON.stringify({ id: i, method, params }));
  });
  ws.onmessage = (ev) => {
    const m = JSON.parse(ev.data);
    if (m.id && pending.has(m.id)) {
      const p = pending.get(m.id);
      pending.delete(m.id);
      m.error ? p.rej(new Error(JSON.stringify(m.error))) : p.res(m.result);
    }
  };
  await new Promise((r) => (ws.onopen = r));
  await send('Network.enable');
  await send('Network.setCookie', { name: 'KIMAI_SESSION', value: sid, url: `${origin}/` });
  await send('Emulation.setDeviceMetricsOverride', { width, height: 1200, deviceScaleFactor: 1, mobile: false });
  await send('Page.enable');
  await send('Page.navigate', { url });
  await sleep(2500);
  const { contentSize } = await send('Page.getLayoutMetrics');
  const height = Math.min(Math.ceil(contentSize.height), 4000);
  await send('Emulation.setDeviceMetricsOverride', { width, height, deviceScaleFactor: 1, mobile: false });
  await sleep(500);
  const shot = await send('Page.captureScreenshot', { format: 'png', captureBeyondViewport: true });
  writeFileSync(out, Buffer.from(shot.data, 'base64'));
  const title = await send('Runtime.evaluate', { expression: 'document.title + " | " + location.href' });
  console.log(title.result.value, `${width}x${height}`, out);
  ws.close();
} finally {
  chrome.kill();
}

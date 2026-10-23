#!/usr/bin/env node
// PostToolUse-Hook: formatiert die gerade geschriebene PHP-Datei mit php-cs-fixer.
//
// Auf dem Entwicklungsrechner gibt es keinen lokalen PHP-Interpreter — php-cs-fixer
// läuft deshalb ausschließlich im Dev-Container (dev/docker-compose.yml, Dienst `kimai`).
// Läuft der Container nicht, passiert nichts (kein Fehler, kein Start des Stacks).
//
// Gleiche Bauart wie block-sensitive-files.js: Payload kommt als JSON auf stdin,
// jeder Fehler ist fail-open (Exit 0) — ein defekter Formatier-Hook darf nie eine
// Änderung verhindern. Abschalten: den PostToolUse-Eintrag aus .claude/settings.json
// entfernen oder DISABLE_AUTOFORMAT=1 setzen.
const { spawnSync } = require('child_process');
const path = require('path');

const COMPOSE = ['compose', '-f', 'dev/docker-compose.yml'];
const PLUGIN_DIR = 'var/plugins/HolidayBundle';

function format(raw) {
  if (process.env.DISABLE_AUTOFORMAT) return;

  const input = JSON.parse(raw);
  const filePath = input && input.tool_input && input.tool_input.file_path;
  if (typeof filePath !== 'string' || filePath.length === 0) return;

  const projectDir = process.env.CLAUDE_PROJECT_DIR || process.cwd();
  const relative = path
    .relative(projectDir, path.resolve(projectDir, filePath))
    .replace(/\\/g, '/');
  // Nur Dateien im Projekt anfassen.
  if (relative.startsWith('..')) return;
  if (path.extname(relative).toLowerCase() !== '.php') return;

  // Läuft der Dev-Container? Sonst nichts tun.
  const ps = spawnSync('docker', [...COMPOSE, 'ps', '-q', 'kimai'], {
    cwd: projectDir,
    encoding: 'utf8',
    timeout: 5000,
  });
  if (ps.status !== 0 || !ps.stdout || ps.stdout.trim().length === 0) return;

  spawnSync(
    'docker',
    [
      ...COMPOSE,
      'exec',
      '-T',
      'kimai',
      'vendor/bin/php-cs-fixer',
      'fix',
      '--quiet',
      '--config=.php-cs-fixer.dist.php',
      `${PLUGIN_DIR}/${relative}`,
    ],
    { cwd: projectDir, stdio: 'ignore', timeout: 30000 },
  );
}

let raw = '';
process.stdin.on('data', (chunk) => (raw += chunk));
process.stdin.on('end', () => {
  try {
    format(raw);
  } catch {
    // fail-open: ein defekter Hook darf keine Änderung blockieren
  }
  process.exit(0);
});

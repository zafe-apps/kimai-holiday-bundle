#!/usr/bin/env node
// PreToolUse-Guardrail: blockt Edit/Write auf sensible Dateien.
// Block = Begründung auf stderr + Exit 2; alles andere (inkl. eigener Fehler) = Exit 0 (fail-open).
const fs = require('fs');

function block(message) {
  console.error(message);
  process.exit(2);
}

function check(raw) {
  const input = JSON.parse(raw);
  const filePath = input && input.tool_input && input.tool_input.file_path;
  if (typeof filePath !== 'string' || filePath.length === 0) return;

  const normalized = filePath.replace(/\\/g, '/');
  const base = normalized.split('/').pop() || '';

  // Regel 1: .env-Dateien (Secrets) — Ausnahme .env.example
  if (/^\.env(\..+)?$/.test(base) && base !== '.env.example') {
    block(
      'BLOCKED: ' +
        base +
        ' enthält Secrets (Datenbank-Zugang, SMTP, APP_SECRET) — nicht editierbar. ' +
        'Nutze .env.example für Vorlagen.',
    );
  }

  // Regel 2: Key-/Credential-Dateien
  if (/\.(pem|key)$/i.test(base) || /^id_rsa/i.test(base) || /^credentials/i.test(base)) {
    block('BLOCKED: ' + base + ' sieht nach Key-/Credential-Material aus — nicht editierbar.');
  }

  // Regel 3: existierende Doctrine-Migrationen (veröffentlichte Migrationen nie editieren)
  if (/(^|\/)Migrations\/Version[^/]*\.php$/.test(normalized) && fs.existsSync(filePath)) {
    block(
      'BLOCKED: ' +
        normalized +
        ' ist eine bereits angelegte Migration — veröffentlichte Migrationen werden nie ' +
        'editiert, sonst driften Installationen auseinander. Lege stattdessen eine neue ' +
        'Migration an.',
    );
  }

  // Regel 4: existierende Linter-/Analyse-/Test-Configs (Checks weichspülen statt Code fixen)
  const toolConfig =
    /^\.php-cs-fixer[^/]*\.php$/.test(base) ||
    /^phpstan[^/]*\.neon(\..+)?$/.test(base) ||
    /^phpunit\.xml(\..+)?$/.test(base);
  if (toolConfig && fs.existsSync(filePath)) {
    block(
      'BLOCKED: ' +
        base +
        ' ist eine Linter-/Analyse-/Test-Config — nicht lockern, um Checks zu bestehen. ' +
        'Den beanstandeten Code fixen; Regeländerungen entscheidet der Mensch.',
    );
  }

  // Regel 5: vendored UI-Kit (nur per Upstream-Sync aktualisieren)
  if (normalized.includes('Resources/views/_kit/')) {
    block(
      'BLOCKED: ' +
        normalized +
        ' gehört zum vendored UI-Kit (Resources/views/_kit/) — lokal nie ändern. ' +
        'Das Kit wird nur per Upstream-Sync aktualisiert; Anpassungen gehören in die ' +
        'Plugin-Templates außerhalb von _kit/.',
    );
  }
}

let raw = '';
process.stdin.on('data', (chunk) => (raw += chunk));
process.stdin.on('end', () => {
  try {
    check(raw);
  } catch {
    // fail-open: defekter Hook darf keine Edits blockieren
  }
  process.exit(0);
});

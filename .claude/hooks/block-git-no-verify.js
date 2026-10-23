#!/usr/bin/env node
// PreToolUse-Guardrail: blockt git-Aufrufe mit --no-verify (Umgehung der pre-commit-Checks).
// Übernommen aus simudrom-backoffice-control (zafe), dort seit 2026-08 im Einsatz.
// Block = Begründung auf stderr + Exit 2; alles andere (inkl. eigener Fehler) = Exit 0 (fail-open).

function block(message) {
  console.error(message);
  process.exit(2);
}

function check(raw) {
  const input = JSON.parse(raw);
  const command = input && input.tool_input && input.tool_input.command;
  if (typeof command !== 'string' || command.length === 0) return;

  // Nur echte git-Kommandos mit --no-verify; bei push heißt -n dry-run, deshalb
  // ausschließlich das Langflag blocken (keine false positives auf Kombi-Kurzflags).
  if (/\bgit\b[^|;&]*\s--no-verify(\s|$|=)/.test(command)) {
    block(
      'BLOCKED: --no-verify umgeht die pre-commit-Checks (Lint). ' +
        'Fehlschlagende Checks reparieren statt umgehen; Ausnahmen entscheidet der Mensch.',
    );
  }
}

let raw = '';
process.stdin.on('data', (chunk) => (raw += chunk));
process.stdin.on('end', () => {
  try {
    check(raw);
  } catch {
    // fail-open: defekter Hook darf keine Commits blockieren
  }
  process.exit(0);
});

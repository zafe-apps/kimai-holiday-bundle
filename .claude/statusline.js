#!/usr/bin/env node
// Cost-Awareness-Statusline: Modell · Git-Branch · Session-Kosten · geänderte Zeilen.
// Spec: siehe Git-History (specs/2026-08-12-harness-quick-wins-design.md, entfernt 2026-08-18)
// Fehlende Felder werden ausgelassen; jeder Fehler → leere Ausgabe, Exit 0.
const { execFileSync } = require('child_process');

let raw = '';
process.stdin.on('data', (chunk) => (raw += chunk));
process.stdin.on('end', () => {
  try {
    const data = JSON.parse(raw);
    const parts = [];

    const model = data && data.model && data.model.display_name;
    if (model) parts.push(model);

    const dir = (data && data.workspace && data.workspace.current_dir) || (data && data.cwd);
    if (dir) {
      try {
        const branch = execFileSync('git', ['-C', dir, 'branch', '--show-current'], {
          timeout: 500,
          stdio: ['ignore', 'pipe', 'ignore'],
        })
          .toString()
          .trim();
        if (branch) parts.push(branch);
      } catch {
        // kein Git-Repo / Timeout — Segment entfällt
      }
    }

    const cost = data && data.cost;
    if (cost && typeof cost.total_cost_usd === 'number') {
      parts.push('$' + cost.total_cost_usd.toFixed(2));
    }
    if (
      cost &&
      (typeof cost.total_lines_added === 'number' || typeof cost.total_lines_removed === 'number')
    ) {
      parts.push('+' + (cost.total_lines_added || 0) + '/-' + (cost.total_lines_removed || 0));
    }

    process.stdout.write(parts.join(' · '));
  } catch {
    // defekte Eingabe → leere Statusline statt Crash
  }
});

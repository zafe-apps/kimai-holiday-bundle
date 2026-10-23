# HANDOFF — WIP

> Stand 2026-10-23.

## Current goal
Plan 1 „Fundament + Urlaub richtig": Fork läuft auf Kimai 2.70.2 bei zafe, Samstag zählt,
Urlaubskonto je Arbeitsjahr mit Übertrag, Genehmigung durch den jeweils anderen Chef, österreichische
Feiertage ohne Gedenktage.

## Done
- Spec freigegeben (`docs/superpowers/specs/2026-10-23-kimai-dienstplan-design.md`).
- Plan 1 geschrieben und auf `feat/fundament-urlaub` committet.
- Claude-Harness aus dem BOC übernommen (`.claude/`).

## In progress / Next
- Umsetzung subagent-driven (superpowers:subagent-driven-development), **Task 1 als Nächstes**
  (Fork-Hygiene, CI auf Kimai 2.70.2, `CLAUDE.md` in der Wurzel).
- Task 8 hebt den Prod-Kimai von 2.58.0 auf 2.70.2 (mit Backup davor und Prüfung danach).

## Settled decisions
- Fork statt WorkContractBundle: `.claude/memory/decisions/0001-fork-statt-workcontractbundle.md`.

## Context to Load (paths only)
- docs/superpowers/specs/2026-10-23-kimai-dienstplan-design.md
- docs/superpowers/plans/2026-10-23-plan-1-fundament-urlaub.md

## Blocked
- Nichts.

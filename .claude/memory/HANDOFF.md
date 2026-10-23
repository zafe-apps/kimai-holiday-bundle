# HANDOFF — WIP

> Stand 2026-10-23 (Abend).

## Current goal
Plan 1 „Fundament + Urlaub richtig" ausliefern: PR #1 mergen, Release v2.0.0, dann Rollout auf
`zafe-server-1` (Task 8) gemeinsam mit dem Betreiber.

## Done
- Spec freigegeben (`docs/superpowers/specs/2026-10-23-kimai-dienstplan-design.md`).
- Plan 1 Tasks 1–7 subagent-driven umgesetzt und je Task reviewt (Fix-Runden bei Task 4 und 5),
  Final-Review über den ganzen Branch (Opus) mit einer Fix-Welle, Re-Review sauber.
- Branch `feat/fundament-urlaub` (21 Commits über upstream `master`), CI grün (Lint 8.2–8.4,
  Unit-Tests in Docker, Kimai-2.70.2-End-to-End), 52 Tests / 106 Assertions.
- **PR #1 offen:** https://github.com/zafe-apps/kimai-holiday-bundle/pull/1 — nicht gemergt.
- Entscheidungen und aufgeschobene Kleinbefunde: SDD-Ledger
  `.superpowers/sdd/2026-10-23-plan-1-fundament-urlaub/progress.md` (git-ignoriert, nur lokal).

## In progress / Next
- Betreiber-Freigabe für Merge + Release (`gh release create v2.0.0`, `release.yml` baut das Zip).
- **Task 8 Rollout** (Plan, Task 8): Backup → Compose auf `kimai/kimai2:2.70.2` → SMTP
  (`MAILER_URL`/`MAILER_FROM`, Zugangsdaten vom Betreiber) → Plugin-Zip ins Volume
  `kimai_kimai_plugins`, `kimai:reload`, `kimai:bundle:holiday:install` → Rollen, Feiertagsgruppe
  `at-de` ab Jahr des frühesten Eintritts, Verträge (Eintrittsdatum, Wochentage, Urlaubstage) →
  Nachtrag alter Urlaube durch die Chefs → systemd-Timer `kimai:bundle:holiday:sync-ics`.
  Protokoll nach `docs/rollout/2026-10-kimai-2.70.2-und-plugin.md`.
- Danach Plan 2 (Standort + Verfügbarkeitsansicht + Aushilfe) und Plan 3 (Tagesverschiebung)
  schreiben; offene Punkte in `TODO.md` (u. a. WebTestCase für Genehmigungsrouten, API/ICS noch
  Kalenderjahr).

## Settled decisions
- Fork statt WorkContractBundle: `decisions/0001-fork-statt-workcontractbundle.md`.
- Urlaubsjahr = Arbeitsjahr ab Eintritt, Anspruch in Arbeitstagen der Person, Übertrag ohne
  Kappung, Kalenderjahr-Rückfall ohne Übertrag (Spec 4.2).
- Niemand genehmigt sich selbst; Mails an alle Genehmiger; Genehmiger-Anlage/-Änderung für andere
  bleibt genehmigt; Undo benachrichtigt (Spec 4.3, Final-Review).
- Dev-Login `admin` / `admin1234` (Kimai-Mindestlänge).

## Context to Load (paths only)
- docs/superpowers/specs/2026-10-23-kimai-dienstplan-design.md
- docs/superpowers/plans/2026-10-23-plan-1-fundament-urlaub.md
- CHANGELOG.md
- TODO.md

## Blocked
- Merge/Release und Rollout warten auf den Betreiber (SMTP-Zugangsdaten, Vertragsdaten je Person,
  Liste bisheriger Urlaube).

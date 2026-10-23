# HANDOFF — WIP

> Stand 2026-10-23 (nach dem Rollout).

## Current goal
Plan 1 in Betrieb nehmen: SMTP, Stammdaten und Nachtrag auf `zeiterfassung.zafe.at`; danach Plan 2
(Standort + Verfügbarkeit + Aushilfe) und Plan 3 (Tagesverschiebung).

## Done
- Spec freigegeben (`docs/superpowers/specs/2026-10-23-kimai-dienstplan-design.md`).
- Plan 1 Tasks 1–7 subagent-driven umgesetzt und je Task reviewt (Fix-Runden bei Task 4 und 5),
  Final-Review über den ganzen Branch (Opus) mit einer Fix-Welle, Re-Review sauber.
- Branch `feat/fundament-urlaub` (21 Commits über upstream `master`), CI grün (Lint 8.2–8.4,
  Unit-Tests in Docker, Kimai-2.70.2-End-to-End), 52 Tests / 106 Assertions.
- PR #1 gemergt (`d3378f7`), Release v2.0.0 veröffentlicht (`HolidayBundle-2.0.0.zip`).
- **Rollout Task 8 teilweise:** Backup, Kimai 2.70.2, Plugin 2.0.0 installiert, ICS-Cron (felix, 04:00) —
  Protokoll `docs/rollout/2026-10-kimai-2.70.2-und-plugin.md`. Image per Override `~felix/kimai-override.yml`
  gepinnt, weil `sudo` ein Passwort verlangt — Compose-Datei von alex muss nachgezogen werden.
- Entscheidungen und aufgeschobene Kleinbefunde: SDD-Ledger
  `.superpowers/sdd/2026-10-23-plan-1-fundament-urlaub/progress.md` (git-ignoriert, nur lokal).

## In progress / Next
- Task 8 technisch abgeschlossen (Mail über Microsoft-Relay intern bestätigt, Feiertagsgruppe
  Österreich, Verträge Manuel/Moritz). **Beim Betreiber:** Nachtrag alter Urlaube, Exchange-Connector/SPF
  für externe Empfänger (Gmail), Compose-Datei nachziehen (alex/sudo), Crontab-Header kosmetisch.
- Dann Plan 2 und Plan 3 schreiben; offene Punkte in `TODO.md`.

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
- Nachtrag der Urlaube und Connector-Prüfung beim Betreiber; `sudo` auf zafe-server-1 nur mit Passwort.

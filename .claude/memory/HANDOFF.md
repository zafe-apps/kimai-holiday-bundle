# HANDOFF — WIP

> Stand 2026-10-29 (Plan 2 ausgerollt).

## Current goal
Plan 3 (Tagesverschiebung, Spec 4.4) schreiben und umsetzen; Plan 1 und 2 sind live auf
`zeiterfassung.zafe.at` (Plugin 2.1.0, Kimai 2.70.2).

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
- Plan 1 live (2.0.0): Mail-Relay, Feiertage, Verträge; Compose-Datei vollständig. **Plan 2 live (2.1.0,
  29.10.):** Standort Wien mit Öffnungszeiten und vier Mitgliedern, Aushilfe, Verfügbarkeitsansicht;
  Protokoll `docs/rollout/2026-10-29-plugin-2.1.0.md`. Issues #2 (Urlaubs-Nachtrag) und #3
  (Gmail-Zustellung) beim Betreiber offen.
- Nächstes: Plan 3 — Tagesverschiebung (`WorkdayShift`, Antrag/Genehmigung, Chokepoint
  `UserWorkContract::getExpectedSecondsForDate`, `PresenceKind::SHIFTED`, Zähler „offene Anträge"),
  dazu Zusammenführung der Duplikate `absenceSeconds`/Feiertags-Reduce; Kleinbefunde in `TODO.md`.
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
- Issues #2/#3 beim Betreiber; Sichtprüfung der Verfügbarkeit auf Prod durch den Betreiber (kein Prod-Login für Claude); `sudo` auf zafe-server-1 nur mit Passwort; Prod-Deploys brauchen die ausdrückliche Freigabe „mach den Deploy auf Prod" (Klassifizierer).

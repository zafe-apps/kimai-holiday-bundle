# Zeitnachträge mit Genehmigung Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Zeiteinträge, die mehr als 14 Tage nach ihrem Tag angelegt, geändert oder gelöscht werden, erzeugen einen Nachtrag, den ein Teamlead freigibt oder ablehnt; Teamleads erfahren davon per Mail.

**Architecture:** Ein Subscriber hört auf Kimais Timesheet-Pre-Ereignisse, hält den alten Stand fest und übergibt nach dem Flush an `TimesheetChangeService`, der Nachträge anlegt, zusammenführt, entscheidet und zurückstellt. Entscheidung und Liste hängen an der bestehenden Seite „Offene Anträge"; Mails gehen über `AbsenceNotifier`.

**Tech Stack:** PHP 8.2–8.4, Symfony/Doctrine in Kimai 2.70.2, PHPUnit ohne Kernel (`dev/test.sh`), Twig mit Kimai-Plugin-UI-Kit.

**Spec:** `docs/superpowers/specs/2026-11-14-zeitnachtrag-genehmigung-design.md`

## Global Constraints

- `CLAUDE.md` des Repos gilt: Schichten Controller → Service → Repository → Entity, Schema nur über neue Migration, Texte in `Resources/translations/*.{de,en}.xlf` mit gleichen Keys (`php dev/check-translations.php`), GUI nur mit Knust/Kit, Code-Stil (frühe Rückkehr, Enums statt Strings/Booleans, Funktionsnamen < 30 Zeichen).
- Commits deutsch, Imperativ, höchstens 50 Zeichen, mit `Co-Authored-By: Claude Fable 5.2 <noreply@anthropic.com>`.
- Recht: `approve_timesheet_change`. Einstellung: `holiday.timesheet_approval_days`, Standard `14`, `0` = aus.
- Tabelle: `kimai2_ext_holiday_timesheet_change`. Status über das bestehende `AbsenceStatus`.
- Tests: `dev/test.sh --filter <Name>`; kein Kernel, Abhängigkeiten als Mocks/Builder wie in `Tests/Service/WorkdayShiftServiceTest.php`.
- Nicht nach `master` mergen (Merge deployt auf Prod; macht der Betreiber).

## Review Focus

1. Schnelleingabe mit gemischten neuen und alten Zeilen: neue Zeilen (`id === null`) in `TimesheetUpdateMultiplePre` werden als `create` erkannt (Task 3).
2. Zweite Änderung am selben Eintrag bei offenem Nachtrag: `before` bleibt der erste Stand; zurück auf den Ausgangswert löscht den Nachtrag (Task 2).
3. Ablehnen eines `delete`, dessen Projekt oder Tätigkeit nicht mehr existiert: Meldung statt Ausnahme, Nachtrag bleibt offen (Task 4).
4. Rückstellung beim Ablehnen erzeugt keinen neuen Nachtrag und keine Mail (Task 4).
5. Eintrag über Mitternacht bzw. Benutzer in anderer Zeitzone: maßgeblich ist der Tag des Beginns in der Zeitzone der Person; genau am Grenztag ist noch frei (Task 1).

---

### Task 1: Datenmodell, Einstellung, Recht

**Files:**
- Create: `Enum/TimesheetChangeKind.php`, `Entity/TimesheetChange.php`, `Repository/TimesheetChangeRepository.php`, `Service/TimesheetSnapshot.php`, `Service/TimesheetChangePolicy.php`, `Migrations/Version20261114100000.php`, `Tests/Support/TimesheetChangeBuilder.php`
- Modify: `Service/HolidayConfiguration.php`, `EventSubscriber/SystemConfigurationSubscriber.php`, `DependencyInjection/HolidayExtension.php`, `EventSubscriber/PermissionSectionSubscriber.php`, `Resources/translations/messages.{de,en}.xlf`
- Test: `Tests/Service/TimesheetSnapshotTest.php`, `Tests/Service/TimesheetChangePolicyTest.php`

**Interfaces — Produces:**
- `enum TimesheetChangeKind: string { CREATE='create'; UPDATE='update'; DELETE='delete'; }` mit `label(): string` (`holiday.change.kind.<wert>`).
- `TimesheetChange`: `user`, `timesheetId: ?int` (schlichte Spalte, kein FK auf `kimai2_timesheet`; beim Löschen des Eintrags setzt der Service sie nicht zurück — das Abbild trägt alles), `day: DateTimeImmutable`, `kind`, `status: AbsenceStatus`, `before: ?array`, `after: ?array` (JSON), `requestedBy: ?User`, `createdAt`, `approvedBy: ?User`, `approvedAt`; Getter/Setter wie `WorkdayShift`. Indizes `(user_id, status)`, `(timesheet_id)`.
- `TimesheetSnapshot` (unveränderlich): `fromTimesheet(Timesheet): self`, `fromOriginal(array $originalData, Timesheet $current): self`, `fromArray(array): self`, `toArray(): array`, `sameTimes(self $other): bool` (Beginn, Ende, Dauer, Pause), `day(\DateTimeZone $tz): DateTimeImmutable`, öffentliche readonly-Felder `begin, end, duration, break, timezone, projectId, activityId, description, tags, billable`.
- `TimesheetChangePolicy`: `__construct(HolidayConfiguration)`; `isLate(\DateTimeInterface $begin, User $owner, \DateTimeImmutable $now): bool` — Tag des Beginns in der Zeitzone der Person `<` (heute − Frist); Frist 0 → immer `false`.
- `HolidayConfiguration::getTimesheetApprovalDays(): int` (Standard 14, nie negativ).
- `TimesheetChangeRepository`: `save`, `remove`, `findOpenForTimesheet(int $timesheetId): ?TimesheetChange`, `findRequestedForUsers(array $users): array`, `hasOpenInRange(User, DateTimeInterface $from, DateTimeInterface $to): bool`, `findOwnRecent(User, int $limit): array`.

- [ ] **Step 1:** Tests schreiben: `TimesheetChangePolicyTest` — heute 2026-11-14, Frist 14: Beginn 2026-10-31 → frei, 2026-10-30 → spät; Beginn `2026-10-30 23:30 UTC` bei Person in `Europe/Vienna` (= 31.10. 00:30) → frei; Frist 0 → frei. `TimesheetSnapshotTest` — `toArray`/`fromArray` Rundreise; `sameTimes` ignoriert Beschreibung/Projekt, erkennt Pause.
- [ ] **Step 2:** `dev/test.sh --filter 'TimesheetChangePolicy|TimesheetSnapshot'` → FAIL (Klassen fehlen).
- [ ] **Step 3:** Klassen, Migration (Muster `Version20261030100000`, FKs auf `kimai2_users`: `user_id` CASCADE, `requested_by_id`/`approved_by_id` SET NULL), Einstellung (IntegerType, min 0), Recht für `ROLE_TEAMLEAD`, `ROLE_ADMIN`, `ROLE_SUPER_ADMIN` und in `$permissions`, Übersetzungen.
- [ ] **Step 4:** Tests grün; `php dev/check-translations.php` ohne Befund.
- [ ] **Step 5:** Commit „Datenmodell für Zeitnachträge".

### Task 2: Nachträge anlegen und zusammenführen

**Files:**
- Create: `Service/TimesheetChangeService.php`, `Service/TimesheetChangeBatch.php`
- Modify: `Service/AbsenceNotifier.php`, `Resources/translations/messages.{de,en}.xlf`
- Test: `Tests/Service/TimesheetChangeServiceTest.php`, `Tests/Service/AbsenceNotifierTest.php`

**Interfaces:**
- Consumes: Task 1.
- Produces:
  - `TimesheetChangeService::PERMISSION = 'approve_timesheet_change'`
  - `record(TimesheetChangeKind $kind, User $owner, ?int $timesheetId, ?TimesheetSnapshot $before, ?TimesheetSnapshot $after, User $actor, \DateTimeImmutable $now): ?TimesheetChange` — `null`, wenn kein Nachtrag nötig oder der offene Nachtrag aufgelöst wurde.
  - `TimesheetChangeBatch` (je Request ein Sammler): `add(TimesheetChange, string $event)`, `flushMails()`; gruppiert nach Person und Ereignis (`requested|approved|rejected`).
  - `AbsenceNotifier::notifyTimesheetChanges(User $owner, string $event, array $changes, ?User $actor): void` — eine Mail je Aufruf, Zeile je Nachtrag „Tag · Art · vorher → nachher"; Empfänger `recipients($owner, TimesheetChangeService::PERMISSION)`; Keys `holiday.mail.change_<event>.subject|body`.

Regeln von `record` (in dieser Reihenfolge):
1. Nicht spät (`isLate` für `before`-Beginn und `after`-Beginn beide falsch) → `null`.
2. `UPDATE` mit `before->sameTimes(after)` → `null`.
3. Offener Nachtrag zum selben `timesheetId`: `UPDATE` ersetzt `after`; gleicht `after` jetzt dem gespeicherten `before` → Nachtrag löschen, `null`. `DELETE` auf offenen `CREATE` → Nachtrag löschen, `null`. `DELETE` auf offenen `UPDATE` → Art `DELETE`, `after = null`, `before` bleibt. Keine neue Mail.
4. Sonst neuer Nachtrag. `actor` ist Genehmiger von `owner` (`ApproverResolver::isApproverOf` mit `PERMISSION`) → `APPROVED`, `approvedBy = actor`, Batch-Ereignis `approved`. `approversFor(owner, PERMISSION) === []` → `APPROVED` ohne `approvedBy`, keine Mail. Sonst `REQUESTED`, Batch-Ereignis `requested`.

- [ ] **Step 1:** Tests schreiben, je Regel einer; dazu Review Focus 2 (zweite Änderung behält erstes `before`; Rückkehr zum Ausgangswert löscht) und „Teamlead ändert eigene alte Zeit, anderer Teamlead existiert → `REQUESTED`". Notifier-Test: zwei Nachträge einer Person → genau eine Mail mit zwei Zeilen, nicht an die Person selbst.
- [ ] **Step 2:** `dev/test.sh --filter 'TimesheetChangeService|AbsenceNotifier'` → FAIL.
- [ ] **Step 3:** Implementieren.
- [ ] **Step 4:** Tests grün.
- [ ] **Step 5:** Commit „Zeitnachträge anlegen und zusammenführen".

### Task 3: An Kimais Zeiteinträge andocken

**Files:**
- Create: `EventSubscriber/TimesheetChangeSubscriber.php`, `Service/AbsenceTimesheetMarker.php`
- Modify: `Service/AbsenceTimesheetService.php` (Erkennung eines einzelnen Eintrags nach `AbsenceTimesheetMarker` herausziehen, Verhalten unverändert), `Resources/translations/flashmessages.{de,en}.xlf`
- Test: `Tests/EventSubscriber/TimesheetChangeSubscriberTest.php`, `Tests/Service/AbsenceTimesheetMarkerTest.php`

**Interfaces:**
- Consumes: `TimesheetChangeService::record`, `TimesheetChangeBatch`, `TimesheetSnapshot`.
- Produces:
  - `AbsenceTimesheetMarker::isAbsenceEntry(Timesheet): bool` — Meta `holiday_absence_id` gesetzt, oder Beschreibung passt auf `/^Absence \(#(\d+)\)$/` und Projekt/Tätigkeit sind die konfigurierten.
  - `TimesheetChangeSubscriber::suspend(callable $work): mixed` — führt `$work` aus, ohne Nachträge aufzuzeichnen (für die Rückstellung in Task 4).

Ablauf: abonniert `TimesheetCreatePreEvent`, `TimesheetUpdatePreEvent`, `TimesheetStopPreEvent`, `TimesheetDeletePreEvent`, `TimesheetUpdateMultiplePreEvent`, `TimesheetDeleteMultiplePreEvent` und merkt je Eintrag Art, Besitzer und `before` (bei bestehender `id` aus `UnitOfWork::getOriginalEntityData`, bei `id === null` Art `CREATE`). Zusätzlich Doctrine-`postFlush`: für jeden gemerkten Eintrag `after` aus dem jetzt endgültigen Eintrag bilden, `record` aufrufen, Merkliste leeren, eigene Nachträge mit einem zweiten Flush speichern (Wiedereintritt per Flag sperren). `kernel.terminate` und `console.terminate`: `TimesheetChangeBatch::flushMails()`. Übersprungen wird: kein angemeldeter Benutzer (`Security::getUser()` kein `User`), `suspend` aktiv, `isAbsenceEntry`, laufender Eintrag ohne Ende bei `CREATE`. Entstand in dem Request ein `REQUESTED`-Nachtrag und gibt es eine Session, Flash `warning` mit `holiday.change.needs_approval`.

- [ ] **Step 1:** Tests mit gemockter `UnitOfWork`/`Security`: Update-Ereignis mit alten Werten ruft `record(UPDATE, …)` erst nach `postFlush`; `UpdateMultiple` mit einer Zeile `id === null` → `CREATE` (Review Focus 1); ohne Benutzer kein Aufruf; Abwesenheits-Eintrag kein Aufruf; innerhalb `suspend` kein Aufruf; Marker-Test für Meta und Altbestand.
- [ ] **Step 2:** Tests rot laufen lassen.
- [ ] **Step 3:** Implementieren.
- [ ] **Step 4:** Tests grün; im Dev-Stack (`dev/kimai-dev.sh`, Migration über `kimai:bundle:holiday:install`) als Benutzer ohne Teamlead-Rolle einen Eintrag vor 20 Tagen ändern → Zeile in `kimai2_ext_holiday_timesheet_change` mit `requested`, Hinweis sichtbar; Eintrag von gestern ändern → keine Zeile.
- [ ] **Step 5:** Commit „Zeitänderungen als Nachtrag aufzeichnen".

### Task 4: Entscheiden und zurückstellen

**Files:**
- Modify: `Service/TimesheetChangeService.php`, `Service/MonthLockService.php`, `Controller/ContractController.php`, `Resources/translations/flashmessages.{de,en}.xlf`
- Create: `Service/TimesheetChangePermissions.php`
- Test: `Tests/Service/TimesheetChangeServiceTest.php`, `Tests/Service/MonthLockServiceTest.php`, `Tests/Service/TimesheetChangePermissionsTest.php`

**Interfaces — Produces:**
- `approve(TimesheetChange, User $approver): void`, `reject(TimesheetChange, User $approver): void` — beide werfen `\InvalidArgumentException` mit Flash-Key (`holiday.error.change_own`, `…change_not_open`, `…change_exported`, `…month_locked`, `…change_target_missing`); legen den Nachtrag mit Ereignis `approved|rejected` in den Batch.
- `TimesheetChangePermissions::canDecide(User $actor, TimesheetChange): bool` (Genehmiger der Person, nicht die Person selbst, nicht `requestedBy`).
- `MonthLockService::lock` wirft `\RuntimeException('holiday.error.month_open_changes')`, wenn `hasOpenInRange(user, 1.1. des Jahres, letzter Tag des Monats)`; `ContractController::lockMonth` zeigt das als Flash-Fehler.

Rückstellung in `reject`, immer innerhalb `TimesheetChangeSubscriber::suspend` und über Kimais `TimesheetService`: `UPDATE` → Felder aus `before` setzen, `updateTimesheet`; `CREATE` → `deleteTimesheet`; `DELETE` → neuen `Timesheet` aus `before` bauen und speichern, `timesheetId` des Nachtrags auf die neue Id. Vorher prüfen: Eintrag exportiert → Fehler; `PeriodLockGuard::assertOpen` für alten und neuen Tag; Projekt/Tätigkeit aus `before` nicht mehr vorhanden → Fehler; Eintrag bei `UPDATE`/`CREATE` nicht mehr vorhanden → Nachtrag als abgelehnt schließen, nichts zurückstellen.

- [ ] **Step 1:** Tests: je Art eine Rückstellung; eigener Nachtrag → Fehler; exportiert → Fehler und Status bleibt `REQUESTED`; fehlendes Projekt → Fehler (Review Focus 3); `reject` ruft `record` nicht auf und erzeugt genau ein Batch-Ereignis `rejected` (Review Focus 4); Monatssperre mit offenem Nachtrag im Vormonat → Ausnahme, ohne → sperrt.
- [ ] **Step 2:** Tests rot.
- [ ] **Step 3:** Implementieren.
- [ ] **Step 4:** Tests grün.
- [ ] **Step 5:** Commit „Zeitnachträge freigeben und ablehnen".

### Task 5: Oberfläche „Offene Anträge"

**Files:**
- Create: `Controller/TimesheetChangeController.php`
- Modify: `Resources/views/absence/requests.html.twig`, der Controller, der diese Seite rendert (Daten für die dritte Tabelle), `Service/OpenRequestCounter.php`, `Resources/translations/messages.{de,en}.xlf`
- Test: `Tests/Service/OpenRequestCounterTest.php`

**Interfaces:**
- Consumes: `TimesheetChangeRepository::findRequestedForUsers`, `ApproverResolver::subjectsFor($approver, TimesheetChangeService::PERMISSION)`, `approve`/`reject`, `TimesheetChangePermissions::canDecide`.
- Produces: Routen `holiday_timesheet_change_approve`, `…_reject` (POST, `{id}`), `…_bulk` (POST, `ids[]`, `decision`), alle mit CSRF-Token und `#[IsGranted('approve_timesheet_change')]`, Muster `WorkdayShiftController`.

Tabelle „Zeitnachträge" unter den beiden bestehenden, nur Kit-Macros: Person · Tag · Art · Vorher · Nachher (je „HH:MM–HH:MM, Dauer", bei fehlender Seite „–") · Angelegt am · Aktionen; Sammelaktion wie bei Verschiebungen; leerer Zustand mit Kit-Macro. Sammel-Entscheidung meldet Erfolge und Fehler getrennt.

- [ ] **Step 1:** Zähler-Test erweitern: zwei offene Nachträge zählen mit.
- [ ] **Step 2:** Test rot.
- [ ] **Step 3:** Zähler, Controller, Twig, Übersetzungen.
- [ ] **Step 4:** Test grün; `php dev/check-translations.php`; im Dev-Stack als Teamlead: Tabelle zeigt den Nachtrag aus Task 3, Ablehnen stellt die Zeit zurück, Freigeben lässt sie stehen, Zähler im Menü stimmt; Screenshot der Seite bei Desktop- und schmaler Breite prüfen (`dev/screenshot.mjs`).
- [ ] **Step 5:** Commit „Zeitnachträge auf der Anträge-Seite".

### Task 6: Auslieferung

**Files:**
- Modify: `composer.json` (Version `2.6.0`), `CHANGELOG.md`, `README.md`, `dev/ci.sh` (Seite „Offene Anträge" bleibt 200 nach Migration), `TODO.md` (offen: API für Nachträge, eigene Liste der Person), `.claude/memory/HANDOFF.md`

- [ ] **Step 1:** Dateien nachziehen.
- [ ] **Step 2:** `dev/test.sh` (ganze Suite) grün, `php dev/check-translations.php`, PHP-Lint über geänderte Dateien, `dev/ci.sh` lokal, wenn der Stack es zulässt.
- [ ] **Step 3:** Commit „Version 2.6.0: Zeitnachträge", push, PR gegen `master` öffnen (nicht mergen).

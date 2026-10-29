# Plan 2: Standort, Aushilfe und Verfügbarkeitsansicht — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ein Standort mit Öffnungszeiten, eine Verfügbarkeitsansicht je Woche/Monat, die pro
Öffnungstag zeigt, wer da ist und welche Tage unbesetzt sind, und ein Aushilfe-Eintrag, mit dem sich
jemand für einen Tag zusätzlich einträgt (Spec 4.5–4.7). Release 2.1.0, Rollout auf
zeiterfassung.zafe.at.

**Architecture:** Neue Entities im bestehenden Präfix `kimai2_ext_holiday_*` (`Location`,
`LocationOpeningHours`, `LocationException`, `LocationUser`, `ExtraShift`). Die Fachlogik liegt in
zwei reinen Services mit Wertobjekten: `LocationSchedule` (ist ein Tag Öffnungstag?) und
`AvailabilityCalculator` (wer ist da?). „Arbeitstag der Person" bleibt der Chokepoint
`AbsenceWorkdayHelper::isAbsenceApplicableDay()` — Plan 3 (Tagesverschiebung) greift nur dort ein.
UI im Stil des Teamkalenders und der Feiertagsverwaltung: Attribute-Routen, Modal-Formulare,
Kit-Makros, Twig ohne eigene Farben.

**Tech Stack:** PHP 8.2+, Symfony 6 (Kimai 2.70.2), Doctrine-Attribute, Twig + Kit 0.7.1
(`Resources/views/_kit/`, unverändert), PHPUnit 10 über `dev/test.sh`, Docker-Dev-Stack
(`dev/kimai-dev.sh`, `admin`/`admin1234`).

**Spec:** `docs/superpowers/specs/2026-10-23-kimai-dienstplan-design.md`, Abschnitte 4.5, 4.6, 4.7,
Nicht-Ziele und 5. 4.4 (Tagesverschiebung) ist Plan 3; die Vermerke „verschoben von" und
Verschiebungen als „offen" kommen erst dort.

## Global Constraints

- Tabellenpräfix `kimai2_ext_holiday_`, Migrationstabelle `bundle_migration_holiday`, Migrationen
  nach dem Muster `Migrations/Version20261023100000.php` (`HolidayBundle\Migrations`,
  `App\Doctrine\AbstractMigration`, `hasTable`-Guards, `preventEmptyMigrationWarning`); veröffentlichte
  Migrationen werden nie editiert. Neue Migration: `Version20261024100000`.
- Neue Rechte genau: `view_location`, `edit_location`, `view_availability`, `extra_shift_own`,
  `extra_shift_other`. Rollen-Defaults in `DependencyInjection/HolidayExtension.php` (jede Rolle
  explizit, auch `ROLE_SUPER_ADMIN`): USER `view_availability`, `extra_shift_own`; TEAMLEAD
  zusätzlich `view_location`, `extra_shift_other`; ADMIN und SUPER_ADMIN alles.
  `PermissionSectionSubscriber` bekommt die Filter `location`, `availability`, `shift`.
- Übersetzungen in `Resources/translations/messages.{de,en}.xlf` (nächste ID `h187`) und
  `flashmessages.{de,en}.xlf` (nächste ID `h101`), gleiche Schlüssel, eindeutige IDs;
  `php dev/check-translations.php` im Container muss grün sein.
- Kit `Resources/views/_kit/` unverändert; keine eigenen Farben, Pixelwerte, Fonts; Tagesraster über
  `.kpu-days` + `kit.calendar_day(..., {content})` oder `.kpu-hint-cards` + `kit.hint_card`.
- Routen als Attribute unter `/holiday/...`, Namen `holiday_*`; Controller über `services.yaml`
  (Verzeichnis-Scan) registriert; Modale wie `PublicHolidayController::createModalForm/renderModalForm`
  mit `HolidayUiTrait::formSuccess`; POST-Aktionen mit CSRF (`data-kpu-post`/`data-kpu-token`).
- Tests zuerst (TDD) für jede Verhaltensänderung; Testhelfer in `Tests/Support/`; deutsche
  Testnamen/Kommentare mit konkreten Daten.
- Commit-Messages: Imperativ, deutsch, Betreff ≤ 50 Zeichen, Body erklärt das Warum, Trailer
  `Co-Authored-By: Claude Fable 5.1 <noreply@anthropic.com>`.
- Version `2.1.0` in `composer.json` und `CHANGELOG.md` (Task 7).
- Standort Wien zur Abnahme: Mi–Fr 14:00–21:00, Sa 12:00–21:00, Mo/Di/So geschlossen,
  Feiertagsgruppe „Österreich".

## Review Focus

1. **Mitglied ohne Vertrag am Öffnungstag** (z. B. ein Chef ohne Arbeitszeiten): erwartet — nicht
   anwesend, kein Fehler, erscheint nur bei Aushilfe oder Abwesenheit. Test in Task 5
   (`AvailabilityCalculatorTest::testMemberWithoutContractIsNotPresent`).
2. **Feiertagsgruppe des Standorts ≠ Gruppe der Person:** Standort per Ausnahme am Feiertag offen,
   Person hat laut eigener Gruppe frei — erwartet: Tag ist Öffnungstag, Person nicht anwesend,
   Tag „unbesetzt", Aushilfe macht sie anwesend. Test in Task 5
   (`AvailabilityCalculatorTest::testOpenExceptionOnHolidayLeavesDayUnstaffedUntilExtraShift`).
3. **Halbe Abwesenheit:** anwesend mit Hinweis „halber Tag", nicht als abwesend. Test in Task 5
   (`AvailabilityCalculatorTest::testHalfDayAbsenceCountsAsPresentWithHint`).
4. **Woche über Jahresgrenze** (ISO-Woche 53/2026 → 1/2027): Navigation und Tagesliste korrekt,
   kein Doppel- oder Fehltag. Test in Task 6 (`AvailabilityPeriodTest::testWeekAcrossYearBoundary`).
5. **Feiertagsgruppe löschen, die ein Standort nutzt:** erwartet — verständliche Fehlermeldung,
   keine 500 (FK RESTRICT). Test in Task 3 über den Smoke (Löschversuch) und
   `LocationRepositoryTest` entfällt; stattdessen Flash `holiday.error.group_in_use` im
   `PublicHolidayController`-Delete-Pfad, Smoke-Nachweis im Report.

---

## Dateistruktur

**Neu**

- `Entity/Location.php`, `Entity/LocationOpeningHours.php`, `Entity/LocationException.php`,
  `Entity/LocationUser.php`, `Entity/ExtraShift.php`
- `Enum/LocationExceptionKind.php` (`open`, `closed`), `Enum/PresenceKind.php` (`fixed`,
  `extra_shift`; Plan 3 ergänzt `shifted`)
- `Repository/LocationRepository.php`, `Repository/LocationUserRepository.php`,
  `Repository/LocationExceptionRepository.php`, `Repository/ExtraShiftRepository.php`
- `Migrations/Version20261024100000.php`
- `Service/OpeningSlot.php`, `Service/LocationSchedule.php`
- `Service/PresenceEntry.php`, `Service/AvailabilityDay.php`, `Service/AvailabilityCalculator.php`,
  `Service/AvailabilityPeriod.php` (Woche/Monat → Datumsliste, Navigation)
- `Service/ExtraShiftService.php`, `Service/ExtraShiftPermissions.php`,
  `Service/LocationPermissions.php`
- `Form/LocationType.php`, `Form/LocationOpeningHoursType.php`, `Form/LocationExceptionType.php`,
  `Form/LocationMemberType.php`, `Form/ExtraShiftType.php`
- `Controller/LocationController.php`, `Controller/ExtraShiftController.php`,
  `Controller/AvailabilityController.php`
- `EventSubscriber/Actions/LocationPageSubscriber.php`, `LocationRowSubscriber.php`,
  `AvailabilityPageSubscriber.php`
- `Resources/views/location/index.html.twig`, `location/form.html.twig`,
  `location/exception_form.html.twig`, `location/member_form.html.twig`,
  `availability/index.html.twig`, `extra_shift/form.html.twig`
- `Tests/Support/LocationBuilder.php`, `Tests/Support/ExtraShiftBuilder.php`,
  `Tests/Service/LocationScheduleTest.php`, `AvailabilityCalculatorTest.php`,
  `AvailabilityPeriodTest.php`, `ExtraShiftServiceTest.php`, `LocationPermissionsTest.php`

**Geändert**

- `DependencyInjection/HolidayExtension.php`, `EventSubscriber/PermissionSectionSubscriber.php`,
  `EventSubscriber/MenuSubscriber.php`, `Controller/AbsenceCalendarController.php` (Aushilfe-Symbol),
  `Resources/views/report/absence_calendar.html.twig` (Legende), `Controller/PublicHolidayController.php`
  (Gruppe in Verwendung), `dev/ci.sh` (neue Seiten), `README.md`, `CHANGELOG.md`, `composer.json`,
  `TODO.md`, Übersetzungen.

---

### Task 1: Datenmodell, Migration, Rechte

**Files:**
- Create: die fünf Entities, zwei Enums, vier Repositories, `Migrations/Version20261024100000.php`,
  `Tests/Support/LocationBuilder.php`, `Tests/Support/ExtraShiftBuilder.php`,
  `Tests/Entity/LocationTest.php`
- Modify: `DependencyInjection/HolidayExtension.php`, `EventSubscriber/PermissionSectionSubscriber.php`

**Interfaces:**
- Produces:
  - `Location`: `id`, `name` (string 100), `publicHolidayGroup` (ManyToOne `PublicHolidayGroup`,
    nullable false, `onDelete: RESTRICT`), `active` (bool, default true), `openingHours`
    (OneToMany `LocationOpeningHours`, cascade persist/remove, orphanRemoval), `exceptions`
    (OneToMany `LocationException`, cascade, orphanRemoval). Methoden: `getOpeningHoursFor(int $isoWeekday): ?LocationOpeningHours`,
    `setOpeningHours(iterable)`; Fluent-Setter; `__toString()` = Name.
  - `LocationOpeningHours`: `location`, `weekday` (SMALLINT 1–7 ISO, Mo = 1), `opensAt`,
    `closesAt` (`TIME_IMMUTABLE`); Unique `(location_id, weekday)`.
  - `LocationException`: `location`, `date` (DATE_IMMUTABLE), `kind` (`LocationExceptionKind`),
    `opensAt`/`closesAt` nullable, `comment` nullable; Unique `(location_id, date)`.
  - `LocationUser`: PK `user_id` (ManyToOne User, CASCADE), `location` (ManyToOne, CASCADE) — ein
    Standort je Nutzer.
  - `ExtraShift`: `id`, `user` (CASCADE), `date` (DATE_IMMUTABLE), `comment` nullable,
    `createdBy` (SET NULL), `createdAt`; Unique `(user_id, date)`.
  - `LocationRepository::findActiveOrdered(): Location[]`, `findAllOrdered()`, `save`, `remove`.
  - `LocationUserRepository::findUsersOf(Location): User[]` (nur aktivierte Nutzer),
    `findLocationOf(User): ?Location`, `assign(User, Location)`, `unassign(User)`.
  - `LocationExceptionRepository::findBetween(Location, \DateTimeInterface, \DateTimeInterface): LocationException[]`.
  - `ExtraShiftRepository::findForUsersBetween(array $users, \DateTimeInterface, \DateTimeInterface): ExtraShift[]`,
    `findOneByUserAndDate(User, \DateTimeInterface): ?ExtraShift`, `save`, `remove`.
  - `LocationBuilder::make(): self`, `named(string)`, `holidayGroup(PublicHolidayGroup)`,
    `open(int $isoWeekday, string $from, string $to)` (`'14:00'`), `closedOn(string $ymd, ?string $comment = null)`,
    `openOn(string $ymd, string $from, string $to)`, `id(int)`, `build(): Location`;
    `LocationBuilder::vienna(PublicHolidayGroup $group): Location` (Mi–Fr 14–21, Sa 12–21).
  - `ExtraShiftBuilder::on(User $user, string $ymd, ?User $createdBy = null): ExtraShift`.

- [ ] **Step 1: Failing Tests (Entity-Logik, ohne DB)**

```php
final class LocationTest extends TestCase
{
    public function testOpeningHoursForWeekday(): void
    {
        $loc = LocationBuilder::make()->open(3, '14:00', '21:00')->open(6, '12:00', '21:00')->build();
        self::assertSame('12:00', $loc->getOpeningHoursFor(6)?->getOpensAt()->format('H:i'));
        self::assertNull($loc->getOpeningHoursFor(1));
    }

    public function testSetOpeningHoursReplacesAll(): void
    {
        // zweimal setzen → nur der zweite Satz bleibt (orphanRemoval auf Collection-Ebene)
    }
}
```

- [ ] **Step 2: Fail prüfen** — `dev/test.sh --filter LocationTest` → Klassen fehlen.

- [ ] **Step 3: Entities, Enums, Repositories, Migration, Rechte implementieren**

Migration legt fünf Tabellen mit Guards an: `kimai2_ext_holiday_location` (id, name, public_holiday_group_id FK RESTRICT `FK_HOLIDAY_LOCATION_GROUP`, active), `…_location_opening_hours` (id, location_id FK CASCADE, weekday SMALLINT, opens_at TIME, closes_at TIME, Unique `uniq_holiday_location_weekday`), `…_location_exception` (id, location_id FK CASCADE, exception_date DATE, kind VARCHAR(16), opens_at/closes_at TIME NULL, comment TEXT NULL, Unique `uniq_holiday_location_date`), `…_location_user` (user_id PK FK CASCADE `kimai2_users`, location_id FK CASCADE), `…_extra_shift` (id, user_id FK CASCADE, shift_date DATE, comment TEXT NULL, created_by_id FK SET NULL, created_at DATETIME, Unique `uniq_holiday_extra_shift_user_date`). `down()` löscht in umgekehrter Reihenfolge. Rechte in `HolidayExtension` je Rolle laut Global Constraints; `PermissionSectionSubscriber` um `location`, `availability`, `shift`.

- [ ] **Step 4: Tests grün, Migration auf dem Dev-Stack**

`dev/test.sh` → PASS. `dev/kimai-dev.sh`, dann im Container `bin/console kimai:bundle:holiday:install`
und `bin/console doctrine:schema:validate --skip-sync` ohne Mapping-Fehler; `SHOW TABLES LIKE
'kimai2_ext_holiday_%'` zeigt die fünf neuen Tabellen. `bin/console debug:container
HolidayBundle --tag=controller.service_arguments` unverändert (keine Controller in diesem Task).

- [ ] **Step 5: Commit** — `Standort-, Aushilfe-Datenmodell und Rechte anlegen`

---

### Task 2: Öffnungstag-Regel (`LocationSchedule`)

**Files:**
- Create: `Service/OpeningSlot.php`, `Service/LocationSchedule.php`
- Test: `Tests/Service/LocationScheduleTest.php`

**Interfaces:**
- Consumes: `Location`, `LocationExceptionRepository::findBetween`,
  `PublicHolidayRepository::findByGroupBetween(PublicHolidayGroup, from, to)`.
- Produces:
  - `final class OpeningSlot { public function __construct(public readonly \DateTimeImmutable $date, public readonly ?\DateTimeImmutable $opensAt, public readonly ?\DateTimeImmutable $closesAt, public readonly bool $open, public readonly ?string $reason /* 'exception_open'|'exception_closed'|'public_holiday'|'weekly'|'inactive' */, public readonly ?string $holidayName) {} public function label(): string /* "14:00–21:00" | "" */ }`
  - `LocationSchedule::slotsBetween(Location $location, \DateTimeImmutable $from, \DateTimeImmutable $to): array` —
    Schlüssel `Y-m-d`, Wert `OpeningSlot`; lädt Ausnahmen und Feiertage je Aufruf **einmal**.
  - `LocationSchedule::slotFor(Location, \DateTimeImmutable): OpeningSlot` (Komfort, intern `slotsBetween`).
  - Regel (Reihenfolge): Standort inaktiv → zu (`inactive`); Ausnahme am Datum → deren `kind` (Zeiten
    aus der Ausnahme, sonst aus dem Wochenmuster); ganztägiger Feiertag der **Standort**-Gruppe → zu
    (`public_holiday`, Name); Wochenmuster → offen mit Zeiten oder zu (`weekly`).

- [ ] **Step 1: Failing Tests**

```php
public function testViennaWeekPattern(): void          // Mi 28.10.2026 offen 14:00–21:00, Sa 31.10. 12:00–21:00, Mo 26.10. zu (weekly)
public function testNationalHolidayClosesLocation(): void // Mo 26.10.2026 Nationalfeiertag — ohnehin zu; Do 08.12.2026 (Mariä Empfängnis, Di? → nimm Fr 01.05.2026 Staatsfeiertag) zu, reason public_holiday, holidayName
public function testOpenExceptionOverridesHoliday(): void // Ausnahme open 01.05.2026 12:00–18:00 → offen, Zeiten aus Ausnahme
public function testClosedExceptionOnRegularDay(): void   // Ausnahme closed Sa 07.11.2026 → zu, reason exception_closed
public function testInactiveLocationIsAlwaysClosed(): void
public function testSlotsBetweenLoadsExceptionsOnce(): void // Repository-Spy: findBetween genau 1×, findByGroupBetween genau 1× für 31 Tage
```
Feiertags-Stub: `findByGroupBetween` liefert `PublicHoliday` 01.05.2026 „Staatsfeiertag" (ganztägig).

- [ ] **Step 2: Fail prüfen** — `dev/test.sh --filter LocationScheduleTest`.
- [ ] **Step 3: Implementieren** — `DateTimeImmutable` durchgehend; Wochentag per `format('N')`.
- [ ] **Step 4: Tests grün** — `dev/test.sh`.
- [ ] **Step 5: Commit** — `Öffnungstag je Standort berechnen`

---

### Task 3: Standortverwaltung (UI)

**Files:**
- Create: `Service/LocationPermissions.php`, `Form/LocationType.php`, `Form/LocationOpeningHoursType.php`,
  `Form/LocationExceptionType.php`, `Form/LocationMemberType.php`, `Controller/LocationController.php`,
  `EventSubscriber/Actions/LocationPageSubscriber.php`, `LocationRowSubscriber.php`,
  `Resources/views/location/{index,form,exception_form,member_form}.html.twig`
- Modify: `EventSubscriber/MenuSubscriber.php` (Admin-Menü „Standorte" hinter `edit_location`),
  `Controller/PublicHolidayController.php` (Gruppe löschen → `holiday.error.group_in_use`, wenn ein
  Standort sie nutzt), `dev/ci.sh` (Seite `/en/holiday/locations` 200 mit Text `locations/create`),
  Übersetzungen
- Test: `Tests/Service/LocationPermissionsTest.php`

**Interfaces:**
- Consumes: Task-1-Entities/Repositories.
- Produces:
  - `LocationPermissions` (Muster `AbsencePermissions`): `canView(): bool` (`view_location` oder
    `edit_location`), `canEdit(): bool`, `canViewAvailabilityOf(Location $location): bool`
    (`view_location`/`edit_location` → alle; sonst `view_availability` und eigener Standort laut
    `LocationUserRepository::findLocationOf($currentUser)`), `visibleLocations(): Location[]`.
  - Routen (`#[Route('/holiday/locations')]`): `holiday_locations` (Index, `view_location`),
    `holiday_location_create` / `holiday_location_edit` / `holiday_location_delete`
    (`edit_location`), `holiday_location_exception_create` / `_delete`, `holiday_location_member_add`
    / `_remove`.
  - Formular `LocationType`: `name`, `publicHolidayGroup` (EntityType), `active`, `openingHours`
    als `CollectionType` von `LocationOpeningHoursType` mit genau 7 Zeilen (Mo–So; `TimePickerType`
    für `opensAt`/`closesAt`, beide leer = geschlossen; Validierung `closesAt > opensAt`).
  - `LocationExceptionType`: `date` (DatePickerType), `kind` (ChoiceType aus Enum), `opensAt`/`closesAt`
    (optional, nur bei `open`), `comment`.
  - `LocationMemberType`: `user` (Kimais `UserType`, nur aktivierte Nutzer, ohne bereits Zugeordnete).

- [ ] **Step 1: Failing Test (Permissions, ohne Kernel)**

```php
public function testUserWithOnlyViewAvailabilitySeesOwnLocationOnly(): void
public function testEditLocationSeesAllLocations(): void
public function testUserWithoutLocationSeesNothing(): void
```
`Security` per Stub (`isGranted` je Recht), `LocationUserRepository`/`LocationRepository` per Stub.

- [ ] **Step 2: Fail prüfen.**

- [ ] **Step 3: Implementieren**

Index-Seite: Tabelle (Name, Feiertagsgruppe, Öffnungszeiten als Kurzform „Mi–Fr 14–21 · Sa 12–21",
Mitgliederzahl, aktiv) mit Zeilenaktionen Bearbeiten/Löschen; Detailbereich je Standort als
`kit.fold`: Ausnahmen (Datum, offen/zu, Zeiten, Kommentar, Löschen) und Mitglieder (Nutzer, Entfernen),
jeweils mit „Hinzufügen"-Modal. Seitenaktion „Standort anlegen" (`modal-ajax-form`). Löschen eines
Standorts mit CSRF-POST, entfernt Mitglieder/Öffnungszeiten/Ausnahmen (CASCADE), lässt Aushilfen und
Abwesenheiten unberührt. `PublicHolidayController` Delete-Pfad: vorher `LocationRepository`
fragen; bei Verwendung Flash `holiday.error.group_in_use` statt 500.

- [ ] **Step 4: Tests grün, Smoke**

`dev/test.sh`, `check-translations`, `lint:twig`. Dev-Stack: Standort „Wien" mit Gruppe
„Österreich" anlegen (Mi–Fr 14–21, Sa 12–21), Ausnahme „geschlossen" an einem Samstag, zwei
Mitglieder; Gruppe „Österreich" löschen versuchen → Flash, kein 500; curl-Nachweise im Report.

- [ ] **Step 5: Commit** — `Standorte mit Öffnungszeiten verwalten`

---

### Task 4: Aushilfe

**Files:**
- Create: `Service/ExtraShiftPermissions.php`, `Service/ExtraShiftService.php`,
  `Form/ExtraShiftType.php`, `Controller/ExtraShiftController.php`,
  `Resources/views/extra_shift/form.html.twig`
- Modify: `Controller/AbsenceCalendarController.php` (Aushilfe als Zelleintrag Typ `extra_shift`,
  Icon `plus`), `Resources/views/report/absence_calendar.html.twig` (Legende),
  Übersetzungen (`holiday.extra_shift.*`, Fehler `holiday.error.extra_shift_on_workday`,
  `holiday.error.extra_shift_absent`, `holiday.error.extra_shift_closed`, `holiday.error.extra_shift_exists`)
- Test: `Tests/Service/ExtraShiftServiceTest.php`

**Interfaces:**
- Consumes: `AbsenceWorkdayHelper::isAbsenceApplicableDay(User, date)`,
  `AbsenceRepository::findApprovedBetween(User, from, to)`, `ExtraShiftRepository`,
  `LocationUserRepository::findLocationOf`, `LocationSchedule::slotFor`.
- Produces:
  - `ExtraShiftPermissions::canCreateFor(?User): bool` (`extra_shift_own` für sich,
    `extra_shift_other` + `canAccessUser` für andere), `canDelete(ExtraShift): bool` (Ersteller,
    Betroffener mit `extra_shift_own`, oder `extra_shift_other`).
  - `ExtraShiftService::create(User $user, \DateTimeImmutable $date, ?string $comment, User $actor): ExtraShift`
    — Prüfungen in dieser Reihenfolge, Fehler als `\InvalidArgumentException('holiday.error.…')`:
    `extra_shift_exists` (Unique), `extra_shift_on_workday` (wenn `isAbsenceApplicableDay` true),
    `extra_shift_absent` (genehmigte Abwesenheit deckt den Tag), `extra_shift_closed` (Nutzer hat
    einen Standort und `slotFor` ist nicht offen; ohne Standort keine Prüfung). *(Controller-Entscheidung:
    Aushilfe nur an Öffnungstagen des eigenen Standorts, analog zur Verschiebungsregel der Spec.)*
  - `ExtraShiftService::delete(ExtraShift): void`.
  - Routen (`#[Route('/holiday/extra-shifts')]`): `holiday_extra_shift_create` (Modal; Felder
    `user` nur mit `extra_shift_other`, sonst fest der eigene; `date`, `comment`),
    `holiday_extra_shift_delete` (POST, CSRF).

- [ ] **Step 1: Failing Tests**

```php
public function testCreateOnFreeOpenDay(): void                 // Moritz (Fr+Sa) am Do 29.10.2026, Wien offen → angelegt, createdBy = actor
public function testRejectsOnContractWorkday(): void            // Moritz am Fr 30.10. → extra_shift_on_workday
public function testRejectsWhenApprovedAbsenceCoversDay(): void // Urlaub 29.10. genehmigt → extra_shift_absent
public function testRejectsOnClosedDayOfOwnLocation(): void     // Mo 02.11. Wien zu → extra_shift_closed
public function testAllowsWithoutLocation(): void               // Nutzer ohne Standort, Montag → angelegt
public function testRejectsDuplicate(): void                    // findOneByUserAndDate liefert Treffer → extra_shift_exists
```

- [ ] **Step 2: Fail prüfen.** — [ ] **Step 3: Implementieren.** — [ ] **Step 4: Tests grün, Smoke**
(Dev-Stack: als Mitarbeiter Aushilfe für sich anlegen → erscheint im Teamkalender mit Plus-Symbol;
Versuch an einem Vertragstag → Flash). — [ ] **Step 5: Commit** — `Aushilfe-Eintrag für einen Tag`

---

### Task 5: Anwesenheitsberechnung (`AvailabilityCalculator`)

**Files:**
- Create: `Service/PresenceEntry.php`, `Service/AvailabilityDay.php`, `Service/AvailabilityCalculator.php`
- Test: `Tests/Service/AvailabilityCalculatorTest.php`

**Interfaces:**
- Consumes: `LocationSchedule::slotsBetween`, `LocationUserRepository::findUsersOf`,
  `AbsenceWorkdayHelper::isAbsenceApplicableDay`, `AbsenceRepository::findVisibleForUsersBetween`
  (genehmigt + beantragt), `ExtraShiftRepository::findForUsersBetween`.
- Produces:
  - `final class PresenceEntry { public function __construct(public readonly User $user, public readonly PresenceKind $kind, public readonly bool $halfDay, public readonly ?ExtraShift $extraShift = null) {} }`
  - `final class AvailabilityDay { public function __construct(public readonly \DateTimeImmutable $date, public readonly OpeningSlot $slot, /** @var PresenceEntry[] */ public readonly array $present, /** @var array<array{user: User, absence: Absence}> */ public readonly array $absent, /** @var array<array{user: User, absence: Absence}> */ public readonly array $pending) {} public function isOpen(): bool; public function isUnstaffed(): bool /* offen && present === [] */ }`
  - `AvailabilityCalculator::forPeriod(Location $location, \DateTimeImmutable $from, \DateTimeImmutable $to): array` —
    Schlüssel `Y-m-d`, Wert `AvailabilityDay`, für **jeden** Kalendertag (auch geschlossene; die
    Ansicht filtert). Regel je Mitglied und Tag: genehmigte ganztägige Abwesenheit → `absent`;
    genehmigte halbe → `present` mit `halfDay` (Kind nach Grundlage); sonst `isAbsenceApplicableDay`
    → `present fixed`; sonst Aushilfe → `present extra_shift`; beantragte Abwesenheit, die den Tag
    deckt → zusätzlich `pending` (Person bleibt in `present`, wenn sie es ohne den Antrag wäre).
    Reihenfolge in `present`: fix vor Aushilfe, dann Anzeigename. Alle Repository-Zugriffe einmal je
    Aufruf, keine Abfrage je Tag.

- [ ] **Step 1: Failing Tests** (Standort Wien, Gruppe mit Feiertag 01.05.2026; Manuel Mi–Sa,
Moritz Fr+Sa, Chef ohne Vertrag; Zeitraum 26.10.–01.11.2026)

```php
public function testFixedPresenceFollowsContract(): void       // Mi 28.10.: Manuel fixed; Fr 30.10.: Manuel + Moritz
public function testClosedDaysHaveNoPresence(): void           // Mo 26.10. zu (Nationalfeiertag + weekly) → present [], isOpen false, isUnstaffed false
public function testApprovedAbsenceMovesToAbsent(): void       // Manuel Urlaub 28.–29.10. → Mi/Do absent, Fr present
public function testUnstaffedWhenNobodyLeft(): void            // Manuel Urlaub Mi 28.10. → Mi unstaffed true
public function testHalfDayAbsenceCountsAsPresentWithHint(): void
public function testExtraShiftMakesPresent(): void             // Moritz Aushilfe Do 29.10. → present extra_shift
public function testPendingRequestListedButStillPresent(): void
public function testMemberWithoutContractIsNotPresent(): void  // Chef: nie present, taucht bei Aushilfe auf
public function testOpenExceptionOnHolidayLeavesDayUnstaffedUntilExtraShift(): void // Fr 01.05.2026 Ausnahme offen; Manuels Gruppe hat Feiertag → unstaffed; Aushilfe Manuel → present
public function testRepositoriesQueriedOncePerPeriod(): void
```

- [ ] **Step 2: Fail prüfen.** — [ ] **Step 3: Implementieren** (`DateTimeImmutable`, Vergleich über
`Y-m-d`, keine Abfrage in Schleifen). — [ ] **Step 4: Tests grün.** — [ ] **Step 5: Commit** —
`Anwesenheit je Öffnungstag berechnen`

---

### Task 6: Verfügbarkeitsansicht

**Files:**
- Create: `Service/AvailabilityPeriod.php`, `Controller/AvailabilityController.php`,
  `EventSubscriber/Actions/AvailabilityPageSubscriber.php`, `Resources/views/availability/index.html.twig`
- Modify: `EventSubscriber/MenuSubscriber.php` (Reporting-Menü „Verfügbarkeit" hinter
  `view_availability`), `dev/ci.sh` (Seite `/en/holiday/availability` 200), Übersetzungen
- Test: `Tests/Service/AvailabilityPeriodTest.php`

**Interfaces:**
- Consumes: `AvailabilityCalculator::forPeriod`, `LocationPermissions::visibleLocations/canViewAvailabilityOf`,
  `ExtraShiftPermissions::canCreateFor`.
- Produces:
  - `AvailabilityPeriod::week(int $isoYear, int $isoWeek): self`, `::month(int $year, int $month): self`,
    `::containing(\DateTimeImmutable $day, string $unit): self`, Felder `unit` (`week|month`),
    `from`, `to` (inklusive), Methoden `prev(): self`, `next(): self`, `label(): string`
    („KW 44/2026 · 26.10.–01.11.2026" bzw. „Oktober 2026"), `routeParams(): array`.
  - Routen (`#[Route('/holiday/availability')]`, `view_availability`): `holiday_availability`
    (`/{year}/week/{week}`, Default: aktuelle Woche), `holiday_availability_month`
    (`/{year}/month/{month}`); Query `?location=<id>`; ohne Parameter der Standort des Nutzers,
    sonst der erste sichtbare; kein sichtbarer Standort → `kit.empty_state('holiday.availability.no_location')`.
  - Seitenaktion „Aushilfe eintragen" (`modal-ajax-form` auf `holiday_extra_shift_create`, mit
    `date`-Vorbelegung über Query) für Nutzer mit `canCreateFor`.

- [ ] **Step 1: Failing Tests**

```php
public function testWeekAcrossYearBoundary(): void   // week(2026, 53) → 28.12.2026–03.01.2027; next() = week(2027, 1); prev() = week(2026, 52)
public function testMonthPrevNext(): void            // month(2026, 1)->prev() = month(2025, 12)
public function testContainingWeekUsesIsoWeek(): void // Sonntag 01.11.2026 liegt in KW 44/2026
```

- [ ] **Step 2: Fail prüfen.** — [ ] **Step 3: Implementieren**

Template: `kit.period_nav(['week','month'], …)`, Standort-Select mit `data-holiday-autosubmit`
(nur wenn > 1 sichtbar), `kit.context_line([location.name, period.label])`.
**Woche:** `.kpu-hint-cards` mit einer `kit.hint_card` je Tag Mo–So: Titel „Mi 28.10. · 14:00–21:00"
(geschlossen: Titel + „geschlossen"/Feiertagsname, `type: null`); Body: Liste `present` (Anzeigename +
Vermerk „fix"/„Aushilfe", bei `halfDay` Zusatz „halber Tag"), danach `offen:` (Namen + Typ),
`Abwesend:` (Namen + Typ); unbesetzt → `type: 'danger'`, Text „Unbesetzt" und Icon
(`kit.status_badge('warning', …)` ist erlaubt, kein eigenes CSS). **Monat:** Tabelle, eine Spalte je
**Öffnungstag**, Zeilen = Mitglieder; Zelle via `kit.calendar_day('', {content: Symbol, absence|holiday|weekend})`,
Fußzeile „Unbesetzt" je Spalte. Legende wie im Teamkalender.

- [ ] **Step 4: Tests grün, Smoke**

`dev/test.sh`, `check-translations`, `lint:twig`. Dev-Stack mit Standort Wien, Manuel/Moritz als
Mitglieder, einem Urlaub und einer Aushilfe: Woche KW 44/2026 und Monat November — Seiten 200, im
HTML die Namen an den erwarteten Tagen (grep), ein unbesetzter Tag mit „Unbesetzt". Als `ROLE_USER`
(Manuel) ist die Seite erreichbar, Standort-Select fehlt, „Aushilfe eintragen" sichtbar.

- [ ] **Step 5: Commit** — `Verfügbarkeitsansicht je Standort (Woche, Monat)`

---

### Task 7: Doku, Release 2.1.0, PR

**Files:**
- Modify: `README.md` (Abschnitt „Standorte und Verfügbarkeit", Rechte-Tabelle, Aushilfe),
  `CHANGELOG.md` (`## 2.1.0 (<Datum>)` — Added: Standorte, Öffnungszeiten, Ausnahmen, Mitglieder;
  Aushilfe; Verfügbarkeitsansicht; neue Rechte; Changed: Teamkalender zeigt Aushilfen; Feiertagsgruppe
  in Verwendung nicht löschbar), `composer.json` (`2.1.0`), `TODO.md` (Plan-3-Zeilen: Verschiebung,
  `shifted`-Vermerk, Zähler für Verschiebungen; API für Standorte/Aushilfen).

- [ ] **Step 1: Doku und Version.** — [ ] **Step 2: Gesamtlauf** (`dev/test.sh`, Übersetzungen,
`lint:twig`, CI grün). — [ ] **Step 3: PR** `gh pr create -R zafe-apps/kimai-holiday-bundle --base master
--title "Standort, Aushilfe und Verfügbarkeit (Plan 2)"` mit Spec-/Plan-Link, Änderungen je Task,
Rollout-Schritten; nicht mergen (Final-Review und Freigabe des Betreibers). — [ ] **Step 4: Nach
Freigabe:** Merge, Tag `v2.1.0` (Release-Workflow baut das Zip).

---

### Task 8: Rollout auf zeiterfassung.zafe.at (Betreiber anwesend, nicht an Agenten)

- [ ] Backup wie in Plan 1 (Dump + Zählwerte, zusätzlich `SELECT COUNT(*) FROM kimai2_ext_holiday_absence`).
- [ ] Zip `HolidayBundle-2.1.0.zip` ins Plugins-Volume (altes Verzeichnis ersetzen), `kimai:reload`,
  `kimai:bundle:holiday:install` → fünf neue Tabellen.
- [ ] Standort „Wien" anlegen (Mi–Fr 14:00–21:00, Sa 12:00–21:00, Gruppe Österreich), Mitglieder
  Manuel und Moritz (Chefs nach Wunsch des Betreibers).
- [ ] Verfügbarkeit der laufenden Woche prüfen: Mi–Fr Manuel, Fr+Sa Moritz; ein Tag mit Urlaub →
  unbesetzt sichtbar.
- [ ] Protokoll `docs/rollout/2026-xx-plugin-2.1.0.md`.

---

## Selbst-Review (ausgeführt)

- **Spec-Deckung:** 4.5 → Task 4 (Felder, zwei Prüfungen, ohne Genehmigung; Zusatzregel
  Öffnungstag als Controller-Entscheidung markiert); 4.6 → Task 1–3 (Felder, Wochenmuster,
  Feiertag = zu, Ausnahmen, Zuordnungstabelle, ein Standort je Nutzer, Nutzer ohne Standort in
  keiner Ansicht); 4.7 → Task 5–6 (Woche/Monat, Öffnungszeit, anwesend-Regel, halber Tag, Vermerke
  fix/Aushilfe — „verschoben von" Plan 3 —, offene Anträge, Abwesend-Zeile, Unbesetzt mit Farbe +
  Text + Symbol, Zugriff für Mitarbeiter, Teamkalender mit Aushilfe-Symbol); Rechte → Task 1;
  Release/Rollout → Task 7–8.
- **Typen:** `OpeningSlot` in Task 2, 4, 5 identisch; `PresenceKind` aus Task 1; `AvailabilityDay`
  Felder in Task 5 und 6 gleich; `LocationBuilder::vienna` in Task 2, 4, 5 genutzt.
- **Review Focus:** 1–3 Tests in Task 5, 4 in Task 6, 5 als Smoke in Task 3 (bewusst kein
  Unit-Test: FK-Verhalten ist DB-Sache).
- **Offen für den Bauer:** exakte Kit-Argumente der Hint-Cards; Form-Theme für die sieben
  Öffnungszeit-Zeilen; ob `UserType` die Ausschlussliste direkt unterstützt oder ein `choice_filter`
  nötig ist; Icon-Namen (Tabler) für Aushilfe und Unbesetzt.

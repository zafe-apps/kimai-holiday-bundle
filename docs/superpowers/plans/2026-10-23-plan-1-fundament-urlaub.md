# Plan 1: Fundament + Urlaub richtig — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Der Fork `zafe-apps/kimai-holiday-bundle` läuft auf Kimai 2.70.2 bei zafe und löst das
Urlaubsproblem: Samstag zählt, Urlaubskonto je Arbeitsjahr mit Übertrag, Genehmigung durch den
jeweils anderen Chef mit Mail an alle Genehmiger plus Zähler, österreichische Feiertage ohne
Gedenktage.

**Architecture:** Das Plugin bleibt ein Symfony-Bundle in Kimai (`Controller/API → Service →
Repository → Entity`). Neue Fachlogik entsteht als eigenständige Services ohne
Controller-Abhängigkeit (`VacationYearResolver`, `VacationAccountService`, `ApproverResolver`,
`OpenRequestCounter`) und wird mit PHPUnit-Unit-Tests abgedeckt, die in einem Kimai-2.70.2-Checkout
laufen. Der Chokepoint „Arbeitstag ja/nein" bleibt `UserWorkContract::getExpectedSecondsForDate()`
→ `AbsenceWorkdayHelper::isAbsenceApplicableDay()`; der harte Sa/So-Check entfällt. Alles läuft
in Docker, weil der Entwicklungsrechner kein PHP hat.

**Tech Stack:** PHP 8.2+, Symfony 6 (Kimai 2.70.2), Doctrine ORM mit Attributen, Twig mit dem
vendored Kit (`Resources/views/_kit/`), PHPUnit 10 (aus Kimais `require-dev`), MariaDB 11, Docker
Compose, GitHub Actions.

**Spec:** `docs/superpowers/specs/2026-10-23-kimai-dienstplan-design.md` (Abschnitte 1–4.4 ohne
Verschiebung/Aushilfe/Standort, 4.8, 5). Plan 2 (Standort + Verfügbarkeit + Aushilfe) und Plan 3
(Tagesverschiebung) folgen.

## Global Constraints

- Kimai-Mindestversion `26400` bleibt in `composer.json`; CI und Dev-Stack testen gegen **2.70.2**.
- PHP ≥ 8.2 (Kimai 2.70.2 verlangt es); CI-Lint-Matrix 8.2, 8.3, 8.4.
- Tabellenpräfix `kimai2_ext_holiday_*`, Migrationstabelle `bundle_migration_holiday`, Install
  über `kimai:bundle:holiday:install`. Veröffentlichte Migrationen werden nie editiert.
- Texte immer in `Resources/translations/*.de.xlf` **und** `*.en.xlf` (gleiche Schlüssel,
  `php dev/check-translations.php` erzwingt es).
- UI nur mit den Makros und Klassen aus `Resources/views/_kit/` (vendored Kit, unverändert
  lassen); keine eigenen Farben/Pixelwerte.
- Berechtigungsnamen bleiben kompatibel zum `WorkContractBundle` (bestehende Liste in
  `DependencyInjection/HolidayExtension.php`).
- Commit-Messages: Imperativ, Betreff ≤ 50 Zeichen, deutsch, Body erklärt das Warum.
- Nichts wird gegen Prod ausgeführt außer in Task 8, und dort nur nach Backup.
- Plugin-Version ab diesem Plan `2.0.0` (`composer.json`), Paketname `zafe-apps/kimai-holiday-bundle`.

## Review Focus

1. **Vertrag ohne Eintrittsdatum:** Nutzer mit Urlaubsanspruch, aber leerem `workStartingDay` —
   erwartet: Konto fällt auf das Kalenderjahr zurück und die Seite zeigt einen Hinweis, kein
   Fehler. Test in Task 4 (`VacationYearResolverTest::testFallsBackToCalendarYearWithoutStart`).
2. **Urlaub über die Grenze zweier Urlaubsjahre:** Abwesenheit 10.03.–20.03. bei Eintritt 15.03. —
   erwartet: Tage vor dem 15.03. zählen im alten, ab dem 15.03. im neuen Urlaubsjahr, nichts
   doppelt. Test in Task 4 (`VacationAccountServiceTest::testAbsenceSpanningYearBoundary`).
3. **Genehmiger ist zugleich Antragsteller:** Admin beantragt eigenen Urlaub und klickt
   „Genehmigen" — erwartet: 403/Fehlermeldung `holiday.error.self_approval`, Status bleibt
   `requested`. Test in Task 5 (`AbsenceApprovalServiceTest::testApproverMayNotApproveOwnAbsence`).
4. **Feiertag am Samstag bei Samstag-Arbeiter:** 26.12. ist Samstag, A arbeitet Sa — erwartet: kein
   Urlaubstag verbraucht, kein Arbeitstag. Test in Task 3
   (`AbsenceWorkdayHelperTest::testFullDayHolidayOnSaturdayIsNotApplicable`).
5. **Google-Feed liefert Gedenktag mit Namensvariante** („Heiliger Abend" vs. „Heiligabend") —
   erwartet: Ausschluss vergleicht nach Trim und Kleinschreibung; ein unbekannter Name wird
   importiert und ist in der Gruppe sichtbar (kein stilles Verschlucken). Test in Task 6
   (`HolidayImporterTest::testExclusionIsCaseInsensitive`, `…::testUnknownNameIsImported`).

---

## Dateistruktur

**Neu**

- `CLAUDE.md` — Arbeitsregeln des Forks (ersetzt `agent.md`-Inhalt; Upstream-Gitea-Regeln raus).
- `dev/docker-compose.yml`, `dev/Dockerfile`, `dev/kimai-dev.sh`, `dev/test.sh` — Dev-Stack und
  Testlauf.
- `Tests/Support/UserBuilder.php` — Testhelfer für `App\Entity\User` mit Vertrag.
- `Tests/Service/*Test.php` — Unit-Tests (Namespace `KimaiPlugin\HolidayBundle\Tests`).
- `Service/VacationYear.php` — Wertobjekt (Start, Ende, Index) des Urlaubsjahres.
- `Service/VacationYearResolver.php` — Urlaubsjahr zu Datum/Index je Nutzer.
- `Service/VacationAccount.php` — Wertobjekt des Kontos (Anspruch, Übertrag, Korrekturen,
  Verbrauch, Saldo).
- `Service/VacationAccountService.php` — Kontoberechnung mit Übertrag.
- `Service/ApproverResolver.php` — alle Genehmiger eines Nutzers.
- `Service/OpenRequestCounter.php` — Anzahl offener Anträge für einen Genehmiger.
- `Migrations/Version20261023100000.php` — `excluded_names` an `ph_group`.
- `Resources/views/absence/requests.html.twig` — Seite „Offene Anträge".
- `docs/rollout/2026-10-kimai-2.70.2-und-plugin.md` — Rollout-Protokoll (Task 8).

**Geändert**

- `composer.json`, `.github/workflows/ci.yml`, `dev/ci-kimai.sh`, `dev/ci.sh`, `.gitattributes`,
  `README.md`.
- `Service/AbsenceWorkdayHelper.php` — Sa/So raus, `isNonWorkingDay()`, `countDaysBetween()`.
- `Service/UserWorkContract.php` — `getVacationDaysForYear(User, VacationYear)`.
- `Service/WorkingTimeCalculator.php` — Urlaubsfelder aus `VacationAccountService`.
- `Service/AbsenceApprovalService.php` — Selbstgenehmigung verboten, Anlegen durch Genehmiger
  direkt genehmigt, halbe Tage nur wenn erlaubt.
- `Service/AbsencePermissions.php` — `canApproveFor` nie für sich selbst.
- `Service/AbsenceNotifier.php` — Empfänger = Nutzer + alle Genehmiger, `%approver%` im Text.
- `Service/HolidayImporter.php`, `Service/HolidayIcsCatalog.php` — Ausschlussliste.
- `Entity/PublicHolidayGroup.php`, `Form/PublicHolidayGroupType.php` (bzw. das bestehende
  Gruppen-Formular), `Controller/PublicHolidayController.php`.
- `Controller/AbsenceController.php`, `Controller/AbsenceCalendarController.php`,
  `Controller/CalendarFeedController.php`, `Service/UserIcsCalendarBuilder.php`.
- `Repository/AbsenceRepository.php`, `Repository/ManualBookingRepository.php`.
- `EventSubscriber/MenuSubscriber.php`, `DependencyInjection/HolidayExtension.php`.
- `Resources/views/absence/index.html.twig`, `Resources/views/report/absence_calendar.html.twig`.
- `Resources/translations/messages.{de,en}.xlf`, `flashmessages.{de,en}.xlf`.

**Entfernt**

- `.gitea/`, `agent.md`, `REVIEW.md` (Gitea-/PR-Agent-Prozess des Upstreams), Gitea-Statusmeldung
  in `ci.yml`.

---

### Task 1: Fork-Hygiene, CI auf Kimai 2.70.2

**Files:**
- Create: `CLAUDE.md`
- Modify: `composer.json`, `.github/workflows/ci.yml`, `dev/ci-kimai.sh`, `.gitattributes`,
  `README.md`
- Delete: `.gitea/`, `agent.md`, `REVIEW.md`

**Interfaces:**
- Produces: Branch `feat/fundament-urlaub` auf `zafe-apps/kimai-holiday-bundle`, CI grün gegen
  Kimai 2.70.2 und PHP 8.2–8.4.

- [ ] **Step 1: Branch anlegen**

```bash
cd ~/kimai-holiday-bundle && git checkout -b feat/fundament-urlaub
```

- [ ] **Step 2: Gitea-Prozess entfernen**

`git rm -r .gitea agent.md REVIEW.md`. In `.github/workflows/ci.yml` die Jobs `report` und die
Schritte „Report pending to Gitea" samt `env.GITEA_STATUS`/`RUN_URL` und `outputs.started`
entfernen; `on.push.branches: ["**"]` ergänzen um `pull_request`. Lint-Matrix auf
`['8.2', '8.3', '8.4']`. `env.KIMAI_VERSION: '2.70.2'`, Job-Name „Kimai 2.70.2 end-to-end",
MariaDB-Service auf `mariadb:11`. In `dev/ci-kimai.sh` Default `KIMAI_VERSION:-2.70.2` und
Kommentar „PHP 8.2+". In `.gitattributes` `/CLAUDE.md export-ignore` statt `agent.md`/`REVIEW.md`.

- [ ] **Step 3: `composer.json` anpassen**

`name` → `zafe-apps/kimai-holiday-bundle`, `version` → `2.0.0`, `homepage`/`support` auf
`https://github.com/zafe-apps/kimai-holiday-bundle`, `require.php` → `^8.2`, `authors` um
`{"name": "zafe", "homepage": "https://zafe.at"}` ergänzen (Upstream-Autor bleibt),
`extra.kimai.name` → `Working Hours, Holidays & Shifts`.

- [ ] **Step 4: `CLAUDE.md` schreiben**

Inhalt (knapp): Zweck des Forks und Link auf die Spec; Schichtung
`Controller/API → Service → Repository → Entity`; Migrationen nur neu, nie editieren;
Berechtigungen in `HolidayExtension`; Übersetzungen de+en; Kit vendored, nicht ändern; Tests mit
`dev/test.sh`, Dev-Stack mit `dev/kimai-dev.sh`; Git: Branch → PR auf GitHub → Merge nach grünem
CI; Upstream als Remote `upstream`, Bugfixes von dort per Cherry-Pick; Commit-Regeln wie in Global
Constraints. Den Abschnitt „GUI rule" des Upstreams übernehmen, ohne den Verweis auf das
Kante-Repo als Pflicht („Kit nur über Upstream-Sync aktualisieren, lokal nichts daran ändern").

- [ ] **Step 5: README-Kopf**

Oberhalb des Upstream-Textes einen Absatz: Fork von `shrippen/kimai-holiday-bundle` für zafe,
GPL-3.0-or-later, Zusatz Dienstplanung (Spec-Link), Kimai ≥ 2.64, getestet 2.70.2.

- [ ] **Step 6: Push und CI prüfen**

```bash
git add -A && git commit -m "Fork für zafe einrichten, CI auf Kimai 2.70.2" \
  -m "Gitea-Prozess des Upstreams entfällt, PRs laufen auf GitHub. Kimai 2.70.2
braucht PHP 8.2, die Lint-Matrix folgt."
git push -u origin feat/fundament-urlaub
gh run watch --exit-status
```
Expected: beide Jobs grün.

---

### Task 2: Dev-Stack und PHPUnit in Docker

**Files:**
- Create: `dev/Dockerfile`, `dev/docker-compose.yml`, `dev/kimai-dev.sh`, `dev/test.sh`,
  `Tests/Support/UserBuilder.php`, `Tests/Service/UserWorkContractTest.php`
- Modify: `.github/workflows/ci.yml` (Job `unit`), `.gitattributes` (`/Tests export-ignore`),
  `Resources/config/services.yaml` (`../../Tests/` zu `exclude`)

**Interfaces:**
- Produces: `dev/test.sh [phpunit-args]` führt die Plugin-Unit-Tests im Kimai-Container aus;
  `UserBuilder::make(): User` mit Fluent-Settern `wednesdayToSaturday(int $secWeekday, int $secSaturday)`,
  `fridaySaturday(int $seconds)`, `startingOn(string $ymd)`, `endingOn(string $ymd)`,
  `vacationDays(float)`, `holidayGroup(string $id)`, `build(): User`.

- [ ] **Step 1: `dev/Dockerfile`**

Basis `php:8.3-cli`; Extensions `intl gd zip xsl pdo_mysql mbstring` (`docker-php-ext-install`,
dazu `libicu-dev libpng-dev libzip-dev libxslt1-dev`), Composer aus `composer:2`-Image kopieren,
`git`. Beim Build Kimai klonen: `ARG KIMAI_VERSION=2.70.2`, `git clone --depth 1 --branch
$KIMAI_VERSION https://github.com/kimai/kimai.git /opt/kimai`, `composer install
--no-interaction --no-progress --no-scripts` (**mit** dev-Abhängigkeiten, wegen PHPUnit).
Arbeitsverzeichnis `/opt/kimai`.

- [ ] **Step 2: `dev/docker-compose.yml`**

Dienste `db` (`mariadb:11`, DB/User/Passwort `kimai`, Healthcheck wie in `ci.yml`) und `kimai`
(Build aus `dev/Dockerfile`, Volume `..:/opt/kimai/var/plugins/HolidayBundle:ro` wird **nicht**
verwendet — stattdessen `..:/opt/kimai/var/plugins/HolidayBundle` beschreibbar, damit PHPUnit-Cache
unter `var/` landet; `DATABASE_URL=mysql://kimai:kimai@db:3306/kimai?charset=utf8mb4&serverVersion=11.4.0-MariaDB`,
`APP_ENV=dev`, `MAILER_URL=null://null`, `MAILER_FROM=kimai@example.test`, Port `8001:8001`,
Befehl `php -S 0.0.0.0:8001 -t public public/index.php`).

- [ ] **Step 3: `dev/kimai-dev.sh`**

Startet `docker compose -f dev/docker-compose.yml up -d --build`, wartet auf die DB, führt
einmalig `kimai:install --no-interaction`, `kimai:user:create admin admin@example.test
ROLE_SUPER_ADMIN admin`, den Onboarding-Eintrag aus `dev/ci.sh` (`__wizards__`), `kimai:reload`
und `kimai:bundle:holiday:install` aus und druckt `http://localhost:8001` (Login `admin`/`admin`).
Idempotent: zweiter Aufruf überspringt Install, wenn `kimai2_users` existiert.

- [ ] **Step 4: `dev/test.sh`**

```bash
#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")"
docker compose -f docker-compose.yml run --rm -T kimai \
  vendor/bin/phpunit --no-configuration --bootstrap vendor/autoload.php \
  --colors=always var/plugins/HolidayBundle/Tests "$@"
```

- [ ] **Step 5: `Tests/Support/UserBuilder.php` und erster Test**

`UserBuilder` setzt `setWorkContractMode('day')` plus `setWorkHours<Tag>(int)` (Sekunden),
`setWorkStartingDay`, `setLastWorkingDay`, `setHolidaysPerYear`, `setPublicHolidayGroup`.
Den Modus-Namen für „Stunden je Wochentag" aus `App\WorkingTime\Mode\WorkingTimeModeDay::getId()`
in Kimai 2.70.2 übernehmen, nicht raten.

```php
final class UserWorkContractTest extends TestCase
{
    public function testSaturdayWorkerHasExpectedSecondsOnSaturday(): void
    {
        $user = UserBuilder::make()->wednesdayToSaturday(7 * 3600, 9 * 3600)->build();
        $contract = $this->contract();
        self::assertSame(9 * 3600, $contract->getExpectedSecondsForDate($user, new \DateTimeImmutable('2026-10-24'))); // Sa
        self::assertSame(0, $contract->getExpectedSecondsForDate($user, new \DateTimeImmutable('2026-10-26'))); // Mo
    }

    public function testBeforeStartIsZero(): void
    {
        $user = UserBuilder::make()->wednesdayToSaturday(7 * 3600, 9 * 3600)->startingOn('2026-11-01')->build();
        self::assertSame(0, $this->contract()->getExpectedSecondsForDate($user, new \DateTimeImmutable('2026-10-24')));
    }
}
```
`contract()` baut `new UserWorkContract(new WorkingTimeModeFactory([new WorkingTimeModeNone(),
new WorkingTimeModeDay()]), $this->createStub(PublicHolidayGroupRepository::class))` — die
Konstruktoren der Modi in Kimai 2.70.2 nachsehen; brauchen sie Dienste, stattdessen
`WorkingTimeModeFactory` per `createStub` und `getMode()` auf ein Stub-Objekt lenken, dessen
`getCalculator($user)->getWorkHoursForDay($day)` an `User::getWorkHoursForDay()` delegiert.

- [ ] **Step 6: Tests laufen lassen**

Run: `dev/test.sh`
Expected: `OK (2 tests, 3 assertions)`.

- [ ] **Step 7: CI-Job `unit`**

In `ci.yml` neuen Job `unit`: `docker compose -f dev/docker-compose.yml build kimai` und
`dev/test.sh`. `.gitattributes`: `/Tests export-ignore`. `services.yaml`: `../../Tests/` in
`exclude`.

- [ ] **Step 8: Commit**

```bash
git add -A && git commit -m "Dev-Stack und PHPUnit in Docker einrichten" \
  -m "Der Entwicklungsrechner hat kein PHP; Kimai 2.70.2 mit dev-Abhängigkeiten
läuft im Container, die Plugin-Tests darin." && git push
```

---

### Task 3: Samstag-Regel — Arbeitstag nur aus dem Vertrag

**Files:**
- Modify: `Service/AbsenceWorkdayHelper.php`, `Controller/CalendarFeedController.php:93`,
  `Service/UserIcsCalendarBuilder.php:95`, `Controller/AbsenceCalendarController.php:75-91`,
  `Resources/views/report/absence_calendar.html.twig:62,83`
- Test: `Tests/Service/AbsenceWorkdayHelperTest.php`

**Interfaces:**
- Consumes: `UserWorkContract::getExpectedSecondsForDate(User, DateTimeInterface): int`,
  `UserBuilder`.
- Produces: `AbsenceWorkdayHelper::isNonWorkingDay(User $user, \DateTimeInterface $date): bool`
  (Soll ≤ 0 oder ganztägiger Feiertag); `isWeekend()` entfällt;
  `countDaysBetween(Absence $absence, \DateTimeImmutable $from, \DateTimeImmutable $to): float`.

- [ ] **Step 1: Failing Tests**

```php
public function testSaturdayCountsForSaturdayWorker(): void
{
    $user = UserBuilder::make()->wednesdayToSaturday(7 * 3600, 9 * 3600)->build();
    $absence = AbsenceBuilder::vacation($user, '2026-10-21', '2026-10-24'); // Mi–Sa
    self::assertSame(4.0, $this->helper()->countDays($absence));
}

public function testMondayDoesNotCount(): void
{
    $user = UserBuilder::make()->wednesdayToSaturday(7 * 3600, 9 * 3600)->build();
    self::assertFalse($this->helper()->isAbsenceApplicableDay($user, new \DateTimeImmutable('2026-10-26')));
}

public function testFullDayHolidayOnSaturdayIsNotApplicable(): void
{
    // 26.12.2026 ist ein Samstag; Gruppe 1 hat dort einen ganztägigen Feiertag
    $user = UserBuilder::make()->wednesdayToSaturday(7 * 3600, 9 * 3600)->holidayGroup('1')->build();
    self::assertFalse($this->helperWithHoliday('2026-12-26')->isAbsenceApplicableDay($user, new \DateTimeImmutable('2026-12-26')));
}

public function testCountDaysBetweenClipsToWindow(): void
{
    $user = UserBuilder::make()->wednesdayToSaturday(7 * 3600, 9 * 3600)->build();
    $absence = AbsenceBuilder::vacation($user, '2026-10-21', '2026-10-24');
    self::assertSame(2.0, $this->helper()->countDaysBetween($absence, new \DateTimeImmutable('2026-10-23'), new \DateTimeImmutable('2026-10-31')));
}
```
`AbsenceBuilder` (in `Tests/Support/`) baut eine `Absence` mit Typ, Status `APPROVED`, Datum.
`helperWithHoliday()` stubt `PublicHolidayRepository::findByGroupAndYear` mit einem
`PublicHoliday` am Datum und `PublicHolidayGroupRepository::find(1)` mit einer Gruppe (ID per
Reflection auf 1 setzen).

- [ ] **Step 2: Fail prüfen**

Run: `dev/test.sh --filter AbsenceWorkdayHelperTest`
Expected: FAIL — `testSaturdayCountsForSaturdayWorker` liefert 3.0, `countDaysBetween` existiert
nicht.

- [ ] **Step 3: Implementieren**

`isAbsenceApplicableDay` ohne Wochenend-Prüfung; neues `isNonWorkingDay()`; `countDays()` ruft
`countDaysBetween()` mit Jahresgrenzen bzw. Absenz-Grenzen. Klassen-Kommentar anpassen.
Aufrufer: `CalendarFeedController:93` und `UserIcsCalendarBuilder:95` nutzen
`isNonWorkingDay($user, $date)`; `AbsenceCalendarController` liefert statt `weekend[day]` je
Nutzer und Tag `off[uid][day]` (= `isNonWorkingDay`), das Template schattiert die Zelle damit
(`kit.calendar_day(..., {off: ...})` — vorhandenes Kit-Argument für die Schattierung verwenden,
Name im Kit-Makro nachsehen) und lässt die Kopfzeile ohne Wochenend-Schattierung.

- [ ] **Step 4: Tests grün, Smoke**

Run: `dev/test.sh` → PASS. `dev/kimai-dev.sh` und
`http://localhost:8001/en/holiday/absence-calendar` öffnen: Spalten Sa/So nicht mehr pauschal
grau, Zellen von Nutzern mit Soll 0 grau.

- [ ] **Step 5: Commit**

```bash
git commit -am "Arbeitstag nur aus dem Vertrag ableiten" \
  -m "Samstag war hart als Wochenende codiert; wer laut Vertrag samstags
arbeitet, bekam dort keinen Urlaubstag gezählt (Spec 4.1)."
```

---

### Task 4: Urlaubskonto je Arbeitsjahr mit Übertrag

**Files:**
- Create: `Service/VacationYear.php`, `Service/VacationYearResolver.php`,
  `Service/VacationAccount.php`, `Service/VacationAccountService.php`
- Modify: `Service/UserWorkContract.php` (`getVacationDaysForYear`),
  `Repository/AbsenceRepository.php` (`findApprovedVacationsBetween`),
  `Repository/ManualBookingRepository.php` (`sumHolidayDaysBetween`),
  `Service/WorkingTimeCalculator.php:129-144`, `Controller/AbsenceController.php:64-100`,
  `Resources/views/absence/index.html.twig:63-68`, Übersetzungen
- Test: `Tests/Service/VacationYearResolverTest.php`, `Tests/Service/VacationAccountServiceTest.php`

**Interfaces:**
- Produces:
  - `final class VacationYear { public function __construct(public readonly \DateTimeImmutable $start, public readonly \DateTimeImmutable $end, public readonly int $index) {} public function contains(\DateTimeInterface $d): bool; public function label(): string /* "15.03.2026 – 14.03.2027" */ }`
  - `VacationYearResolver::forDate(User $user, \DateTimeInterface $date): VacationYear`,
    `::previous(User, VacationYear): ?VacationYear` (null vor Eintritt),
    `::next(User, VacationYear): VacationYear`, `::isCalendarFallback(User): bool`
    (true ohne `workStartingDay`; dann Kalenderjahr, `index` = Jahreszahl).
  - `final class VacationAccount { entitlement, carryOver, corrections, used, balance: float; year: VacationYear }`
  - `VacationAccountService::calculate(User $user, VacationYear $year): VacationAccount` —
    rekursiv `carryOver = calculate(previous)->balance`, 0 ohne Vorjahr.
  - `UserWorkContract::getVacationDaysForYear(User $user, VacationYear $year): float` — voller
    Anspruch; endet der Vertrag im Jahr, anteilig nach vollen Monaten im Fenster, auf halbe Tage
    aufgerundet (bestehende Formel, Fenster statt Kalenderjahr).
  - `AbsenceRepository::findApprovedVacationsBetween(User, \DateTimeInterface $from, \DateTimeInterface $to): array`
  - `ManualBookingRepository::sumHolidayDaysBetween(User, \DateTimeInterface $from, \DateTimeInterface $to): float`

- [ ] **Step 1: Failing Tests (Resolver)**

```php
public function testYearStartsOnAnniversary(): void
{
    $user = UserBuilder::make()->startingOn('2024-03-15')->build();
    $y = $this->resolver()->forDate($user, new \DateTimeImmutable('2026-10-23'));
    self::assertSame('2026-03-15', $y->start->format('Y-m-d'));
    self::assertSame('2027-03-14', $y->end->format('Y-m-d'));
    self::assertSame(2, $y->index);
}

public function testDayBeforeAnniversaryBelongsToPreviousYear(): void
{
    $user = UserBuilder::make()->startingOn('2024-03-15')->build();
    self::assertSame(1, $this->resolver()->forDate($user, new \DateTimeImmutable('2026-03-14'))->index);
}

public function testPreviousIsNullBeforeStart(): void
{
    $user = UserBuilder::make()->startingOn('2024-03-15')->build();
    $first = $this->resolver()->forDate($user, new \DateTimeImmutable('2024-06-01'));
    self::assertNull($this->resolver()->previous($user, $first));
}

public function testFallsBackToCalendarYearWithoutStart(): void
{
    $user = UserBuilder::make()->build();
    $y = $this->resolver()->forDate($user, new \DateTimeImmutable('2026-10-23'));
    self::assertTrue($this->resolver()->isCalendarFallback($user));
    self::assertSame('2026-01-01', $y->start->format('Y-m-d'));
    self::assertSame(2026, $y->index);
}

public function testLeapDayAnniversary(): void
{
    $user = UserBuilder::make()->startingOn('2024-02-29')->build();
    $y = $this->resolver()->forDate($user, new \DateTimeImmutable('2025-06-01'));
    self::assertSame('2025-02-28', $y->start->format('Y-m-d')); // kein 29.02. → letzter Februartag
    self::assertSame('2026-02-27', $y->end->format('Y-m-d'));
}
```

- [ ] **Step 2: Failing Tests (Konto)**

Repositories als Stubs: `findApprovedVacationsBetween` liefert vorbereitete `Absence`-Objekte,
`sumHolidayDaysBetween` Zahlen je Fenster.

```php
public function testBalanceWithoutHistory(): void
{
    // Eintritt 15.03.2026, 20 Tage, Mi–Sa; Urlaub 21.–24.10.2026 (4 Arbeitstage)
    $user = UserBuilder::make()->startingOn('2026-03-15')->vacationDays(20)->wednesdayToSaturday(7 * 3600, 9 * 3600)->build();
    $acc = $this->service([AbsenceBuilder::vacation($user, '2026-10-21', '2026-10-24')])->calculate($user, $this->year($user, '2026-10-23'));
    self::assertSame(20.0, $acc->entitlement);
    self::assertSame(0.0, $acc->carryOver);
    self::assertSame(4.0, $acc->used);
    self::assertSame(16.0, $acc->balance);
}

public function testCarryOverFromPreviousYear(): void
{
    // Eintritt 15.03.2024, 20 Tage/Jahr, Jahr 0 verbraucht 15, Jahr 1 verbraucht 20 → Jahr 2 Übertrag 5
    ...
    self::assertSame(5.0, $acc->carryOver);
    self::assertSame(25.0, $acc->balance); // 20 + 5 − 0
}

public function testAbsenceSpanningYearBoundary(): void
{
    // Eintritt 15.03.2025, Mi–Sa; Urlaub 11.03.–18.03.2026 → 11.,12.,13.,14.03. (Mi–Sa) im Jahr 0; 18.03. (Mi) im Jahr 1
    ...
    self::assertSame(4.0, $this->service($absences)->calculate($user, $year0)->used);
    self::assertSame(1.0, $this->service($absences)->calculate($user, $year1)->used);
}

public function testManualBookingIsCorrection(): void
{
    // sumHolidayDaysBetween liefert −3 im laufenden Jahr → corrections −3, balance 17
}

public function testProRataWhenContractEndsInYear(): void
{
    // Eintritt 15.03.2024, Austritt 14.09.2026, 20 Tage → Jahr 2: 6 volle Monate → 10
}
```

- [ ] **Step 3: Fail prüfen**

Run: `dev/test.sh --filter 'Vacation'`
Expected: FAIL — Klassen fehlen.

- [ ] **Step 4: Implementieren**

`VacationYearResolver`: Anker = `workStartingDay` (Tag/Monat); Start des Jahres mit Index n =
Anker + n Jahre (29.02. → letzter Februartag des Zieljahres), Ende = Start des Folgejahres − 1 Tag.
`VacationAccountService` nutzt `AbsenceWorkdayHelper::countDaysBetween` je Absenz mit dem
Jahresfenster. `UserWorkContract::getVacationDaysPerYear(User, ?int)` bleibt für Altaufrufer, ruft
intern die neue Methode mit dem Kalenderjahr.

- [ ] **Step 5: Tests grün**

Run: `dev/test.sh` → PASS.

- [ ] **Step 6: Konto in Seite und Calculator**

`WorkingTimeCalculator::calculateYear`: Schlüssel `vacationUsed/Entitlement/Balance` kommen aus
`VacationAccountService::calculate($user, $resolver->forDate($user, $untilDay))`; zusätzlich
`vacationYearLabel`, `vacationCarryOver`. `AbsenceController::index`: Jahresparameter bleibt der
Index des **Urlaubsjahres** (bei Kalender-Fallback die Jahreszahl); Liste über
`findByUserBetween($user, $year->start, $year->end)` (neue Repository-Methode, ersetzt
`findByUserAndYear` dort); KPI-Leiste bekommt die Kachel `holiday.kpi.carry_over`
(„Übertrag aus Vorjahr") vor `vacation_left`, Überschrift `holiday.kpi.vacation_year` mit
`year.label()`; vor/zurück navigieren über `previous()`/`next()`. Ohne `workStartingDay`
Hinweis `holiday.notice.no_start_date` („Kein Eintrittsdatum im Vertrag — Urlaubsjahr =
Kalenderjahr") über der Leiste. Halbe Tage: in `AbsenceApprovalService::validate` Fehler
`holiday.error.half_day_not_allowed`, wenn `halfDay` und Typ `VACATION` und
`HolidayConfiguration::isHalfDayVacationAllowed()` false (Methode existiert; sonst anlegen, Key
`holiday.allow_half_day_vacation`).

- [ ] **Step 7: Übersetzungen, Smoke, Commit**

Keys in de+en. `dev/kimai-dev.sh`, Nutzer mit Eintritt und Urlaub anlegen, Kacheln prüfen.
`php dev/check-translations.php` grün.

```bash
git commit -am "Urlaubskonto je Arbeitsjahr mit Übertrag" \
  -m "Urlaubsjahr läuft ab Eintrittsdatum, der Saldo des Vorjahres wird
automatisch übertragen (Spec 4.2). Kalenderjahr bleibt Rückfall ohne Datum."
```

---

### Task 5: Genehmigung durch den anderen Chef, Mail an alle Genehmiger, Zähler

**Files:**
- Create: `Service/ApproverResolver.php`, `Service/OpenRequestCounter.php`,
  `Resources/views/absence/requests.html.twig`
- Modify: `DependencyInjection/HolidayExtension.php` (Rollen), `Service/AbsencePermissions.php`,
  `Service/AbsenceApprovalService.php`, `Service/AbsenceNotifier.php`,
  `Repository/AbsenceRepository.php` (`findRequestedForUsers`), `EventSubscriber/MenuSubscriber.php`,
  `Controller/AbsenceController.php` (Route `holiday_absence_requests`), `Resources/config/routes.yaml`,
  Übersetzungen `messages`/`flashmessages`
- Test: `Tests/Service/ApproverResolverTest.php`, `Tests/Service/AbsenceApprovalServiceTest.php`,
  `Tests/Service/AbsenceNotifierTest.php`

**Interfaces:**
- Consumes: `App\Security\RolePermissionManager::hasRolePermission(User, string)`,
  `::checkUserAccess(User $subject, User $user)`, `App\Repository\UserRepository`.
- Produces:
  - `ApproverResolver::approversFor(User $subject): array` — alle aktivierten Nutzer ≠ subject mit
    `approve_other_absence` und `checkUserAccess($subject, $candidate)`.
  - `OpenRequestCounter::countFor(User $approver): int` — Anzahl `REQUESTED`-Abwesenheiten von
    Nutzern, für die `$approver` Genehmiger ist.
  - `AbsenceRepository::findRequestedForUsers(array $users): array`.
  - `AbsenceApprovalService::create(Absence, User $actor)`: legt der Actor für einen **anderen**
    Nutzer an und ist dessen Genehmiger → direkt `APPROVED` mit `approvedBy = actor`.
  - `approve()/reject()` werfen `\InvalidArgumentException('holiday.error.self_approval')`, wenn
    `$approver === $absence->getUser()`.

- [ ] **Step 1: Failing Tests**

```php
// ApproverResolverTest
public function testBothAdminsAreApproversOfEmployee(): void   // zwei Admins, ein User → beide
public function testSubjectIsNeverOwnApprover(): void          // Admin A als subject → nur Admin B
public function testDisabledUserIsNotApprover(): void

// AbsenceApprovalServiceTest
public function testApproverMayNotApproveOwnAbsence(): void    // erwartet Exception self_approval, Status bleibt REQUESTED
public function testApproverCreatingForOtherIsApprovedDirectly(): void
public function testUserCreatingForSelfIsRequested(): void

// AbsenceNotifierTest (MailerInterface-Mock sammelt Emails)
public function testRequestedGoesToUserAndAllApprovers(): void // to: user + 2 Admins
public function testDecisionNamesApprover(): void              // Body enthält Displayname des Genehmigers
```

- [ ] **Step 2: Fail prüfen**

Run: `dev/test.sh --filter 'Approver|Approval|Notifier'` → FAIL.

- [ ] **Step 3: Implementieren**

`HolidayExtension`: `approve_own_absence` aus allen Rollen entfernen (Name bleibt registriert).
`AbsencePermissions::canApproveFor`: `return $user !== $this->getCurrentUser() && …`.
`AbsenceApprovalService` bekommt `ApproverResolver`; Selbstgenehmigung prüfen; `create()` mit
Direktgenehmigung, wenn `in_array($actor, approversFor($absence->getUser()), true)`.
`AbsenceNotifier` bekommt `ApproverResolver`; Empfänger immer Nutzer + Genehmiger; neue
Platzhalter `%approver%` in `holiday.mail.approved.body`/`rejected.body`.
`MenuSubscriber`: `$absence->setBadge((string) $count)` und `setBadgeColor('yellow')`, wenn
`$count > 0` (`OpenRequestCounter`). Neue Seite `holiday_absence_requests`
(`/holiday/absence/requests`): Tabelle aller offenen Anträge der zugreifbaren Nutzer mit
Genehmigen/Ablehnen (bestehende Row-Actions wiederverwenden); Menüpunkt „Offene Anträge" unter
`contract`, nur für Nutzer mit `approve_other_absence`. Die bestehende Bulk-Genehmigung bleibt.

- [ ] **Step 4: Tests grün, Smoke**

`dev/test.sh` → PASS. Dev-Stack: als `admin` eigenen Urlaub beantragen → Button „Genehmigen" fehlt;
zweiten Admin anlegen, einloggen → Badge zeigt 1, Seite listet den Antrag, genehmigen → Badge weg.

- [ ] **Step 5: Übersetzungen prüfen, Commit**

```bash
git commit -am "Genehmigung nur durch andere, Mail an alle Genehmiger" \
  -m "Niemand genehmigt sich selbst, beide Chefs sehen jeden Antrag und
jede Entscheidung — per Mail und als Zähler im Menü (Spec 4.3)."
```

---

### Task 6: Österreichische Feiertage mit Ausschlussliste

**Files:**
- Create: `Migrations/Version20261023100000.php`
- Modify: `Entity/PublicHolidayGroup.php`, Gruppen-Formular (`Form/PublicHolidayGroupType.php`
  oder wie im Repo benannt), `Service/HolidayIcsCatalog.php:121-122`,
  `Service/HolidayImporter.php:128-151`, `Controller/PublicHolidayController.php` (Delete,
  neue Edit-Route für Feiertage), `Resources/views/public_holiday/*.twig`, Übersetzungen
- Test: `Tests/Service/HolidayImporterTest.php`

**Interfaces:**
- Produces:
  - `PublicHolidayGroup::getExcludedNames(): array` / `setExcludedNames(array $names): void`
    (Spalte `excluded_names` JSON, nullable → `[]`).
  - `HolidayIcsCatalog` Einträge bekommen optional `'exclude' => list<string>`; für `at` und
    `at-de` die Liste unten.
  - `HolidayImporter::import()` setzt bei leerer Gruppenliste die Katalog-Vorbelegung;
    `applyHolidays()` überspringt Namen aus der Liste (Vergleich `mb_strtolower(trim())`).
  - `PublicHolidayController::delete`: hat die Gruppe eine `icsUrl`, wandert der Name des
    gelöschten Feiertags in `excludedNames`.
  - Route `holiday_public_holiday_edit` (Name, Datum, halber Tag).

**Gesetzliche Feiertage Österreich (behalten, alles andere im Feed ausschließen):** Neujahr,
Heilige Drei Könige, Ostermontag, Staatsfeiertag, Christi Himmelfahrt, Pfingstmontag,
Fronleichnam, Mariä Himmelfahrt, Nationalfeiertag, Allerheiligen, Mariä Empfängnis, Christtag,
Stefanitag. Die Ausschlussliste für `at-de` entsteht beim Bauen aus dem echten Feed
(`https://calendar.google.com/calendar/ical/de.austrian%23holiday%40group.v.calendar.google.com/public/basic.ics`):
alle `SUMMARY`-Werte, die nicht in dieser Dreizehnerliste stehen (erwartet u. a. Heiliger Abend,
Silvester, Karfreitag, Muttertag, Vatertag, Valentinstag, Josefstag, Florian, Rupert, Leopoldi,
Martini, Faschingsdienstag, Allerseelen, Nikolaus). Für `at` dieselben Tage mit den englischen
Namen des Feeds.

- [ ] **Step 1: Failing Tests**

```php
public function testExcludedNameIsSkipped(): void          // Feed mit „Heiliger Abend" → nicht gespeichert
public function testExclusionIsCaseInsensitive(): void     // Liste „heiliger abend " vs Feed „Heiliger Abend"
public function testUnknownNameIsImported(): void           // „Tag der Arbeit" nicht in Liste → gespeichert
public function testCatalogDefaultsFillEmptyGroupList(): void // import('at-de') auf Gruppe ohne Liste → Liste gesetzt
public function testExistingGroupListIsKept(): void
```
`HttpClientInterface` mit `MockHttpClient` und einem kleinen ICS-String stubben; Repositories als
Stubs/Spies.

- [ ] **Step 2: Fail prüfen** — `dev/test.sh --filter HolidayImporterTest` → FAIL.

- [ ] **Step 3: Migration und Implementierung**

Migration im Stil von `Version20260925120000.php` (`hasTable`/`hasColumn`-Guard):
`ALTER TABLE kimai2_ext_holiday_ph_group ADD excluded_names JSON DEFAULT NULL` über die
Schema-API (`addColumn('excluded_names', 'json', ['notnull' => false])`). Formularfeld
`excludedNames` als `TextareaType` mit `CallbackTransformer` (Zeilen ↔ Array), Help-Text
`holiday.public_holiday.excluded_names_help`. Edit-Route für Feiertage analog zur bestehenden
Create-Route.

- [ ] **Step 4: Tests grün, Smoke**

`dev/test.sh` → PASS. Dev-Stack: Gruppe „Österreich" aus Katalog `at-de` ab 2024 importieren →
nur gesetzliche Feiertage erscheinen; einen löschen, `kimai:bundle:holiday:sync-ics` ausführen →
bleibt weg.

- [ ] **Step 5: Commit**

```bash
git commit -am "Feiertags-Import mit Ausschlussliste je Gruppe" \
  -m "Google-Feeds führen Gedenktage als Ereignisse; die Gruppe hält, was
nicht importiert wird, und gelöschte Einträge kommen beim Sync nicht
zurück (Spec 4.8). Österreich ist vorbelegt."
```

---

### Task 7: Release 2.0.0 und PR

**Files:**
- Modify: `CHANGELOG.md`, `composer.json` (Version bereits 2.0.0), `README.md`

- [ ] **Step 1: CHANGELOG** — Abschnitt `2.0.0` mit den Punkten der Tasks 3–6 und dem Hinweis
  „Bruch: Urlaubsjahr = Arbeitsjahr ab Eintrittsdatum; `approve_own_absence` in keiner Rolle".
- [ ] **Step 2: Gesamtlauf** — `dev/test.sh`, `php dev/check-translations.php` (im Container),
  CI grün auf dem Branch.
- [ ] **Step 3: PR** — `gh pr create --base master --title "Fundament und Urlaubskonto (Plan 1)"`
  mit Spec-/Plan-Link und den Entscheidungen; Review durch `code-reviewer` (neuer Agent, nur Diff
  und Spec); **Wichtig**-Befunde beheben.
- [ ] **Step 4: Merge und Release** — nach Freigabe des Betreibers mergen,
  `gh release create v2.0.0 --generate-notes` (bestehendes `release.yml` baut das Zip
  `HolidayBundle-2.0.0.zip`).

---

### Task 8: Rollout auf zafe-server-1 (Betreiber anwesend)

**Files:**
- Create: `docs/rollout/2026-10-kimai-2.70.2-und-plugin.md` (Protokoll mit Ist-Werten)
- Außerhalb des Repos: `/opt/containers/kimai/docker-compose.yml` auf dem Server

**Voraussetzungen vom Betreiber:** SMTP-Zugangsdaten (Host, Port, Benutzer, Passwort,
Absenderadresse); Eintrittsdatum, Wochentage mit Stunden und Urlaubsanspruch je Person; Liste der
seit Eintritt genommenen Urlaube.

- [ ] **Step 1: Backup**

```bash
ssh zafe-server-1 'mkdir -p ~/backups/kimai && docker exec kimai-db sh -c "mariadb-dump -ukimai -p\"\$MARIADB_PASSWORD\" kimai" | gzip > ~/backups/kimai/kimai-$(date +%F).sql.gz && docker run --rm -v kimai_kimai_data:/d -v ~/backups/kimai:/b alpine tar czf /b/kimai_data-$(date +%F).tgz -C /d .'
```
Dann Zählwerte sichern: `SELECT COUNT(*) FROM kimai2_users; … kimai2_timesheet; … kimai2_customers;`
ins Protokoll.

- [ ] **Step 2: Compose pinnen und updaten**

`image: kimai/kimai2:2.70.2` statt `apache` (Variante vorher per
`docker image inspect kimai/kimai2:2.70.2 --format '{{.Config.Cmd}}'` prüfen: muss Apache sein).
`docker compose pull && docker compose up -d`, Log abwarten bis Migrationen durch,
`docker exec kimai php /opt/kimai/bin/console kimai:version` → `2.70.2`. Zählwerte erneut
abfragen: identisch. Login im Browser, eine Zeiterfassung öffnen.

Rückweg: Tag zurück auf das alte Digest (`kimai/kimai2@sha256:ad7e1627…`), `up -d`, Dump
einspielen.

- [ ] **Step 3: SMTP**

In der Compose-Umgebung `MAILER_URL=smtp://user:pass@host:port` (URL-codiert) und
`MAILER_FROM`; `up -d`; Testmail über Kimais Einstellungen → Benutzer → „Passwort vergessen"-Mail
oder `bin/console mailer:test <adresse>` (vorhanden ab Symfony 6.2).

- [ ] **Step 4: Plugin installieren**

Release-Zip ins Volume: `docker cp HolidayBundle-2.0.0.zip kimai:/tmp/ && docker exec kimai sh -c
'cd /opt/kimai/var/plugins && unzip -q /tmp/HolidayBundle-2.0.0.zip'`, dann `kimai:reload` und
`kimai:bundle:holiday:install`. Seite `/de/holiday/absence` lädt.

- [ ] **Step 5: Stammdaten**

Rollen: Mitarbeiter `ROLE_USER` (Default-Rechte reichen), Chefs sind `ROLE_SUPER_ADMIN`.
Feiertagsgruppe „Österreich" aus Katalog `at-de` ab dem Jahr des frühesten Eintritts. Je Person
im Profil → Arbeitsvertrag: Modus Stunden je Wochentag, Stunden, Eintrittsdatum, Urlaubstage,
Feiertagsgruppe.

- [ ] **Step 6: Nachtrag**

Chefs tragen die bisherigen Urlaube je Person als Abwesenheit ein (als Genehmiger → direkt
genehmigt). Kacheln je Person prüfen und Resturlaub vom Betreiber bestätigen lassen.

- [ ] **Step 7: ICS-Timer**

Systemd-Timer auf dem Host, täglich 04:00: `docker exec kimai php /opt/kimai/bin/console
kimai:bundle:holiday:sync-ics`. Unit-Dateien ins Protokoll.

- [ ] **Step 8: Protokoll committen**

Ist-Werte, Zeiten, Rückweg; `git commit -m "Rollout-Protokoll Kimai 2.70.2 und Plugin 2.0.0"`.

---

## Selbst-Review (ausgeführt)

- **Spec-Deckung:** 4.1 Task 3; 4.2 Task 4 (Arbeitsjahr, Übertrag, Nachtrag via Task 5
  Direktgenehmigung, Kalender-Fallback); 4.3 Task 5 (Genehmiger-Mails, Zähler/Seite, keine
  Selbstgenehmigung, Chef-Konto = normaler Vertrag, halbe Tage in Task 4); 4.8 Task 6; Abschnitt 5
  Tests/Dev-Stack Task 2, Rollout Task 8, Release Task 7. 4.4–4.7 bewusst Plan 2/3. Die
  Zusammenführung der Duplikate `absenceSeconds`/Feiertags-Reduce wandert in Plan 3, wo der
  Chokepoint ohnehin umgebaut wird.
- **Typen:** `VacationYear` überall mit `start/end/index`; `countDaysBetween(Absence,
  DateTimeImmutable, DateTimeImmutable)`; `approversFor(User): array` in Task 5 und 8.
- **Offen gelassen (Bauer):** Name des Kit-Arguments für Zellschattierung; Konstruktor der
  Kimai-Modi im Test; Form-Klassenname der Gruppe; `mailer:test`-Verfügbarkeit.

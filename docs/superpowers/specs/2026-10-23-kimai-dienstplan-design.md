# Design: Urlaub, Verträge und Dienstplan für Kimai bei zafe

Stand 2026-10-23. Gespräch mit dem Betreiber (Felix), Code-Analyse des Plugins
`shrippen/kimai-holiday-bundle` (Commit vom 2026-10-22, 84 Commits, GPL-3.0-or-later).

## 1. Absicht und Warum

zafe betreibt einen Standort in Wien mit Öffnungszeiten Mi–Fr 14–21 Uhr und Sa 12–21 Uhr. Vier
Personen: zwei Mitarbeiter (A arbeitet fix Mi–Sa, B meist Fr+Sa mit wechselnden Tagen) und zwei
Chefs, die bei Bedarf aushelfen.

Heute fragt ein Mitarbeiter einen der beiden Chefs nach Urlaub, der sagt ja, der andere Chef weiß
davon oft nichts. Notiert wird unregelmäßig in einem Teams-Kalender. Es gibt keinen Freigabeprozess
und keine Übersicht, wer an einem Öffnungstag tatsächlich im Studio ist.

Zeiterfassung läuft bereits in einem selbst gehosteten Kimai (2.58.0 auf `zafe-server-1`,
Docker). Das offizielle Kimai-Plugin für Verträge und Abwesenheiten kostet 299 €/Jahr und kennt
keinen Dienstplan. Deshalb: Fork des freien Plugins und Weiterentwicklung.

**Erfolg heißt:** Beide Chefs sehen jeden Antrag und jede Entscheidung. Jeder sieht seinen
Resturlaub. Eine Ansicht zeigt für jeden Öffnungstag, wer da ist, und ein unbesetzter Tag fällt
sofort auf.

## 2. Ziele, Nicht-Ziele, Anti-Ziele

**Ziele**

1. Vertrag je Mitarbeiter: Sollstunden je Wochentag (= fixe Arbeitstage), Urlaubsanspruch pro
   Jahr, Vertragsbeginn/-ende, Feiertagsgruppe.
2. Resturlaub je Mitarbeiter und Jahr, inklusive Übertrag aus dem Vorjahr.
3. Abwesenheiten (Urlaub, Krank, Zeitausgleich, Sonstiges) mit Antrag und Genehmigung; beide
   Chefs werden über jeden Antrag und jede Entscheidung informiert.
4. Österreichische Feiertage automatisch, ohne Gedenktage.
5. Tagesverschiebung: ein Mitarbeiter verlegt einen seiner Arbeitstage in einer Woche auf einen
   anderen Tag; das ist ein Antrag mit Genehmigung wie Urlaub.
6. Aushilfe: ein Chef (oder ein Mitarbeiter) trägt sich für einen Tag zusätzlich ein.
7. Standort mit Öffnungszeiten je Wochentag; mehrere Standorte möglich, aktuell einer.
8. Verfügbarkeitsansicht je Standort und Woche/Monat: pro Öffnungstag die anwesenden Personen,
   unbesetzte Tage markiert.
9. Urlaub an Samstagen zählt, wenn laut Vertrag Samstag ein Arbeitstag ist (im Ursprungsplugin
   ist das Wochenende hart codiert).

**Nicht-Ziele**

- Schichten mit Uhrzeiten je Person („A von 14 bis 18, B von 18 bis 21"). Geplant wird auf
  Tagesebene: da oder nicht da.
- Mindestbesetzung größer als 1 als Regel. Unbesetzt = niemand da; alles darüber beurteilt der
  Mensch.
- Lohnverrechnung, Überstundenauszahlung, Monats-PDF-Freigabe. Was das Plugin dazu hat, bleibt
  wie es ist.
- Tatsächlich gearbeitete Zeiten in der Verfügbarkeitsansicht erfassen oder ändern. Das Ist
  bleibt Kimais Zeiterfassung (Betreiber-Entscheidung 2026-10-23). Spätere Option: in der
  Ansicht für vergangene Tage „geplant / erfasst" anzeigen mit Link auf den Timesheet-Eintrag.
- Ersatz des Teams-Kalenders durch Sync. Der persönliche ICS-Feed des Plugins (Feiertage und
  genehmigte Abwesenheiten) reicht als Brücke; er wird um genehmigte Verschiebungen erweitert.
- Rückportierung an das Ursprungs-Repo als Ziel. Wenn sich etwas sauber trennen lässt, ist ein
  Pull-Request willkommen, aber kein Muss.

**Anti-Ziele** (das wäre ein Fehlschlag, auch wenn es technisch läuft)

- Ein Chef genehmigt, der andere erfährt es nicht.
- Resturlaub stimmt nicht, weil Samstage oder Feiertage falsch gezählt werden.
- Die Verfügbarkeitsansicht zeigt jemanden als anwesend, der genehmigten Urlaub hat, oder
  verschweigt eine genehmigte Verschiebung.
- Ein Kimai-Update bricht das Plugin, ohne dass es jemand vor dem Ausrollen merkt.

## 3. Rahmenbedingungen

- **Kimai-Mindestversion 2.64** (Plugin verlangt `kimai.require: 26400`). Der Server läuft auf
  2.58.0. Erster Schritt des Plans ist das Update auf **2.70.2** (aktuelles Release). Betreiber hat
  das am 2026-10-23 angeordnet, aber ausdrücklich „mit dem Plan", nicht sofort.
- **Deployment:** `/opt/containers/kimai/docker-compose.yml` auf `zafe-server-1`, Container
  `kimai` (Image bisher der unversionierte Tag `kimai/kimai2:apache`, Stand 2026-05-25) und
  `kimai-db` (`mariadb:11`). Volumes: `kimai_kimai_data` (`/opt/kimai/var/data`),
  `kimai_kimai_plugins` (`/opt/kimai/var/plugins`), `kimai_kimai_db` (`/var/lib/mysql`), ein
  anonymes Volume für `/opt/kimai/var`. Der Benutzer `felix` ist in den Gruppen `docker` und
  `sudo`. Das Plugin landet im Plugins-Volume; das offizielle Image hat keinen Cron (ICS-Sync muss
  auf dem Host per systemd-Timer oder Host-Cron laufen).
- **Technik des Plugins:** PHP ≥ 8.1, Symfony-Bundle, Doctrine-Attribute, Twig, vendored UI-Kit
  (`Resources/views/_kit/`). Routen unter `/{_locale}/holiday/...`, API unter `/api/holiday`.
  Eigene Migrationstabelle `bundle_migration_holiday`, Install-Command
  `kimai:bundle:holiday:install`.
- **Lizenz:** GPL-3.0-or-later; der Fork bleibt GPL. Für den internen Betrieb bei zafe ohne
  Weitergabe ist das ohne Folgen. Copyright-Hinweise bleiben, eigene Änderungen werden als zafe
  gekennzeichnet.
- **Tests:** Das Ursprungsplugin hat **keine** Unit-Tests, nur `php -l`, einen Übersetzungs-Check
  und einen CI-Smoke-Test (klont Kimai 2.67, installiert das Plugin, ruft fünf Seiten auf). Der
  Fork braucht ein lauffähiges PHPUnit-Setup, bevor Fachlogik verändert wird.
- **Team:** 4 Nutzer. Performance ist kein Thema; Korrektheit und Nachvollziehbarkeit schon.
- Sprache der UI: Deutsch und Englisch (Plugin hat beide Übersetzungen, der Check erzwingt
  Schlüsselgleichheit). Neue Texte in beiden Sprachen.

## 4. Fachmodell

### 4.1 Vertrag (bestehend, Kimai-Core)

Der Vertrag ist Kimais eingebautes Arbeitszeitmodell in den Benutzer-Einstellungen: Modus
„Stunden je Wochentag" mit Soll je Mo–So, Urlaubstage pro Jahr, Feiertagsgruppe,
Vertragsbeginn/-ende. Das Plugin ergänzt nur Felder, keine eigene Tabelle.

**Fixe Arbeitstage sind die Wochentage mit Soll > 0.** Für A: Mi–Sa je 7 h (Mi–Fr 14–21) bzw.
9 h (Sa 12–21). Für B: Fr+Sa. Für die Chefs: nach ihrem tatsächlichen Vertrag; wenn sie keinen
pflegen wollen, 0 h überall (dann gelten für sie nur Aushilfe-Einträge).

Die Regel „ist Datum X für User Y ein Arbeitstag" gilt überall gleich und lautet:
**Soll laut Vertrag für diesen Wochentag > 0, innerhalb Vertragsbeginn/-ende, kein ganztägiger
Feiertag der Gruppe, dann Tagesverschiebungen angewandt.** Der harte Sa/So-Check des
Ursprungsplugins (`AbsenceWorkdayHelper::isWeekend`, Kalender-Schattierung, Feed) entfällt
vollständig. Die bestehende zentrale Stelle `UserWorkContract::getExpectedSecondsForDate()` bleibt
der einzige Chokepoint und bekommt die Verschiebungen dazu.

### 4.2 Urlaubskonto

**Das Urlaubsjahr ist das Arbeitsjahr ab Eintrittsdatum** (Betreiber-Entscheidung, österreichische
Regel): Für einen Eintritt am 15.03.2024 laufen die Urlaubsjahre 15.03.2024–14.03.2025,
15.03.2025–14.03.2026 usw. Das Ursprungsplugin rechnet in Kalenderjahren mit 1/12-Anteiligkeit;
das wird ersetzt. Das Eintrittsdatum ist Kimais Vertragsfeld „Beginn" (`workStartingDay`) und
damit Pflicht für jeden, der Urlaub hat.

**Der Anspruch ist in Arbeitstagen dieser Person** einzutragen (Betreiber-Entscheidung): A mit
4 Tagen/Woche bekommt z. B. 20, B mit 2 Tagen/Woche 10. Keine Umrechnung durch das Plugin.

Je Mitarbeiter und Urlaubsjahr:

```
Anspruch      = Urlaubstage/Jahr laut Vertrag (voll je begonnenem Urlaubsjahr; bei Austritt
                innerhalb des Jahres anteilig nach vollen Monaten, auf halbe Tage aufgerundet)
Übertrag      = Saldo des vorigen Urlaubsjahres (neu; rekursiv ab Eintrittsdatum)
Korrekturen   = Summe manueller Buchungen (bestehend, Art „holiday", Tage ±) mit Buchungsdatum
                im Urlaubsjahr
Verbraucht    = genehmigte Urlaubs-Abwesenheiten mit Datum im Urlaubsjahr, gezählt in
                Arbeitstagen der Person (halbe Tage zählen 0,5)
Saldo         = Anspruch + Übertrag + Korrekturen − Verbraucht
```

**Übertrag** (neu): Der Saldo des vorigen Urlaubsjahres wird automatisch Teil des nächsten. Es
gibt keine Verfallsregel im Code; wer verfallenen Urlaub ausbuchen will, macht eine negative
manuelle Buchung mit Kommentar. *(Meine Entscheidung: automatischer Übertrag ohne Kappung,
Verfall per Hand — bei vier Personen ist die Handbuchung billiger als eine Verfallslogik, deren
Regeln niemand genannt hat.)*

**Anfangssaldo / Nachtrag** (Betreiber-Entscheidung): Es gibt kein Feld „Anfangssaldo". Beim
Rollout wird je Person das Eintrittsdatum gesetzt, und die seit Eintritt genommenen Urlaube
werden nachträglich als genehmigte Abwesenheiten erfasst; der Resturlaub ergibt sich daraus. Dafür
muss ein Genehmiger Abwesenheiten **für andere und in der Vergangenheit direkt als genehmigt**
anlegen können (Upstream kann „für andere anlegen", die Sperrprüfung gegen Kimais
Arbeitszeit-Freigabe und Monatssperren bleibt — beim Rollout wird geprüft, dass keine
Freigaben bestehen, die den Nachtrag blockieren). Feiertage der Gruppe müssen für die
Vergangenheit importiert sein (`icsFromYear` = Jahr des frühesten Eintritts).

Angezeigt wird das Konto auf der Abwesenheitsseite als Kacheln je Urlaubsjahr („Urlaubsjahr
15.03.2026–14.03.2027"), ergänzt um „Übertrag aus Vorjahr", mit Navigation zu früheren
Urlaubsjahren.

### 4.3 Abwesenheiten (bestehend, angepasst)

Typen bleiben: Urlaub, Krank, Krank (Angehöriger), Zeitausgleich, Sonstiges. Status bleibt:
beantragt → genehmigt / abgelehnt. Krankheit ohne Genehmigung. Halbe Tage bleiben.

**Geändert:**

- Zählung und Timesheet-Anlage nutzen die Arbeitstag-Regel aus 4.1 (Samstag zählt, wenn Soll).
- **Benachrichtigung an alle Genehmiger.** Bei jedem Antrag und jeder Entscheidung geht eine
  E-Mail an den Antragsteller **und an alle Konten, die diesen Antrag genehmigen dürften**
  (Berechtigung `approve_other_absence` plus Kimai-`access_user` auf den Antragsteller), nicht
  nur an den im Profil hinterlegten Vorgesetzten. Die Entscheidungs-Mail nennt, wer entschieden
  hat. So sieht der zweite Chef jede Entscheidung des ersten.
- **Zweiter Kanal ohne Mail (Pflicht):** Das Prod-Kimai hat heute keinen Mailversand
  (`MAILER_URL=null://`). Die Navigation zeigt Genehmigern einen Zähler „offene Anträge"
  (Abwesenheiten + Verschiebungen aller Nutzer, auf die sie Zugriff haben), und die
  Abwesenheitsseite bekommt den Reiter „Offene Anträge". SMTP wird beim Rollout eingerichtet
  (Zugangsdaten vom Betreiber), aber das System darf nicht davon abhängen.
- **Niemand genehmigt sich selbst** (Betreiber-Entscheidung): Die Berechtigung
  `approve_own_absence` wird aus allen Rollen-Defaults entfernt, und der Service lehnt
  Genehmiger = Antragsteller ab — auch für Admins. Bei zafe genehmigt damit immer der jeweils
  andere Chef. Das gilt ebenso für Verschiebungen.
- **Chefs haben ein eigenes Urlaubskonto** (Betreiber-Entscheidung): Sie pflegen einen Vertrag
  mit Arbeitstagen und Urlaubsanspruch wie alle anderen; ihre Abwesenheiten laufen durch denselben
  Antrag. Für Mitarbeiter sind Chef-Abwesenheiten nur Information (sichtbar in Teamkalender und
  Verfügbarkeitsansicht, siehe 4.7), nicht genehmigbar.
- Die Einstellung „halbe Urlaubstage erlauben" wird durchgesetzt (heute definiert, aber
  wirkungslos). *(Meine Entscheidung: durchsetzen statt entfernen, der Schalter ist schon da.)*

### 4.4 Tagesverschiebung (neu)

Eine Verschiebung sagt: „Am Datum F (laut Vertrag Arbeitstag) arbeite ich nicht, dafür am Datum T
(laut Vertrag frei)". F und T liegen in derselben Kalenderwoche (Mo–So). *(Meine Entscheidung:
dieselbe Woche, weil der Betreiber „seine Tage verschieben" sagte und ein Wochenwechsel sonst
Sollstunden zwischen Monaten verschiebt; wer über Wochen hinweg tauschen will, nutzt Urlaub plus
Aushilfe.)*

- Felder: Nutzer, von-Datum, nach-Datum, Status (beantragt/genehmigt/abgelehnt), Kommentar,
  erstellt am, entschieden von/am.
- Antrag durch den Nutzer selbst oder einen Genehmiger für ihn. Genehmigung wie bei
  Abwesenheiten (gleiche Berechtigungslogik, gleiche Benachrichtigung an alle Genehmiger).
- Prüfungen: F ist Arbeitstag laut Vertrag und nicht bereits verschoben; T ist kein Arbeitstag
  laut Vertrag, kein ganztägiger Feiertag und **ein Öffnungstag des Standorts der Person**
  *(meine Entscheidung: Backoffice-Tage an Schließtagen sind kein Planungsziel, siehe
  Nicht-Ziele)*; keine Überlappung mit einer genehmigten Abwesenheit an F oder T; keine
  Verschiebung in einen gesperrten Monat.
- Mitarbeiter B hat laut Betreiber feste Tage; Verschiebungen sind der Ausnahmefall, das
  Antragsmodell ist also nicht zu schwer.
- Wirkung nach Genehmigung: F hat Soll 0 und ist kein Arbeitstag; T hat das Soll von F und ist
  Arbeitstag. Das gilt für Urlaubszählung, Timesheet-Anlage, Arbeitszeit-Jahresansicht,
  Kalender, ICS-Feed und Verfügbarkeitsansicht gleichermaßen (über den Chokepoint aus 4.1).
- Stundenneutral: das Wochensoll ändert sich nicht.
- Nur Verschiebungen innerhalb eines Standorts; der Standort kommt aus der Nutzerzuordnung.

### 4.5 Aushilfe (neu)

Ein Eintrag „Nutzer N ist am Datum D zusätzlich da", ohne Sollstunden-Wirkung. Dient der
Verfügbarkeitsansicht, nicht der Zeiterfassung. Kein Genehmigungsstatus: Ein Chef trägt sich
selbst ein oder trägt jemanden ein; ein Mitarbeiter darf sich selbst eintragen, und das ist
sofort sichtbar. *(Meine Entscheidung: ohne Genehmigung, weil der Betreiber Genehmigung nur für
Urlaub und Verschiebung verlangt hat und eine Aushilfe nie jemandem etwas wegnimmt.)*

Felder: Nutzer, Datum, Kommentar, erstellt von/am. Prüfung: D ist nicht bereits Arbeitstag der
Person (dann wäre der Eintrag sinnlos) und keine genehmigte Abwesenheit an D.

Ob die Aushilfe auch ein Timesheet oder Sollstunden erzeugen soll, bleibt offen; erste Version
nein.

### 4.6 Standort (neu)

- Felder: Name, Feiertagsgruppe (Pflicht), aktiv ja/nein.
- Öffnungszeiten: je Wochentag von–bis oder geschlossen. Für Wien: Mi–Fr 14:00–21:00,
  Sa 12:00–21:00, Mo/Di/So geschlossen.
- **An gesetzlichen Feiertagen ist der Standort geschlossen** (Betreiber-Entscheidung): Ein
  ganztägiger Feiertag der Standort-Feiertagsgruppe ist kein Öffnungstag.
- Ausnahmen je Datum (Sonderöffnung, Sonderschließung) mit optionalen Zeiten. *(Meine
  Entscheidung: in der ersten Version dabei, damit ein einzelner Sondertag nicht das
  Wochenmuster verbiegt; eine Ausnahme „geöffnet" kann auch einen Feiertag öffnen.)*
- Jeder Nutzer gehört zu genau einem Standort (eigene Zuordnungstabelle, nicht Kimai-Preference,
  weil die Core-Preferences des Plugins bereits Kimai-Felder sind). Nutzer ohne Standort
  erscheinen in keiner Verfügbarkeitsansicht.

### 4.7 Verfügbarkeitsansicht (neu)

Eine Seite je Standort, Zeitraum Woche oder Monat, Navigation vor/zurück und „heute".

Je Öffnungstag eine Spalte (Monatsansicht) bzw. je Tag eine Karte (Wochenansicht) mit:

- Öffnungszeit des Tages (oder „geschlossen" / Feiertagsname).
- Liste der anwesenden Personen. Anwesend = (Arbeitstag laut 4.1 nach Verschiebung ODER
  Aushilfe) UND keine genehmigte ganztägige Abwesenheit. Bei halber Abwesenheit: anwesend mit
  Hinweis „halber Tag".
- Je Person ein Vermerk: „fix", „verschoben von <Datum>", „Aushilfe".
- Noch nicht entschiedene Anträge (Abwesenheit oder Verschiebung), die den Tag betreffen, als
  „offen" sichtbar, damit beide Chefs beim Entscheiden sehen, was es für den Tag bedeutet.
- Darunter „Abwesend: …" mit allen Personen des Standorts, die an diesem Tag eine genehmigte
  Abwesenheit haben — unabhängig davon, ob der Tag für sie ein Arbeitstag wäre. So sind auch
  Chef-Urlaube für alle sichtbar (Betreiber: „informell für alle anderen").
- **Unbesetzt:** geöffneter Tag ohne anwesende Person — deutlich markiert (Farbe plus Text plus
  Symbol, nie nur Farbe).

Zugriff: jeder Nutzer mit Zugriff auf den Standort darf die Ansicht sehen (auch Mitarbeiter,
damit B sieht, wann A fehlt). Bearbeiten (Aushilfe eintragen, Standort pflegen) nach
Berechtigung.

Die Ansicht ist server-gerendert in Twig wie der bestehende Teamkalender; der Teamkalender bleibt
daneben bestehen (Jahres-/Monatsmatrix User × Tage) und bekommt Verschiebungen und Aushilfen als
eigene Zellsymbole.

### 4.8 Feiertage (bestehend, angepasst)

- Gruppe „Österreich" aus dem Katalog (`de.austrian` Google-Feed). Wien hat keine zusätzlichen
  gesetzlichen Feiertage; der Bundesfeed reicht.
- **Neu: Ausschlussliste je Gruppe** (Namen, die beim Import übersprungen werden), vorbelegt
  für Österreich mit den Gedenktagen des Google-Feeds (z. B. Heiliger Abend, Silvester,
  Muttertag, Vatertag, Valentinstag, Josefstag, Florian, Rupert, Leopoldi, Martini, Karfreitag
  — die genaue Liste ergibt sich aus dem realen Feed und wird beim Bauen aus dem Feed gezogen und
  gegen die gesetzlichen Feiertage Österreichs abgeglichen).
- **Neu: Gelöschte importierte Einträge kommen beim nächsten Sync nicht zurück** (heute prüft
  der Import nur auf das Datum). Umsetzung: beim Löschen eines importierten Feiertags wandert
  der Name in die Ausschlussliste der Gruppe.
- Feiertag bearbeiten (heute nur anlegen/löschen), damit „halber Tag" nachträglich gesetzt werden
  kann.
- ICS-Sync läuft täglich per Host-Timer (`docker exec kimai … kimai:bundle:holiday:sync-ics`).

## 5. Entschiedene Technik

- **Fork** als eigenes Repo `zafe-apps/kimai-holiday-bundle` (GitHub, Organisation `zafe-apps`,
  `gh` ist dafür angemeldet). Upstream bleibt als Remote `upstream` eingetragen, damit
  Bugfixes von dort nachgezogen werden können. Bundle-Name und Ordner `HolidayBundle` bleiben,
  damit Migrations-Historie und Routen kompatibel bleiben.
- **Erst Tests, dann Fachlogik.** PHPUnit läuft in einem Kimai-Checkout (wie der bestehende
  CI-Smoke-Test, Kimai 2.70.2 statt 2.67): Plugin nach `var/plugins/HolidayBundle`, Tests des
  Plugins unter `tests/` mit Kimais Test-Bootstrap. Reine Fachlogik (Arbeitstag-Regel,
  Urlaubssaldo mit Übertrag, Verschiebungs-Prüfung, Anwesenheits-Berechnung) wird in Services
  ohne Controller-Abhängigkeit gekapselt und mit Unit-Tests abgedeckt; Controller/API über
  Kimais `WebTestCase`. Lokal: Docker-Compose mit Kimai 2.70.2 + MariaDB zum Entwickeln und
  Testen, Skript im Repo.
- **Datenmodell:** neue Tabellen im bestehenden Präfix `kimai2_ext_holiday_*`:
  `workday_shift`, `extra_shift` (Aushilfe), `location`, `location_opening_hours`,
  `location_exception`, `location_user`; `ph_group` bekommt die Ausschlussliste (JSON-Spalte
  oder Kind-Tabelle, Wahl des Bauers). Migrationen im Plugin-Stil mit `hasTable`-Guards.
- **Chokepoint:** Verschiebungen wirken ausschließlich über `UserWorkContract` ⇒
  `getExpectedSecondsForDate()`; zusätzlich im `WorkingTimeYearSubscriber` das Soll per
  `setExpectedTime` korrigieren, weil Kimais Jahresansicht für vergangene Tage nicht durch das
  Plugin rechnet. Die Duplikate des Ursprungsplugins (`absenceSeconds`, Feiertags-Reduce in
  Calculator und Subscriber) werden dabei zusammengeführt, damit die Regel nur einmal steht.
- **Berechtigungen:** neue Rechte im bestehenden Muster (`HolidayExtension` prepend und
  `PermissionSectionSubscriber`): `view_location`, `edit_location`, `shift_own`, `shift_other`,
  `approve_shift`, `extra_shift_own`, `extra_shift_other`, `view_availability`. Rollen-Defaults:
  USER darf eigene Verschiebung und Aushilfe beantragen und die Verfügbarkeit sehen; ADMIN alles.
  Die Chefs sind Admins. Der Nachweis, dass die Genehmigungs-Rechte wirklich greifen (laut
  Upstream-README nicht verifiziert), ist Teil der Tests.
- **API:** die neuen Objekte bekommen Endpunkte im bestehenden Stil unter `/api/holiday`
  (Lesen und Anlegen/Entscheiden), damit später z. B. das BOC oder ein Bot darauf zugreifen kann.
  Keine Pflicht für die erste Version, aber vorgesehen.
- **Erstinstallation des Plugins, Kimai-Daten bleiben** (Betreiber-Entscheidung): Auf Prod ist
  heute kein Plugin installiert (geprüft 2026-10-23). Es gibt keine Altdaten des
  Upstream-Plugins; dessen Migrationen dürfen frei umgebaut werden, die Samstag-Regel und das
  Arbeitsjahr wirken auf nichts rückwirkend. **Alle bestehenden Kimai-Daten — Zeiterfassungen,
  Kunden, Projekte, Tätigkeiten, Konten, Einstellungen — bleiben vollständig erhalten.** Das
  Plugin legt nur eigene Tabellen (`kimai2_ext_holiday_*`) an und ergänzt Nutzer-Einstellungen;
  es verändert keine Kimai-Tabellen. Das Kimai-Update 2.58 → 2.70.2 läuft über Kimais eigene
  Migrationen gegen dieselbe Datenbank, mit Backup davor und Prüfung danach (Anzahl Nutzer,
  Zeiterfassungen und Kunden vor/nach dem Update identisch). Vier Konten existieren (zwei
  Super-Admins = die Chefs, zwei Nutzer), alle mit E-Mail-Adresse.
- **Rollout:** 1) DB-Backup (`mariadb-dump` aus `kimai-db`) und Snapshot der Volumes;
  2) Compose auf `kimai/kimai2:apache-2.70.2` pinnen (kein unversionierter Tag mehr),
  `docker compose pull && up -d`, Kimai-Migrationen laufen beim Start; 3) SMTP in der
  Compose-Umgebung (`MAILER_URL`, `MAILER_FROM`) mit Zugangsdaten vom Betreiber, Testmail;
  4) Plugin-Release-Zip ins Volume `kimai_kimai_plugins`, `kimai:reload`,
  `kimai:bundle:holiday:install`; 5) Rollen, Feiertagsgruppe Österreich (Import ab dem Jahr des
  frühesten Eintritts), Standort Wien, Verträge der vier Personen mit Eintrittsdatum und
  Urlaubsanspruch; 6) Nachtrag der bisherigen Urlaube durch die Chefs; 7) Host-Timer für den
  ICS-Sync. Jeder Schritt mit Rückwegs-Beschreibung.
- **Releases** über GitHub-Releases als Zip (bestehendes `release.yml`), Versionsnummer im
  Plugin (`composer.json`) nach SemVer ab `2.0.0`, um sich vom Upstream `1.x` abzusetzen.

## 6. Offen für den Bauer

- Ob die Ausschlussliste der Feiertage JSON-Spalte oder Kind-Tabelle ist.
- Layout der Wochen- und Monatsansicht im Detail (Twig/Kit-Makros); Vorgabe nur: Tag, Öffnungszeit,
  Personen mit Vermerk, offene Anträge, Unbesetzt-Markierung.
- Reihenfolge der Umsetzungsschritte nach dem Test-Setup (Empfehlung: Wochenend-Regel und
  Übertrag zuerst, weil sie den Resturlaub betreffen; dann Benachrichtigung; dann Standort und
  Verfügbarkeit; dann Verschiebung; dann Aushilfe).
- Wie der lokale Dev-Stack genau aussieht (Compose-Datei im Repo, Seed-Skript für die vier
  Personen und den Standort).
- Ob Kimais FullCalendar zusätzlich die Verschiebungen als Quelle bekommt (nice to have).

## 7. Glossar

- **Arbeitstag:** Tag mit Soll > 0 laut Vertrag nach Feiertag und Verschiebung.
- **Öffnungstag:** Tag, an dem der Standort laut Öffnungszeiten (nach Ausnahmen und Feiertagen)
  geöffnet ist.
- **Anwesend:** Person, die an einem Tag Arbeitstag oder Aushilfe hat und keine genehmigte
  ganztägige Abwesenheit.
- **Unbesetzt:** Öffnungstag ohne anwesende Person.
- **Genehmiger:** Konto mit `approve_other_absence` bzw. `approve_shift` und Kimai-Zugriff auf den
  Antragsteller. Bei zafe: die beiden Chefs (Admins).

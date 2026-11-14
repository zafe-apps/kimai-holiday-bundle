# Zeitnachträge mit Genehmigung — Design

Stand 2026-11-14. Ausgangspunkt: Plugin 2.5.0, Kimai 2.70.2.

## Absicht

Zeiten, die lange nach dem Arbeitstag angelegt, geändert oder gelöscht werden, sind schwer zu prüfen.
Solche Nachträge sollen deshalb durch einen Teamlead freigegeben werden, und die Teamleads sollen davon
per Mail erfahren, so wie bei einem Urlaubsantrag.

## Ziele

- Legt jemand einen Zeiteintrag an, ändert oder löscht ihn, und der Tag des Eintrags liegt mehr als
  14 Tage zurück, entsteht ein offener Nachtrag.
- Die Person arbeitet im normalen Kimai-Formular weiter. Die Änderung ist sofort gespeichert.
- Die zuständigen Teamleads bekommen eine Mail und entscheiden auf der Seite „Offene Anträge".
- Bei Ablehnung stellt das Plugin den Stand vor der Änderung wieder her.

## Nicht-Ziele

- Kein eigenes Formular für Nachträge, kein Begründungsfeld (die Beschreibung des Eintrags reicht).
- Keine API unter `/api/holiday/…` für Nachträge.
- Keine Mail an die Person selbst (wie bei Urlaub: sie sieht den Stand in Kimai).
- Zeiten, die verschwinden, weil ein Admin Benutzer, Projekt, Tätigkeit oder Kunde löscht (Kimai
  räumt dort ohne Ereignis per DQL/Kaskade auf), sind kein Nachtrag. *(Annahme des Entwurfs.)*
- Wechselt ein alter Eintrag nur den Besitzer (Sammelaktion, nur mit Recht auf fremde Zeiten), ist
  das kein Nachtrag. *(Annahme des Entwurfs.)*

## Anti-Ziele

- Ein Nachtrag darf nie still verschwinden: keine Änderung an einem alten Tag ohne Antrag oder
  ohne sofortige Freigabe durch einen Zuständigen.
- Niemand gibt eigene Nachträge frei.
- Zeiten, die das Plugin selbst schreibt (Abwesenheits-Einträge), erzeugen keine Nachträge.

## Ansätze

1. **Sofort speichern, als offen markieren, bei Ablehnung zurückstellen** — gewählt vom Betreiber am
   14.11.2026. Die Leute bleiben im gewohnten Formular; dafür zählt die Änderung bis zur Entscheidung
   schon im Saldo.
2. Speichern ablehnen und auf ein eigenes Formular „Nachtrag beantragen" verweisen. Saldo bleibt
   unberührt, aber umständlicher und ein zweites Zeitformular im Plugin.
3. Kimais eingebaute Sperrfrist (`lockdown_*`) mit Ausnahme-Recht für Teamleads. Kein Antrag, keine
   Mail: der Teamlead müsste alles selbst eintragen. Erfüllt die Absicht nicht.

## Vom Betreiber entschieden

- Variante 1.
- Frist 14 Tage, gerechnet ab dem Tag des Eintrags; in den Plugin-Einstellungen änderbar.
- Ändert ein Teamlead eigene alte Zeiten, entscheidet ein anderer Teamlead.
- Trägt ein Teamlead für jemanden aus seinem Team nach, gilt das sofort als freigegeben.
- Teamleads werden informiert wie beim Urlaub.

## Verhalten

### Wann entsteht ein Nachtrag

- Geprüft wird beim Anlegen, Ändern, Stoppen und Löschen eines Zeiteintrags, auch bei Sammelaktionen,
  Schnelleingabe, Duplizieren, Neustart und über die Kimai-API. Alle diese Wege laufen durch
  `TimesheetService` und seine Ereignisse: `TimesheetCreatePre`, `TimesheetUpdatePre`,
  `TimesheetStopPre`, `TimesheetDeletePre`, `TimesheetUpdateMultiplePre`,
  `TimesheetDeleteMultiplePre`. Die Schnelleingabe schickt auch **neue** Einträge durch
  `TimesheetUpdateMultiplePre` (dort `id === null`), ohne `TimesheetCreatePre`.
- Der alte Stand ist zum Pre-Zeitpunkt über `UnitOfWork::getOriginalEntityData` lesbar. Der neue
  Stand ist erst nach dem Flush endgültig (Dauer und Rundung rechnet Kimai in `onFlush`,
  `src/Doctrine/TimesheetSubscriber.php`): `after` und der Vergleich „wieder wie vorher" entstehen
  aus den Werten nach dem Flush.
- Maßgeblich ist der Tag des Beginns in der Zeitzone der Person. Bei einer Änderung zählen alter und
  neuer Tag: liegt einer davon vor der Grenze, ist es ein Nachtrag. Grenze: heute minus Frist; ein
  Eintrag genau am Grenztag ist noch frei.
- Bei Änderungen zählen nur Beginn, Ende, Dauer und Pause. Wer nur Beschreibung, Projekt, Tätigkeit
  oder Schlagworte ändert, braucht keine Freigabe. *(Annahme des Entwurfs.)*
- Ohne angemeldeten Benutzer (Konsole, Cron) entsteht nichts. Abwesenheits-Einträge schreibt das
  Plugin am `TimesheetService` vorbei, sie lösen also nichts aus; ändert jemand einen solchen
  Eintrag von Hand, entsteht ebenfalls nichts. Erkannt werden sie mit derselben Regel wie in
  `AbsenceTimesheetService` (Meta `holiday_absence_id`, Altbestand über Beschreibung plus
  Projekt/Tätigkeit).
- Frist 0 schaltet die Funktion ab.

### Der Nachtrag

Neue Tabelle `kimai2_ext_holiday_timesheet_change`:

- Person (Besitzer des Eintrags), Zeiteintrag (nullable, `SET NULL`), Tag, Art (`create`, `update`,
  `delete`), Status (`AbsenceStatus`), angelegt von, angelegt am, entschieden von, entschieden am
- `before` und `after` als JSON-Abbild des Eintrags: Beginn, Ende, Dauer, Pause, Zeitzone, Projekt,
  Tätigkeit, Beschreibung, Schlagworte, abrechenbar. `before` fehlt bei `create`, `after` bei `delete`.

Regeln:

- Ist der Handelnde Genehmiger der Person (`ApproverResolver::isApproverOf` mit dem neuen Recht
  `approve_timesheet_change`), wird der Nachtrag sofort als freigegeben gespeichert und die übrigen
  Teamleads bekommen die Freigabe-Mail. Sonst Status offen und Antrags-Mail.
- Gibt es für die Person keinen anderen Genehmiger (einziger Teamlead/Admin), wird der Nachtrag
  sofort als freigegeben gespeichert, ohne Entscheider. Sonst bliebe er für immer offen und würde
  den Monatsabschluss blockieren. *(Annahme des Entwurfs.)*
- Ändert jemand denselben Eintrag erneut, solange ein Nachtrag offen ist, bleibt es ein Nachtrag:
  `before` bleibt der erste Stand, `after` wird ersetzt, keine zweite Mail. Entspricht `after` danach
  wieder `before`, wird der Nachtrag gelöscht.
- Löscht jemand einen Eintrag, dessen Anlage noch offen ist, verschwindet der Nachtrag mit ihm.
- Nach dem Speichern sieht die Person einen Hinweis „Diese Änderung braucht die Freigabe eines
  Teamleads".

### Entscheidung

- Freigeben: Status freigegeben, Mail an die Teamleads. Am Eintrag ändert sich nichts.
- Ablehnen: `update` schreibt `before` zurück, `create` löscht den Eintrag, `delete` legt ihn aus
  `before` neu an. Danach Status abgelehnt, Mail an die Teamleads. Die Rückstellung selbst erzeugt
  keinen neuen Nachtrag.
- Ablehnen scheitert mit einer Meldung, wenn der Eintrag inzwischen exportiert ist, der Monat
  gesperrt ist (`PeriodLockGuard`) oder Projekt/Tätigkeit nicht mehr existieren.
- Weder Kimai noch das Plugin verhindern heute Zeitänderungen in einem gesperrten Monat; das bleibt
  so. Ein Nachtrag in einem gesperrten Monat lässt sich freigeben, zum Ablehnen muss der Monat erst
  entsperrt werden. *(Annahme des Entwurfs.)*
- Eigene Nachträge kann niemand entscheiden; die Aktionen sind dann nicht sichtbar und serverseitig
  verweigert.
- Solange für eine Person ein Nachtrag offen ist, lässt sich ein betroffener Monat nicht sperren.
  Der einzige Abschlussweg ist `ContractController::lockMonth` → `MonthLockService::lock`; er sperrt
  auch alle Vormonate des Jahres, die Prüfung umfasst sie deshalb mit. Kimais eigene
  Arbeitszeit-Freigabe lässt sich nicht aufhalten (`WorkingTimeApproveMonthEvent` kommt nach dem
  Speichern) und wird in dieser Installation nicht benutzt.

### Oberfläche

- Seite „Offene Anträge" (`absence/requests.html.twig`): dritte Tabelle „Zeitnachträge" mit Person,
  Tag, Art, vorher → nachher (Beginn–Ende, Dauer), angelegt am, Aktionen Freigeben/Ablehnen einzeln
  und als Sammelaktion; nur Kit-Komponenten.
- Der Zähler am Menüeintrag (`OpenRequestCounter`) zählt Nachträge mit.
- Einstellung „Zeitnachträge freigeben ab … Tagen" in den Plugin-Einstellungen
  (`SystemConfigurationSubscriber`, `HolidayConfiguration`), Standard 14.

### Rechte

Neues Recht `approve_timesheet_change` für `ROLE_TEAMLEAD`, `ROLE_ADMIN`, `ROLE_SUPER_ADMIN`
(`DependencyInjection/HolidayExtension.php`, `PermissionSectionSubscriber`). Zuständigkeit wie bei
Urlaub über Team-Zugehörigkeit (`ApproverAccess`).

### Mails

Über `AbsenceNotifier` an alle Genehmiger der Person, drei Ereignisse (beantragt, freigegeben,
abgelehnt), Text mit Person, Tag, Art und vorher → nachher. Texte in beiden Sprachen.

Schnelleingabe und Sammelaktionen berühren viele Einträge auf einmal: je Speichervorgang und Person
geht **eine** Mail mit allen betroffenen Einträgen hinaus, nicht eine je Eintrag. Dasselbe gilt für
Sammel-Freigabe und -Ablehnung. *(Annahme des Entwurfs.)*

## Dem Umsetzer überlassen

- Wie der alte Stand bei `update` gelesen wird (Doctrine `UnitOfWork::getOriginalEntityData` oder
  eigener Abzug) und wie die Rückstellung den eigenen Wächter umgeht.
- Ob der Hinweis nach dem Speichern als Flash-Meldung über die Session oder anders kommt; über die
  API entfällt er.
- Aufteilung in Klassen nach dem Muster von `WorkdayShiftService`, `WorkdayShiftPermissions`,
  `WorkdayShiftController`.

## Auslieferung

Version 2.6.0, eine neue Migration, `CHANGELOG.md`, `README.md`. Merge in `master` deployt nach
`zeiterfassung.zafe.at`; den Merge macht der Betreiber.

---
name: 0001-fork-statt-workcontractbundle
description: Das freie Plugin shrippen/kimai-holiday-bundle (GPL-3) wird geforkt, das WorkContractBundle (299 €/Jahr) nicht gekauft
metadata: { type: decision, date: 2026-10-23 }
---

Wir forken das freie Kimai-Plugin `shrippen/kimai-holiday-bundle` (GPL-3.0-or-later) als
`zafe-apps/kimai-holiday-bundle` und bauen darauf Urlaubskonto und Dienstplanung, statt das
kommerzielle WorkContractBundle (299 €/Jahr) zu kaufen. Bundle-Name und Ordner `HolidayBundle`
bleiben, Upstream bleibt als Remote `upstream`.

**Why:** Die Dienstplanung (Standort, Verfügbarkeit je Öffnungstag, Tagesverschiebung, Aushilfe)
fehlt im WorkContractBundle — es hätte die Lücke nicht geschlossen, und wir müssten sie dennoch
selbst bauen, ohne den Quelltext zu besitzen. Das freie Plugin deckt Abwesenheiten, Feiertage und
Genehmigung bereits ab, hat dieselbe Rechte-Benennung und ist unter der GPL frei erweiterbar.
Für den internen Betrieb bei zafe hat die GPL keine Folgen.

**How to apply:** Neue Fachlogik als Services mit PHPUnit-Tests im Fork; Berechtigungsnamen
kompatibel zum WorkContractBundle halten; Upstream-Bugfixes per Cherry-Pick nachziehen; die
beiden Plugins nie parallel installieren. Spec: `docs/superpowers/specs/2026-10-23-kimai-dienstplan-design.md`.

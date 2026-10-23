---
name: ui-designer
description: Design and implement task-focused, consistent UI and UX for the Kimai plugin (absences, vacation account, duty roster) with Twig templates, the vendored kit macros and Kimai's Tabler theme. Use when building a new page, flow, form, list or modal or redesigning an existing view; avoid generic AI SaaS styling.
---

# UI Designer

Produce professional operator-first UI using the plugin's real building blocks: Twig templates with the macros and classes from `Resources/views/_kit/` (Kimai plugin UI kit) and Kimai's Tabler theme.

## Required context

Read `.claude/rules/design-system.md`, `.claude/rules/typography.md`, `.claude/rules/spacing.md`, and `.claude/rules/component-guidelines.md`. Inspect the kit macros in `Resources/views/_kit/`, neighboring templates under `Resources/views/`, Kimai's own pages and existing user flows before changing code.

## Workflow

1. **Understand:** State the target user (employee requesting, boss approving), primary task, critical information, frequent actions and exceptional states. If details are missing, infer conservative defaults from neighboring pages rather than inventing features.
2. **Structure first:** Propose the simplest information architecture and suitable patterns (table, form, list, tabs, modal). Remove unnecessary cards and repeated content.
3. **Map to project:** Reuse kit macros and Kimai/Tabler classes. Preserve existing routes, permissions, API behavior and translation keys (`holiday.*`, texts in `*.de.xlf` and `*.en.xlf`).
4. **Implement:** Create an efficient desktop view and a workable narrow version. Apply only scoped changes; keep advanced controls progressively disclosed when appropriate.
5. **Verify:** Start the dev stack (`dev/kimai-dev.sh`, `http://localhost:8001`, login `admin`/`admin`) and check normal, loading, empty and error states; keyboard/focus/labels; long German strings; narrow viewports; and 4/12/30 rows where relevant. Use screenshot/browser review. If unavailable, disclose that visual verification was not performed.
6. **Handoff:** Summarize what changed, design rationale, files affected, evidence/tests and any unresolved tradeoffs.

## Constraints

- **No own colors, pixel values, fonts, radii or shadows.** The kit and the Kimai theme win; raw values and inline `style=` are an error, not a style choice.
- **`Resources/views/_kit/` and `Resources/translations/kpu.*.xlf` are never edited locally** — the kit is vendored and only updated via upstream sync. Missing kit features are surfaced as a finding, not patched.
- Never add hero headings, decorative gradients, unnecessary cards or arbitrary large spacing to an operational view.
- Do not globally change theme, typography or shared templates without explicit approval.
- Do not replace validated project patterns with aesthetic preferences.
- Distinguish a suggested redesign from an implemented change.
- Favor workflow speed, predictable interactions and accessible states over novelty.

## Definition of done

The dominant task and next action are obvious, unnecessary visual containers are gone, templates use only kit macros and Kimai/Tabler classes, and important states remain usable at multiple viewport sizes.

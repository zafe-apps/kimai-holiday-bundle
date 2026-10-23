# Component and Interaction Guidelines

## Reuse first

1. Check `Resources/views/_kit/` (vendored kit macros), Kimai's Tabler components and neighboring plugin templates.
2. Reuse an existing macro or Kimai component if it supports the task.
3. Compose existing primitives before authoring a new reusable partial.
4. Add dependencies (CSS/JS libraries) only with a compelling and documented reason; Kimai ships its own assets.
5. The kit is vendored: never edit `Resources/views/_kit/` or `Resources/translations/kpu.*.xlf`. A missing kit feature is a finding, not a local patch.

## Choose the right structure

- **Table** (Kimai data table with row menu) for comparable entities: absences, requests, public holidays, availability per opening day.
- **List** for simple ordered items or compact summaries.
- **KPI tile** only for a key fact (remaining vacation, open requests); avoid card-in-card nesting.
- **Tabs** for distinct sibling views, not to hide small sets of related inputs.
- **Collapsible group** for infrequent advanced configuration; preserve discoverability.
- **Kimai modal** for focused edit, confirmation or troubleshooting tasks; avoid full multi-step workflows inside a small modal.
- **Detail page** when contextual editing needs room (duty roster, location setup).

## Actions

- One clear primary action per functional area; secondary actions should look secondary.
- Frequent actions visible; low-frequency destructive/maintenance actions in the row menu.
- Approve/reject act immediately and offer undo where the plugin supports it; destructive actions (delete) get a specific label and a confirmation modal, not a generic "Are you sure?".
- Keep save/cancel placement consistent across forms and modals.
- Display loading progress and actionable failure messages for asynchronous operations.
- Every state-changing action is a POST with CSRF token and a permission check on the server; hiding a button is never the protection.

## Forms

- Labels always visible; communicate required/optional status consistently.
- Group by mental model: period and type, reason, approval, advanced.
- Use sensible defaults (e.g. full day, current year); make defaults discoverable and editable.
- Show validation near affected fields with concrete remediation (translated, de and en).
- Do not overwhelm with all options at once; avoid hiding options essential to the task.

## Tables and lists

- Use stable row identity and consistent alignment.
- Put name/person first; status (requested/approved/rejected) as badge with text, plus actionable warnings near it.
- Row actions through the Kimai row menu; avoid repeating the same large button in every row.
- Handle empty periods, rejected or exported entries, users without contract data and loading explicitly.
- Design and test with 4 users, 12 rows and a full year of absences.

## Accessibility and responsiveness

- Use semantic controls, keyboard-focusable actions, descriptive accessible names and visible focus states.
- Supply tooltips as supplementary information, never the sole source of essential instructions.
- Do not encode status solely in color or icon.
- Test narrow viewports, zoom, keyboard navigation and long German strings.
- Prefer the kit's and Tabler's accessible patterns; verify custom markup carefully.

## No gratuitous refactors

When implementing a localized request, do not redesign the navigation, change global typography or rewrite unrelated templates. Surface broader issues separately and request approval before extensive changes.

# Product UI Design System

## Purpose

Build a mature, efficient management UI inside Kimai for absences, vacation account and duty roster of a small team. Operators are the bosses who approve and the employees who request; optimize for their daily use, not for marketing screenshots. Avoid generic AI-generated SaaS aesthetics.

## Hard rule: kit and theme win

- UI is built only from the macros and classes in `Resources/views/_kit/` (Kimai plugin UI kit) and Kimai's Tabler theme.
- **No own colors, pixel values, fonts, radii or shadows.** No inline `style=`, no hex values, no new font. Where this file or a rule names a number, the theme token or kit macro still takes precedence; a raw value is an error.
- `Resources/views/_kit/` is vendored and never edited locally (updated only by upstream sync).

## Priority order

1. User task and correctness
2. Existing product patterns (Kimai, kit) and accessibility
3. Information hierarchy and interaction efficiency
4. Visual consistency
5. Decoration (only when justified; usually not at all)

## Before changing UI

- Inspect neighboring templates, kit macros and Kimai's own pages.
- Preserve Kimai's look. Do not introduce a new visual identity.
- Identify the primary task, top 3 user actions, essential states and decision-critical data.
- Prefer extending an existing pattern over creating a new partial.
- Do not modify unrelated pages or shared templates without explicit instruction.

## Visual principles

- Neutral, restrained surfaces; use the theme's accent only for primary actions, selections and important highlights.
- Distinguish content through hierarchy, alignment and spacing, not through cards around everything.
- Avoid gradients, glassmorphism, ornamental icons, glowing effects, exaggerated shadows and oversized hero titles.
- Use typography and spacing from `typography.md` and `spacing.md` as orientation; theme tokens decide.
- A table or simple list often works better than a gallery of tiles for operational data.
- Keep status colors semantic (success/warning/error/info) and always include a textual/iconographic cue. Never communicate a status by color alone.

## Layout patterns

- **Overview:** compact page header with contextual actions; period navigator and filters; primary data surface.
- **Detail:** identity/status/actions at top, then logically ordered sections, usually with a focused edit modal.
- **Configuration:** show frequent options first; group advanced settings under meaningful progressive disclosure.
- **Dense lists:** support scanning, sorting, filtering and empty states when genuinely needed.

## Product-specific guidance

- Absences and requests: person, period, type and status first; approve/reject next to the request; the remaining vacation is always visible.
- Vacation account: entitlement, carry-over, taken, requested and remaining per vacation year, with the year boundary explained.
- Duty roster / availability: surface per opening day who is there and who is missing; hide infrequent technical options behind advanced sections.
- Approver view: what needs a decision now (open requests with count) before history.
- Design for 4 users, 12 and 30+ entries without forcing oversized tiles.

## Quality gates

- Clear first action within 3 seconds of viewing the page.
- Every visible element has a function; remove redundant headings, explanatory prose and duplicate actions.
- All common states are considered: loading, empty, success, error, disabled and long text.
- Verify keyboard navigation, visible focus, accessible labels, contrast, responsive overflow and touch target sizes.
- Inspect the rendered UI (`dev/kimai-dev.sh`, `http://localhost:8001`) at desktop and narrow widths, in `de` and `en`. Do not claim a visual check without screenshots/browser evidence.

## Authority

Kimai theme and kit take precedence over these baseline rules. If a rule conflicts with a product-specific requirement, explain the exception rather than silently overriding.

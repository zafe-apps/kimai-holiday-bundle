# Spacing and Density Rules

**Kimai theme tokens and kit macros always take precedence. Raw pixel values in plugin templates are an error** — use the spacing utilities and components that Tabler/the kit provide. The table below is orientation for judging whether a layout is too loose or too tight, not a source of values to type in.

| Step | Approx. | Typical use                             |
| ---- | ------: | --------------------------------------- |
| 1    |     4px | icon/text micro-gap                     |
| 2    |     8px | label-to-control, small inline gap      |
| 3    |    12px | compact component gap                   |
| 4    |    16px | typical panel padding / field grouping  |
| 5    |    24px | separation of related sections          |
| 6    |    32px | major page section separation           |

## Default density for desktop operations

- Page padding and panel padding: whatever the Kimai page layout gives; do not add wrappers to widen it.
- Form row gap: compact (about 12-16px); label-to-control gap small (4-8px).
- Table row height: Kimai data table default (about 40-48px).
- Section gap: 24-32px, never "marketing" spacing.
- Toolbar actions: group tightly; larger gap only between unrelated action groups.

## Radius/elevation

- Never set radius or shadow in plugin code; the theme decides.
- Floating elements (menus, modals) get elevation from Kimai's components, not from custom CSS.

## Responsive

- Preserve visual grouping on narrow screens by stacking thoughtfully, not by making every element full-width.
- Prevent horizontal page scrolling; data tables scroll within their own bounded region when necessary.
- Touch targets on mobile: use Kimai's default controls, which are sized for touch.

## Review

Look for arbitrary values like 27px, repeated giant padding, inline `style=` and multiple nested borders. Replace them with kit macros or theme utilities.

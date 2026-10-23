# Typography Rules

Use Kimai's font family and weights. Never add a font. **Kimai theme tokens and kit macros always take precedence over the numbers below; typing raw font sizes or weights into plugin templates is an error.** The table is orientation for judging hierarchy.

## Operational UI type scale (orientation only)

| Element             |    Size |  Weight |
| ------------------- | ------: | ------: |
| Page title          |    24px |     600 |
| Section heading     |    18px |     600 |
| Panel heading       | 15–16px |     600 |
| Body / form input   |    14px |     400 |
| Table cell          | 13–14px |     400 |
| Field label         | 12–13px |     500 |
| Secondary/help text | 12–13px |     400 |
| Button text         | 13–14px | 500–600 |

Avoid going below 12px for critical interactive content.

## Rules

- One page-level heading per screen (Kimai's page header); section headings are subordinate.
- Avoid all-caps labels except short badges the kit already provides.
- Never use oversized marketing headlines in plugin pages.
- Reserve bold text for hierarchy and critical information; avoid bolding every row or value.
- Prefer concise, explicit labels (`Resturlaub`, `Urlaubsjahr`, `Genehmigt von`) over decorative descriptions.
- Align numeric columns and times consistently (days, hours); use tabular numerals if the theme provides them.
- Truncate only when full text is accessible via expansion or an accessible tooltip.
- Maintain legible contrast and sizing at 200% zoom; do not rely on placeholders as form labels.

## Implementation

Use the kit's and Kimai's typography classes. If something is missing, surface it as a finding instead of hard-coding font-size values. Validate wrapped headings and long German labels in both `de` and `en`.

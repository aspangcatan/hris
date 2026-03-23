# HRIS Design System

## Purpose
This document captures the current UI language used in the HRIS app so new work matches the existing look and feel. It is grounded in the live layout and page styles already in the codebase. Do not introduce new colors or patterns unless they already exist in this repository.

## Sources Of Truth
- Global theme tokens and component refinements: `resources/views/master.blade.php`
- List view styling: `resources/views/saln.blade.php`, `resources/views/layouts/saln_styles.blade.php`
- Non-list view styling: `resources/views/profile.blade.php`
- Base template styles: `public/assets/css/style.css`

## Frameworks And Libraries
- Laravel 8 + Blade templates
- Bootstrap 5 (layout, components, utilities)
- Tailwind CDN utilities (used heavily in `profile.blade.php`)
- Icons: Bootstrap Icons, Font Awesome

## Typography
- Primary font: Plus Jakarta Sans (loaded in `resources/views/master.blade.php`)
- Headings inherit theme color `--text-primary` with heavier weights
- Page title: `.pagetitle h1` uses 1.55rem, weight 800, slight letter spacing

## Color Tokens
Global tokens (`resources/views/master.blade.php`):
- `--app-bg`: `#eef3f9`
- `--surface`: `#ffffff`
- `--surface-soft`: `#f8fafc`
- `--text-primary`: `#0f172a`
- `--text-secondary`: `#475569`
- `--line`: `#dbe3ee`
- `--brand-600`: `#0f766e`
- `--brand-500`: `#0ea5a4`
- `--brand-100`: `#ccfbf1`
- `--shadow-soft`: `0 14px 36px rgba(2, 32, 71, 0.08)`
- `--shadow-card`: `0 18px 44px rgba(15, 23, 42, 0.08)`

SALN tokens (`resources/views/layouts/saln_styles.blade.php`):
- `--saln-primary`: `#0f766e`
- `--saln-primary-dark`: `#115e59`
- `--saln-primary-light`: `#ccfbf1`
- `--saln-text-main`: `#0f172a`
- `--saln-text-muted`: `#64748b`
- `--saln-bg-soft`: `#f8fafc`

Color usage rules:
- Use only the tokens above or Bootstrap utilities already in use.
- Primary action gradients use `--brand-600` to `--brand-500`.
- List views should use SALN token set when applicable.

## Layout And Surfaces
- App background uses soft radial gradients over `--app-bg`.
- Primary surfaces are white with subtle borders and `--shadow-card`.
- Cards are rounded (20px) with shadow and border (`.card` in `master.blade.php`).

## Spacing And Radius
- Use Bootstrap spacing utilities or Tailwind spacing scale already in use.
- Common radii:
  - 10px: small action buttons (`.btn-action`)
  - 12px: buttons and inputs
  - 16px–20px: cards and panels
  - 9999px: badges, pills

## Buttons
Global styles in `resources/views/master.blade.php`:
- `.btn`: radius 12px, weight 600
- `.btn-primary` / `.btn-success`: gradient background, no border, shadow
- `.btn-secondary`: solid `#334155`
- `.btn-action`: 38x38, icon-only, rounded 10px
- `.btn-action.btn-edit`: green theme with hover inversion
- `.btn-action.btn-delete`: red theme with hover inversion
- `.btn-action.btn-view`: blue theme with hover inversion
- `.btn-modal-close`: neutral outline, soft hover
- `.btn-modal-save`: gradient primary, strong shadow

Reuse existing classes. Do not create new button colors or shapes.

## Cards
- Base card: `.card` uses white background, 20px radius, `--shadow-card`.
- SALN list cards: `.saln-record-card` with 16px radius, subtle border, hover lift.
- Profile panels use Tailwind `rounded-3xl` and strong shadows.

## Forms And Inputs
- `.form-control`, `.form-select`, `.selectize-input` use 12px radius and 42px min height.
- Focus state: 4px glow ring with `rgba(14, 165, 164, 0.14)` and border `--brand-500`.
- SALN premium inputs use `.form-control-premium` with similar focus treatment.

## Tables
- Table header: uppercase, small type, `#f8fafc` background.
- Borders use `--line`.
- Stripe/hover backgrounds match `--surface-soft` and `--brand-100` tints.

## Tabs And Navigation
- Tabs use `.nav-tabs` or `.nav-tabs-bordered` with rounded tabs and active state in brand color.
- Profile tabs use `.profile-tab` pill buttons with teal border and gradient active state.

## Badges
- `.badge` uses pill radius and compact padding.
- SALN has specialized badges like `.filing-badge` with predefined backgrounds.

## Modals
- Standard modals use Bootstrap structure.
- Premium modals (`.premium-modal`) use gradient headers, rounded 24px, and soft shadows.

## Icons
- Primary icons are Bootstrap Icons (`bi` classes) and Font Awesome.
- Use existing icon sets only.

## View Type Rules
- Non-list views should follow the design language of `resources/views/profile.blade.php`.
  - Use bold card surfaces, large radii, and Tailwind layout utilities.
- List views should follow `resources/views/saln.blade.php` + `resources/views/layouts/saln_styles.blade.php`.
  - Use `.saln-board`, `.saln-filter-lite`, `.saln-record-card`, and `.empty-state` patterns.

## Consistency Rules
- Reuse existing button classes and card structures.
- Match current responsive breakpoints (Bootstrap/Tailwind defaults).
- Preserve existing hover and focus behaviors.
- Never introduce new colors unless they already exist in tokens or Bootstrap defaults used in the app.

## Quick Reference (Common Classes)
- Buttons: `.btn`, `.btn-primary`, `.btn-success`, `.btn-secondary`, `.btn-action`, `.btn-modal-close`, `.btn-modal-save`
- Cards: `.card`, `.saln-record-card`, `.saln-board`
- Inputs: `.form-control`, `.form-select`, `.selectize-input`, `.form-control-premium`
- Tables: `.table`, `.saln-table-premium`
- Tabs: `.nav-tabs`, `.nav-tabs-bordered`, `.profile-tab`, `.saln-tab-nav`, `.saln-sub-tab-nav`
- Empty states: `.empty-state`
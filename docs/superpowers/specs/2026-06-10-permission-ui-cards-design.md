# Permission Management UI — Card Grid Redesign
**Date:** 2026-06-10
**Status:** Approved

---

## Problem

The current Access Rights page (`resources/views/admin/permissions.blade.php`) renders each module's permissions as a flat list of rows under a small uppercase heading, all stacked in a single long column. With 10 modules and growing, this is hard to scan. There's also a "Recent Changes" audit log section taking up space at the bottom of the panel.

## Goal

Group each module's permission toggles into its own bordered card, arranged in a 2-column grid within the existing right-hand panel. Remove the on-page audit log display (DB logging via `permission_audit_logs` is unaffected — only the UI list is removed).

---

## Layout

Outer page structure is unchanged:
- Left column (`col-md-4`): employee search/list — unchanged.
- Right column (`col-md-8`): permission panel — redesigned below.

### Permission panel header (unchanged)
- User name + level badge
- "Reset to Level Defaults" button

### Module cards (replaces `permissions_by_module` flat sections)

Each module becomes a Bootstrap card. Cards are laid out in a `row row-cols-md-2 g-3` grid inside the panel (2 cards per row at the panel's width).

**Card header**: bootstrap-icon + module label, using this mapping (consistent with sidebar icons where applicable):

| module | label | icon |
|---|---|---|
| dtr | DTR | `bi-calendar-check` |
| employee | Employee | `bi-person-badge` |
| leave | Leave | `bi-calendar-event` |
| payroll | Payroll | `bi-cash-stack` |
| overtime | Overtime | `bi-clock-history` |
| passslip | Pass Slip | `bi-door-open` |
| schedule | Schedule | `bi-calendar3` |
| saln | SALN | `bi-file-earmark-text` |
| hospital_order | Hospital Order | `bi-hospital` |
| reports | Reports | `bi-bar-chart-line` |

If a module appears that isn't in this map, fall back to `bi-gear` and the raw module key as the label (same fallback behavior as the current `MODULE_LABELS` map).

**Card body**: each permission rendered as a row — description text + status hint (`(role default)` / `(custom grant)` / `(revoked)`) + toggle switch on the right. Same markup/logic as the current `renderPermRow()` and `setPermission()` — no behavior change, only the wrapping container changes from a `<div class="mb-3">` section to a `<div class="card h-100">` with `card-header` and `card-body`.

### Removed

- "Recent Changes" / audit log block (`#audit_log`, the `<hr>` above it, and the heading) is removed from the template.
- `renderAuditLog()` JS function and its call in `selectUser()` are removed.
- The `audit_log` data returned by the existing `users/{id}` JSON endpoint can remain in the response (no backend change required) — it's simply unused by the frontend now.

---

## Implementation Notes

- File touched: `resources/views/admin/permissions.blade.php` only.
- `renderPermissions(grouped)` rewritten to map each `[module, perms]` entry to a card `<div class="col">...</div>`, wrapped in a `<div class="row row-cols-md-2 g-3">`.
- `renderPermRow(p)` stays as-is (returns a row used inside `card-body`).
- No changes to `PermissionService`, routes, or controllers.

---

## Out of Scope

- Permission priority rules (#3), dynamic sidebar (#4), and migration regeneration (#1) are separate efforts, to be designed individually.

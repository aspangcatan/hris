# HRIS Design System

## Purpose
This document captures the current UI language used in the HRIS app so new work matches the existing look and feel. It is grounded in the live layout and page styles already in the codebase. Do not introduce new colors or patterns unless they already exist in this repository.

## Sources Of Truth

| Reference page | Pattern it defines |
|---|---|
| `resources/views/master.blade.php` | Global tokens, base component styles |
| `resources/views/schedule.blade.php` | **Board layout** — filter strip + table shell |
| `resources/views/leave_approval.blade.php` | Board layout (second example) |
| `resources/views/employee_dtr.blade.php` | **Shell layout** — split sidebar + main panel |
| `resources/views/profile.blade.php` | **Profile layout** — dark sidebar + Tailwind content panels |
| `resources/views/saln.blade.php` + `layouts/saln_styles.blade.php` | **List layout** — record cards, SALN-style board |
| `public/assets/css/style.css` | Base theme overrides |

---

## Frameworks And Libraries
- Laravel 8 + Blade templates
- Bootstrap 5 (layout, components, utilities)
- Tailwind CDN utilities (used in `profile.blade.php` only)
- Icons: Bootstrap Icons (`bi-*`), Font Awesome

---

## Color Tokens

### Global (`master.blade.php`)
| Token | Value |
|---|---|
| `--app-bg` | `#eef3f9` |
| `--surface` | `#ffffff` |
| `--surface-soft` | `#f8fafc` |
| `--text-primary` | `#0f172a` |
| `--text-secondary` | `#475569` |
| `--line` | `#dbe3ee` |
| `--brand-600` | `#0f766e` |
| `--brand-500` | `#0ea5a4` |
| `--brand-100` | `#ccfbf1` |
| `--shadow-soft` | `0 14px 36px rgba(2,32,71,0.08)` |
| `--shadow-card` | `0 18px 44px rgba(15,23,42,0.08)` |

### In-page constants (use these exact values in page-scoped `<style>` blocks)
| Purpose | Value |
|---|---|
| Panel border | `#e2e8f0` (schedule/approval) · `#d9e2ec` (dtr) |
| Panel shadow | `0 18px 38px rgba(15,23,42,0.08)` |
| Field/strip bg | `#f8fafc` (schedule) · `#f8fbff` (dtr) |
| Field/strip border | `#dce6ef` (dtr) · `#d8e1ee` (schedule) |
| Heading | `#0f172a` |
| Body text | `#1e293b` |
| Secondary text | `#64748b` · `#5b6b81` |
| Field label | `#607086` |
| Input border | `#d8e1ee` |
| Brand primary | `#0f766e` |
| Brand secondary | `#0ea5a4` |
| Selected border | `#0f7b7a` |

Rule: pick one variant per page and be consistent within it. `#e2e8f0` / `#f8fafc` / `#d8e1ee` (schedule family) is preferred for new pages.

---

## Typography
- Primary font: Plus Jakarta Sans
- Page title: 1.8rem, weight 800, `#0f172a`
- Section subtitle: 0.9–0.92rem, `#64748b`
- Field label: 0.75rem, weight 800, uppercase, letter-spacing 0.04em, `#607086`
- Table header: 0.72rem, uppercase, letter-spacing 0.08em, `#0f172a`
- Table body: 0.9–1rem, weight 500, `#1e293b`

---

## Layout Patterns

### 1 — Board Layout
**Use for:** Single-panel pages with a filter strip and table (schedule, leave_approval, overtime_approval).

```css
.{page}-board {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 20px;
    padding: 1.5rem;
    box-shadow: 0 18px 38px rgba(15, 23, 42, 0.08);
}

/* Header row: title left, action/legend right */
.{page}-head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 1rem;
    margin-bottom: 1.25rem;
    flex-wrap: wrap;
}
.{page}-head-title h4 { margin: 0; font-weight: 700; color: #0f172a; }
.{page}-head-title span { font-size: 0.9rem; color: #64748b; }

/* Filter strip */
.{page}-filter {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 16px;
    padding: 1rem;
    margin-bottom: 1.25rem;
}
.{page}-filter .form-control,
.{page}-filter .form-select {
    border-radius: 12px;
    border: 1px solid #d8e1ee;
    background: #fff;
}

/* Table shell */
.{page}-table-shell {
    border: 1px solid #e2e8f0;
    border-radius: 16px;
    overflow: hidden;
    background: #fff;
}
.{page}-table-shell table { margin-bottom: 0; }
.{page}-table-shell table thead th {
    background: #f8fafc;
    color: #0f172a;
    font-size: 0.72rem;
    text-transform: uppercase;
    letter-spacing: 0.08em;
}
.{page}-table-shell table tbody td {
    color: #1e293b;
    font-weight: 500;
    vertical-align: middle;
}
```

**HTML skeleton:**
```html
<div class="{page}-board">
    <div class="{page}-head">
        <div class="{page}-head-title">
            <h4>Page Title</h4>
            <span>Short description of this page.</span>
        </div>
        {{-- optional: action button or legend --}}
    </div>

    <div class="{page}-filter">
        <div class="row g-3 align-items-end">
            {{-- filter controls --}}
        </div>
    </div>

    <div class="table-responsive {page}-table-shell">
        <table class="table text-center table-bordered align-middle">
            <thead><tr>...</tr></thead>
            <tbody id="{page}_list"></tbody>
        </table>
    </div>

    {{-- pagination --}}
</div>
```

---

### 2 — Shell Layout
**Use for:** Split-panel pages with a sticky sidebar of controls and a main content area (employee_dtr, bulk leave credits).

```css
.{page}-shell {
    display: grid;
    grid-template-columns: 500px minmax(0, 1fr);
    gap: 1.25rem;
}

.{page}-side,
.{page}-main,
.{page}-card {
    background: #fff;
    border: 1px solid #d9e2ec;
    border-radius: 20px;
    box-shadow: 0 16px 34px rgba(15, 23, 42, 0.08);
}
.{page}-side { padding: 1.2rem; position: sticky; top: 1rem; }
.{page}-main { padding: 1.1rem; }
.{page}-card { padding: 1rem; }

/* Page title inside side panel */
.{page}-title { font-size: 1.8rem; font-weight: 800; color: #0f172a; }
.{page}-sub   { color: #5b6b81; font-size: 0.92rem; margin-bottom: 1rem; }

/* Inputs inside panels */
.{page}-side .form-control,
.{page}-side .form-select { border-radius: 12px; min-height: 44px; }

/* Inner field card (label + control, soft inset bg) */
.{page}-field,
.{page}-stat {
    background: #f8fbff;
    border: 1px solid #dce6ef;
    border-radius: 16px;
    padding: 0.9rem;
}
.{page}-field label,
.{page}-stat-label {
    display: block;
    font-size: 0.75rem;
    font-weight: 800;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    color: #607086;
    margin-bottom: 0.55rem;
}

/* Stat card (numeric display) */
.{page}-stat { display: flex; flex-direction: column; justify-content: center; }
.{page}-stat strong { display: block; font-size: 1.55rem; line-height: 1; color: #0f172a; }
.{page}-stat span   { margin-top: 0.35rem; color: #5b6b81; font-size: 0.9rem; }

/* Empty state */
.{page}-empty {
    text-align: center;
    padding: 2rem 1rem;
    border: 1px dashed #cbd5e1;
    border-radius: 16px;
    color: #64748b;
    background: #fbfdff;
}

/* Responsive */
@media (max-width: 991.98px) {
    .{page}-shell { grid-template-columns: 1fr; }
    .{page}-side  { position: static; }
}
```

---

### 3 — Profile Layout
**Use for:** Personal data / form-heavy pages with a dark identity sidebar (profile.blade.php).

- Outer background: radial-gradient teal tints over `#f8fafc`
- Left sidebar: `rounded-3xl bg-slate-900/95 text-white shadow-2xl` (Tailwind)
- Content panels: `rounded-3xl bg-white shadow-xl ring-1 ring-slate-200` (Tailwind)
- Tabs: `.profile-tab` pill buttons with teal border; active = `linear-gradient(120deg, #0f766e, #0ea5a4)` white text

---

### 4 — List Layout (SALN)
**Use for:** Paginated record-card lists with search (saln, pass_slips, overtime).
See `resources/views/layouts/saln_styles.blade.php` for the full token set and component classes (`.saln-board`, `.saln-filter-lite`, `.saln-record-card`, `.empty-state`).

---

## Buttons
From `master.blade.php` — **reuse only these classes, do not create new ones:**

| Class | Description |
|---|---|
| `.btn` | 12px radius, weight 600 |
| `.btn-primary` | Gradient `--brand-600 → --brand-500`, no border, shadow |
| `.btn-success` | Same gradient, green tone |
| `.btn-secondary` | Solid `#334155` |
| `.btn-action` | 38×38 icon-only, 10px radius |
| `.btn-action.btn-edit` | Green theme, hover inversion |
| `.btn-action.btn-delete` | Red theme, hover inversion |
| `.btn-action.btn-view` | Blue theme, hover inversion |
| `.btn-modal-close` | Neutral outline, soft hover |
| `.btn-modal-save` | Gradient primary, strong shadow |

---

## Forms And Inputs
- `.form-control`, `.form-select`, `.select2-selection`: 12px radius, 42–44px min height
- Focus: 4px glow `rgba(14,165,164,0.14)`, border `--brand-500`
- Inside filter strips: add `border: 1px solid #d8e1ee; background: #fff`

---

## Tables
- Header: `#f8fafc` bg, `#0f172a` text, 0.72rem, uppercase, letter-spacing 0.08em
- Body: `#1e293b`, weight 500, vertical-align middle
- Borders: `#e2e8f0`
- Use `.table-shell` wrapper with `border-radius: 16px; overflow: hidden`

---

## Tabs

| Context | Class | Active state |
|---|---|---|
| Board / settings pages | `.nav-tabs` or `.nav-tabs-bordered` | Brand underline |
| Profile page | `.profile-tab` | Teal gradient pill |
| SALN | `.saln-tab-nav`, `.saln-sub-tab-nav` | Brand underline |

---

## Modals
- Standard: Bootstrap structure
- Premium (`.premium-modal`): gradient header, 24px radius, soft shadow

---

## Icons
Bootstrap Icons (`bi-*`) and Font Awesome only. No new icon libraries.

---

## Page-Type Decision Tree

```
New page type?
├── Single panel, has filter + table     → Board Layout (schedule pattern)
├── Split panel, sidebar controls + main → Shell Layout (dtr pattern)
├── Personal / PDS / profile data        → Profile Layout (profile pattern)
└── Paginated record cards               → List Layout (saln pattern)
```

---

## Quick Reference — All Classes

**Buttons:** `.btn` `.btn-primary` `.btn-success` `.btn-secondary` `.btn-action` `.btn-action.btn-edit` `.btn-action.btn-delete` `.btn-action.btn-view` `.btn-modal-close` `.btn-modal-save`

**Cards / Surfaces:** `.card` `.saln-record-card` `.saln-board`

**Inputs:** `.form-control` `.form-select` `.select2-selection` `.form-control-premium`

**Tables:** `.table` `.saln-table-premium` + `{page}-table-shell` pattern

**Tabs:** `.nav-tabs` `.nav-tabs-bordered` `.profile-tab` `.saln-tab-nav` `.saln-sub-tab-nav`

**Empty states:** `.empty-state` · `{page}-empty`

---

## Consistency Rules
1. Pick one layout pattern per page and name all classes with its `{page}-` prefix.
2. Use only the color values listed above. No hex codes outside this doc.
3. Reuse existing button classes. Never add new button colors or shapes.
4. Match existing responsive breakpoints (Bootstrap/Tailwind defaults).
5. Preserve existing hover and focus behaviors.
6. Keep page-scoped `<style>` blocks — do not add page CSS to `style.css`.

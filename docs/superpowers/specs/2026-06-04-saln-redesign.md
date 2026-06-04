# SALN Module Redesign — Design Spec

**Date:** 2026-06-04

## Scope

Frontend-only. No backend or route changes.

---

## 1. Per-Tab Save Removal

Remove `#btn_save_declarant` (Declarant Profile & ID tab) and `#spouse_btn_save_declarant` (Spouse Profile & ID tab) from the modal HTML.

Remove their corresponding click handlers from `js/saln.blade.php` (lines ~299-478). The data they save is already included in the main `updateSalnCompliance` payload — removing them loses nothing.

---

## 2. SweetAlert Confirmation on Save

Before calling `updateSalnCompliance`, intercept `#btn_save_and_finalize` with a SweetAlert2 confirmation:

> **"Save SALN Declaration?"**
> "All information across all tabs — Declarant Profile, Children, Assets, Liabilities, Business Interests, Government Relatives, and Spouse data — will be finalized."
> Buttons: **Save Declaration** (teal) / Cancel

Rename the button label from "SAVE & FINALIZE DECLARATION" → **"SAVE DECLARATION"**.

---

## 3. Modal Redesign (saln_form.blade.php)

### Header
Replace plain modal header with a premium gradient header:
- Background: `linear-gradient(135deg, #0f766e, #0ea5a4)`
- White text showing: **"SALN Declaration"** + filing context chip (Assumption / Annual / Exit)
- Close button white

### Body — Compliance Config Section
Apply `.saln-config-strip` card (soft teal bg, rounded 16px) to the filing context / filing type / effective date row.

### Tab Navigation
- Main tabs (Declarant / Spouse): keep `.saln-tab-nav` pill style, apply brand active state
- Sub-tabs: keep `.saln-sub-tab-nav`, tighten spacing

### Tab Content Sections
Each sub-tab's content wrapped in a `.saln-section-card` (white, 16px radius, `#e2e8f0` border, subtle shadow) with a `.saln-section-label` header (small uppercase teal label).

### Footer
Single action row:
- Left: current filing mode badge
- Right: **CANCEL** (neutral) + **SAVE DECLARATION** (gradient primary, prominent)

---

## 4. Sub-Modals (modals/saln.blade.php)

Apply consistent header: `background: linear-gradient(135deg, #0f766e, #0ea5a4)`, white title, white close button. Match `.btn-modal-save` / `.btn-modal-close` for footers.

---

## 5. Main Page (saln.blade.php)

Already uses `.saln-board` / `.saln-record-card`. Apply consistent head structure:
- `.saln-board-head` with title + subtitle
- Filter strip using `.saln-filter-lite`

---

## Files Changed

| File | Change |
|------|--------|
| `resources/views/modals/saln_form.blade.php` | Premium header, section cards, remove per-tab save buttons, rename footer button |
| `resources/views/modals/saln.blade.php` | Gradient headers on all sub-modals |
| `resources/views/js/saln.blade.php` | Remove per-tab save handlers, add SweetAlert before main save |
| `resources/views/layouts/saln_styles.blade.php` | Add premium-header, section-card, config-strip CSS |
| `resources/views/saln.blade.php` | Minor board-head polish |

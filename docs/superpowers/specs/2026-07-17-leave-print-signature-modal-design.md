# Leave Print Signature Modal — Design

**Goal:** Replace the previous auto-lookup approach (reading `tdh_user.users.signature` and rendering it automatically on the printed leave form) with an explicit, in-page signature capture flow on the print preview screen. Before clicking Print, the user can draw their signature by hand (mouse/touch) or upload an image of it, and that becomes the "(Signature of Applicant)" image for the current print — nothing is persisted anywhere.

**Supersedes:** commit `17ab7b4` ("show the applicant's on-file e-signature on the printed leave form"). That commit's `PrintController::printLeaveForm()` query addition (`u.signature`) and the `applicantSignature` view variable are removed as part of this work — this feature does not read or write that column at all.

## Scope decisions (confirmed with user)

- **No persistence.** "Save Changes" only affects the current print preview page's DOM. Reloading the page loses the signature; the user redraws/re-uploads each time. No new route, no DB write, no migration.
- **Drawing implementation:** use the `signature_pad` library via CDN (jsDelivr), matching the existing pattern of loading SweetAlert2 via CDN on this same page. Not vanilla canvas code.
- **Custom modal, not the app's Bootstrap modal pattern.** `resources/views/print/leave_form.blade.php` is a standalone printable page — it doesn't load Bootstrap/jQuery. The signature modal is a small self-built overlay (plain CSS + vanilla JS).

## UI

**Trigger:** a new button in `.print-toolbar` (top-right, alongside the existing "Print" button). `.print-toolbar` already has `display:none` under `@media print`, so this button — and the modal it opens — never appears in the printed/PDF output.

**Modal contents** (per user-provided mockup):
- Instructional text: "You can manually draw your signature here or override with upload"
- A `<canvas>` signature pad (signature_pad-driven), sized ~500×180, scaled for `devicePixelRatio` per signature_pad's documented setup
- "Upload Signature" — a file input (`accept="image/*"`) styled as a button/row below the canvas. Selecting a file clears the canvas and draws the uploaded image onto it (centered, aspect-ratio preserved, fit to canvas bounds) — upload overrides whatever was drawn, matching the mockup's "override with upload"
- "Clear" — a small text link that resets the canvas to blank (standard signature-pad affordance, not in the mockup but expected)
- "Save Changes" — primary action. If the canvas is empty, show an inline validation message ("Please draw or upload a signature before saving.") and do not close the modal. Otherwise: read `signaturePad.toDataURL('image/png')`, insert it as an `<img class="signature-img">` into the existing `.applicant-line` div (reusing the CSS from the reverted commit — image above the sign-line, sign-line's `margin-top` tightened when an image is present), and close the modal.
- A close/cancel affordance (✕) that dismisses without changing anything already embedded

**Re-opening the modal** (e.g., to redo the signature before printing) re-shows a blank canvas — since nothing is persisted, there's no prior state to restore. If they'd already saved one, clicking the toolbar button again lets them redraw/re-upload and Save Changes again, replacing the previously embedded image.

## Files touched

- `app/Http/Controllers/PrintController.php` — remove the `u.signature` select column and the `applicantSignature` view variable (revert of `17ab7b4`'s backend half).
- `resources/views/print/leave_form.blade.php`:
  - Remove the `@if($applicantSignature)` server-rendered `<img>` block, keep the `.signature-img` / `.sign-line` CSS (now populated client-side instead of server-side).
  - Add the CDN `<script>` tag for `signature_pad`.
  - Add the toolbar button, modal markup, and modal JS (init pad, upload-to-canvas, clear, save-and-embed, close).

## Testing

No backend logic remains for this feature, so no PHPUnit coverage applies. Verification is a manual browser pass: open a leave print preview, open the signature modal, draw a signature, confirm it embeds above the applicant line and the modal closes; reopen, upload an image instead, confirm it overrides the canvas and embeds correctly; try Save Changes on a blank canvas and confirm the validation message appears without closing the modal; confirm the toolbar button and modal are absent from the print/PDF output (`@media print`).

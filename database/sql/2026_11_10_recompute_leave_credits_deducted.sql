-- =====================================================================
-- Recompute leave_date_applied.credits_deducted from the (already
-- corrected) minute_conversions table.
--
-- Only leave_date_applied is changed. leave_credits is NOT touched.
-- Back up leave_date_applied first.
--
-- Rules (same as LeaveApplicationService::creditsForDate):
--   * Only hour-based leave types (leave_types.deduction_unit <> 'days').
--     Day-based types are always 1.000 / 0.500 and are left alone.
--   * LWOP filings (is_lwop = 1) charge 0 and are left alone.
--   * Rows that currently charge 0 are left alone.
--   * Correct charge = hours x points_deduction of minute 60.
--   * SL -> VL fallback rows (fallback_credits > 0): the SL part stays,
--     the difference goes to fallback_credits.
-- =====================================================================

USE hris;
SET SQL_SAFE_UPDATES = 0;   -- MySQL Workbench Error 1175 otherwise

-- ---------------------------------------------------------------------
-- 1. PREVIEW: rows that will change (run this first and review)
-- ---------------------------------------------------------------------
SELECT lda.id, la.id AS leave_application_id, la.user_id, la.status,
       lt.code, lda.date_applied, lda.hours,
       lda.credits_deducted                          AS old_credits,
       CASE WHEN lda.fallback_credits > 0
            THEN LEAST(lda.credits_deducted, ROUND(lda.hours * mc.points_deduction, 3))
            ELSE ROUND(lda.hours * mc.points_deduction, 3) END AS new_credits,
       lda.fallback_credits                          AS old_fallback,
       CASE WHEN lda.fallback_credits > 0
            THEN ROUND(lda.hours * mc.points_deduction, 3)
                 - LEAST(lda.credits_deducted, ROUND(lda.hours * mc.points_deduction, 3))
            ELSE 0 END                               AS new_fallback
FROM leave_date_applied lda
JOIN leave_applications la ON la.id = lda.leave_application_id
JOIN leave_types lt        ON lt.id = lda.leave_type_id
CROSS JOIN (SELECT points_deduction FROM minute_conversions WHERE minute = 60) mc
WHERE COALESCE(la.is_lwop, 0) = 0
  AND LOWER(COALESCE(lt.deduction_unit, 'hours')) <> 'days'
  AND (lda.credits_deducted + lda.fallback_credits) > 0
  AND (lda.credits_deducted + lda.fallback_credits) <> ROUND(lda.hours * mc.points_deduction, 3)
ORDER BY la.user_id, lda.date_applied;

-- ---------------------------------------------------------------------
-- 2. UPDATE
-- ---------------------------------------------------------------------
-- 2a. Fallback rows first (they still have fallback_credits > 0 here)
UPDATE leave_date_applied lda
JOIN leave_applications la ON la.id = lda.leave_application_id
JOIN leave_types lt        ON lt.id = lda.leave_type_id
CROSS JOIN (SELECT points_deduction FROM minute_conversions WHERE minute = 60) mc
SET lda.fallback_credits = ROUND(lda.hours * mc.points_deduction, 3)
                           - LEAST(lda.credits_deducted, ROUND(lda.hours * mc.points_deduction, 3)),
    lda.credits_deducted = LEAST(lda.credits_deducted, ROUND(lda.hours * mc.points_deduction, 3))
WHERE COALESCE(la.is_lwop, 0) = 0
  AND LOWER(COALESCE(lt.deduction_unit, 'hours')) <> 'days'
  AND lda.fallback_credits > 0
  AND lda.fallback_leave_type_id IS NOT NULL;

-- 2b. Normal rows (no fallback)
UPDATE leave_date_applied lda
JOIN leave_applications la ON la.id = lda.leave_application_id
JOIN leave_types lt        ON lt.id = lda.leave_type_id
CROSS JOIN (SELECT points_deduction FROM minute_conversions WHERE minute = 60) mc
SET lda.credits_deducted = ROUND(lda.hours * mc.points_deduction, 3)
WHERE COALESCE(la.is_lwop, 0) = 0
  AND LOWER(COALESCE(lt.deduction_unit, 'hours')) <> 'days'
  AND lda.fallback_credits = 0
  AND lda.credits_deducted > 0;

-- ---------------------------------------------------------------------
-- 3. VERIFY: re-run the preview in step 1; it must return no rows.
-- ---------------------------------------------------------------------

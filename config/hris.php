<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Pass Slip Auto-Approve (temporary)
    |--------------------------------------------------------------------------
    |
    | While the Section Head / Division approval flow is not yet in place,
    | flip this to bypass the pending queue and mark pass slips as approved
    | the moment they're filed, so printDTR picks them up immediately.
    | Set to false once real approval routing is ready.
    |
    */

    'pass_slip_auto_approve' => env('PASS_SLIP_AUTO_APPROVE', true),

    /*
    |--------------------------------------------------------------------------
    | DTR Half-Day-Missing-Half Check (temporary)
    |--------------------------------------------------------------------------
    |
    | Disabled for now: this flat-charge special case (only one of AM/PM
    | worked on a full-day schedule) has known categorization bugs in the
    | surrounding incomplete-punch fallback that haven't been sorted out
    | yet. While off, DtrDayComputationService falls back to plain
    | full-day boundary math for every day, same as a schedule with no
    | break configured. Flip back to true once the fallback path is fixed.
    |
    */

    'dtr_halfday_missing_check_enabled' => env('DTR_HALFDAY_MISSING_CHECK_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | DTR Raw Timelog Editing (temporary kill switch)
    |--------------------------------------------------------------------------
    |
    | Controls the admin-side add/edit/delete of raw IN/OUT DTR logs (the
    | "View DTR" page's raw log modal, backed by storeManual/deleteTimeLog
    | in DTRController). Set to false to block create/update/delete for
    | everyone regardless of the 'dtr.edit' permission. Flip back to true
    | once editing is safe to re-enable.
    |
    */

    'dtr_raw_timelog_editing_enabled' => env('DTR_RAW_TIMELOG_EDITING_ENABLED', true),

];

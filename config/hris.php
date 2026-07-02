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

];

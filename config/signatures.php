<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Signature storage
    |--------------------------------------------------------------------------
    |
    | How newly captured signatures (leave applicant / Section Head / Division
    | Head) are saved in their database column:
    |
    |   "base64" - the image itself, as a data URL (data:image/png;base64,...).
    |              This is the current behaviour.
    |   "file"   - the image is written to a file on the disk below and the
    |              column holds only its relative path.
    |
    | Reading works with both formats regardless of this setting, so switching
    | never breaks signatures that were saved before the switch.
    |
    */

    'storage' => env('SIGNATURE_STORAGE', 'base64'),

    // Filesystem disk used when storage = "file" (local = storage/app, not public).
    'disk' => env('SIGNATURE_DISK', 'local'),

    // Folder on that disk; files are only ever read from inside this folder.
    'directory' => 'signatures',

];

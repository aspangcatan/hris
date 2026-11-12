<?php

return [

    /*
    |--------------------------------------------------------------------------
    | DTR PDF engine
    |--------------------------------------------------------------------------
    |
    | Which library renders the DTR print (pdf/old_dtr.blade.php):
    |
    |   "snappy" - wkhtmltopdf via barryvdh/laravel-snappy (needs the
    |              wkhtmltopdf binary, see WKHTML_PDF_BINARY / config/snappy.php)
    |   "dompdf" - pure PHP via barryvdh/laravel-dompdf, no binary needed
    |
    | Every other print in the app already uses dompdf.
    |
    */

    'dtr_engine' => env('DTR_PDF_ENGINE', 'snappy'),

];

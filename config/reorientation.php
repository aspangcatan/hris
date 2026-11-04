<?php

// Re-Orientation reading materials. PDFs live in resources/pdf/reorientation (not
// public) and are streamed by ReorientationController::modulePdf for view-only access.
return [
    'modules' => [
        1 => ['title' => 'Introduction and General Information', 'pdf' => 'pdf/reorientation/Mod_1.pdf'],
        2 => ['title' => 'HR Systems',                           'pdf' => 'pdf/reorientation/Mod_2.pdf'],
        3 => ['title' => 'Employee Relations',                   'pdf' => 'pdf/reorientation/Mod_3.pdf'],
        4 => ['title' => 'Compensation and Benefits',            'pdf' => 'pdf/reorientation/Mod_4.pdf'],
    ],
];

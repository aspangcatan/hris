<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Document</title>
    <style>
        @page {
            size: 612pt 1008pt !important;
        }

        @font-face {
            font-family: 'Arial Narrow';
            src: url({{ storage_path("fonts/arial_narrow.ttf") }}) format("truetype");
            font-weight: 800;
            font-style: normal;
        }

        @font-face {
            font-family: 'Arial Black';
            src: url({{ storage_path("fonts/arial_black.ttf") }}) format("truetype");
            font-weight: 800;
            font-style: normal;
        }

        label.checkbox-label input[type=checkbox] {
            position: relative;
            vertical-align: middle;
            bottom: 1px;
        }

        html, body {
            font-size: 9.66px !important;
            font-family: sans-serif;
            margin: 2px !important;
            padding: 2px !important;
        }

        input {
            vertical-align: middle !important;
        }

        .field-value {
            font-size: 13.33px !important;
            font-weight: bold !important;
        }

        table {
            width: 100%;
            table-layout: fixed !important;
            border-spacing: 0; /* Removes the cell spacing via CSS */
            border-collapse: collapse; /* Optional - if you don't want to have double border where cells touch */
        }

        table td {
            margin: 0px !important;
            height: 21px !important;
            padding: 1px !important;
            vertical-align: middle !important;
            overflow-wrap: break-word !important;
        }

        table.survey td {
            margin: 0px !important;
            height: 5px !important;
            padding: 2px !important;
            vertical-align: top !important;
        }

        table.reference td {
            margin: 0px !important;
            padding: 0px !important;
            height: 3px !important;
            vertical-align: middle !important;
        }

        table.government_id td {
            margin: 0px !important;
            padding: 0px !important;
            height: unset !important;
            vertical-align: middle !important;
        }

        input[type='checkbox'] {
            height: 12px;
            width: 12px;
        }

        .gray {
            background: rgb(234, 234, 234) !important;
        }

        .title {
            font-weight: bold;
            color: white;
            font-size: 12.33px;
            /*font-size: 14.66px;*/
            padding: 2px !important;
            height: unset !important;
            border-top: solid 1px black;
            border-bottom: solid 1px black;
        }

        .underline {
            border: 0;
            outline: 0;
            background: transparent;
            border-bottom: 1px solid black;
        }

        .rectangle {
            border: solid 2px black;
            text-align: center;
        }

        .photo {
            margin-left: auto;
            margin-right: auto;
            height: 165.07874016px;
            width: 152.28346457px;
        }

        .value {
            font-family: sans-serif;
            font-size: 13.33px;
            margin-left: 5px;
            font-weight: bold;
        }

        .center-value {
            font-family: sans-serif;
            font-size: 13.33px;
            text-align: center;
            font-weight: bold;
        }

        .education {
            font-size: 10.66px !important;
        }

        .hide{
            display: none;
        }
    </style>
</head>
<body>
@include('pdf.new.pds_1')
@php
    // Count total education rows and check if continuation is needed
    $totalEducationRows = 0;
    foreach($educations as $education) {
        $totalEducationRows += count($education);
    }
    
    $maxRowsPage1 = 8;
    $needsContinuation = $totalEducationRows > $maxRowsPage1;
    
    // Split educations into page 1 and continuation page
    $educationsContinuation = [];
    $currentRowCount = 0;
    
    foreach($educations as $levelKey => $education) {
        $levelRowCount = count($education);
        
        if ($currentRowCount + $levelRowCount <= $maxRowsPage1) {
            $currentRowCount += $levelRowCount;
        } else if ($currentRowCount < $maxRowsPage1) {
            $remainingSpace = $maxRowsPage1 - $currentRowCount;
            $educationsContinuation[$levelKey] = array_slice($education, $remainingSpace);
            $currentRowCount = $maxRowsPage1;
        } else {
            $educationsContinuation[$levelKey] = $education;
        }
    }
@endphp
@if($needsContinuation && !empty($educationsContinuation))
<div style="page-break-before: always !important;"></div>
@include('pdf.new.pds_1_educational_continuation', ['educations' => $educationsContinuation])
@endif
<div style="page-break-before: always !important;"></div>
@include('pdf.new.pds_2')
<div style="page-break-before: always !important;"></div>
@include('pdf.new.pds_3')
<div style="page-break-before: always !important;"></div>
@include('pdf.new.pds_4')
</body>
</html>

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

        label.checkbox-label input[type=checkbox] {
            position: relative;
            vertical-align: middle;
            bottom: 1px;
        }

        html, body {
            font-size: 9.5px;
            margin: 0px !important;
            padding: 2px !important;
            font-family: "Times New Roman" !important;
        }

        input {
            vertical-align: middle !important;
        }

        table {
            width: 100%;
            table-layout: fixed !important;
            border-spacing: 0; /* Removes the cell spacing via CSS */
            border-collapse: collapse; /* Optional - if you don't want to have double border where cells touch */
        }

        table td {
            margin: 0px !important;
            height: 23px !important;
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
            height: 170.07874016px;
            width: 132.28346457px;
        }

    </style>
</head>
<body>
@include('pdf.pds_1');
<div style="page-break-before: always !important;"></div>
@include('pdf.pds_2');
<div style="page-break-before: always !important;"></div>
@include('pdf.pds_3');
<div style="page-break-before: always !important;"></div>
@include('pdf.pds_4');
</body>
</html>

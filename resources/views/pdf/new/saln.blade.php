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
            font-family: 'Bookman';
            src: url({{ storage_path("fonts/bookman.ttf") }}) format("truetype");
            font-weight: 800;
            font-style: normal;
        }

        html, body {
            padding: 20px;
            margin: 0px;
            font-size: 10.66px;
            font-family: 'Bookman';
        }

        table {
            width: 100%;
            table-layout: fixed !important;
            border-spacing: 0; /* Removes the cell spacing via CSS */
            border-collapse: collapse; /* Optional - if you don't want to have double border where cells touch */
        }

        table td {
            margin: 0px !important;
            height: 15px !important;
            padding: 1px !important;
            vertical-align: middle;
        }

        .title {
            font-size: 13.33px;
        }

        #declarant td {
            vertical-align: bottom !important;
        }

        .assets td {
            height: 40px !important;
        }

        .border {
            border: solid 1px black;
        }

        .border-bottom {
            border-bottom: solid 1px black;
        }

        .border-right {
            border-right: solid 1px black;
        }

        .border-left {
            border-left: solid 1px black;
        }

        .border-top {
            border-top: solid 1px black;
        }

        .fw-bold {
            font-weight: bold;
        }

        .gray {
            background: rgb(234, 234, 234) !important;
        }
    </style>
</head>
<body>
@include('pdf.new.saln_annex_a')

@if($show_annex_b)
    <div style="page-break-before: always !important;"></div>
    @include('pdf.new.saln_annex_b')
@endif

@if($show_annex_c)
    <div style="page-break-before: always !important;"></div>
    @include('pdf.new.saln_annex_c')
@endif

</body>
</html>

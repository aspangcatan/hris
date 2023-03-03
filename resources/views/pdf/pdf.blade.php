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
            size: legal portrait;
        }

        body, html {
            height: 100% !important;
            width: 100% !important;
            margin: 0px !important;
            padding: 0px !important;
            font-family: "Times New Roman" !important;
            font-size: 9px !important;
        }

        input[type=checkbox] {
            transform: scale(0.7);
        }

        .label {
            font-size: 12px !important;
        }

        .mini-label {
            font-size: 10px;
        }

        .fw-bold {
            font-weight: 300 !important;
        }

        .fw-light-bold {
            font-weight: 500 !important;
        }

        .p-1 {
            padding: 2px;
        }

        .p-10 {
            padding: 20px !important;
        }

        .pr-1 {
            padding-right: 2px;
        }

        .pl-1 {
            padding-left: 2px;
        }

        .text-center {
            text-align: center !important;
        }

        .d-inline-block {
            display: inline-block;
        }

        .d-inline {
            display: inline;
        }

        .mt-2 {
            margin-top: 20px;
        }

        .ml-1 {
            margin-left: 10px;
        }

        .ml-2 {
            margin-left: 20px;
        }

        .ml-3 {
            margin-left: 30px;
        }

        .border {
            border: 0.5px solid black;
        }

        .page {
            padding: 3px !important;
        }

        .border-lg {
            border: 2px solid black;
        }

        .bg-gray {
            background: rgb(150, 150, 150);
        }

        .bg-gray-white {
            background: rgb(234, 234, 234);
        }

        .bg-white {
            background: white;
        }

        .w-100 {
            width: 100% !important;
        }

        .item {
            background: rgb(150, 150, 150);
            color: white;
            font-weight: bold;
            padding-top: 2px;
            padding-bottom: 2px;
        }

        .border-dark {
            border: 1px solid black;
        }

        .border-dark-right {
            border-right: 1px solid black;
        }

        .border-dark-left {
            border-left: 1px solid black;
        }

        .border-dark-top {
            border-top: 1px solid black;
        }

        .border-dark-bottom {
            border-bottom: 1px solid black !important;
        }

        .border-gray-top {
            border-top: 1px solid rgb(150, 150, 150);
        }

        table.no-spacing {
            border-spacing: 0; /* Removes the cell spacing via CSS */
            border-collapse: collapse; /* Optional - if you don't want to have double border where cells touch */
        }

        table td {
            margin: 0px !important;
            height: 20px !important;
            padding: 2px !important;
            text-align: unset !important;
            vertical-align: unset !important;
        }

        v-align-center {
            vertical-align: middle !important;
        }

        .inline-block {
            display: inline-block;
            vertical-align: top;
            height: 32px;
            margin: 2px;
        }


        .v-center {
            vertical-align: middle !important;
        }

        .text-center {
            text-align: center !important;
        }

        .underline {
            border: 0;
            outline: 0;
            background: transparent;
            border-bottom: 1px solid black;
        }

        .inline-block-box {
            box-sizing: border-box;
            display: inline-block;
        }
    </style>
</head>
<body>
<div class="page">
    @include('pdf.page_1')
    <div style="page-break-before: always !important;"></div>
    @include('pdf.page_2')
    <div style="page-break-before: always !important;"></div>
    @include('pdf.page_3')
    <div style="page-break-before: always !important;"></div>
    @include('pdf.page_4')
</div>
{{--@include('pdf.page_5')--}}
</body>
</html>

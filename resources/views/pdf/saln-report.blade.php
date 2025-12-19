<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>SALN Report</title>

    <style>
        @page {
            margin: 120px 20px 60px 20px;
        }

        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 10px;
        }

        /* ===== HEADER (REPEATS EVERY PAGE) ===== */
        header {
            position: fixed;
            top: -100px;
            left: 0;
            right: 0;
            text-align: center;
        }

        /* ===== TABLE STYLES ===== */
        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            border: 1px solid #000;
            padding: 3px;
        }

        thead {
            background-color: rgb(167, 170, 154);
            display: table-header-group; /* REQUIRED */
        }

        .text-center {
            text-align: center;
        }

        .no-border{
            border: 0px;
        }

        .text-right {
            text-align: right;
        }

        /* Prevent rows from splitting */
        tr {
            page-break-inside: avoid;
        }
    </style>
</head>
<body>

<header>
    <h2>{{ $agency }}</h2>
    <h3>
        Summary List of Filers<br>
        Statement of Assets, Liabilities and Networth<br>
        Calendar Year {{ $calendar_year }}
    </h3>
</header>

<table>
    <thead>
    <tr>
        <th width="3%" rowspan="2">No.</th>
        <th colspan="3">NAME OF EMPLOYEE</th>
        <th rowspan="2">TIN</th>
        <th rowspan="2">POSITION</th>
        <th rowspan="2">NET WORTH</th>
    </tr>
    <tr>
        <th>Lastname</th>
        <th>Firstname</th>
        <th>Middlename</th>
    </tr>
    </thead>

    <tbody>
    @foreach ($employees as $emp)
        <tr>
            <td class="text-center">{{ $emp['no'] }}</td>
            <td>{{ $emp['lastname'] }}</td>
            <td>{{ $emp['firstname'] }}</td>
            <td>{{ $emp['middlename'] }}</td>
            <td>{{ $emp['tin'] }}</td>
            <td>{{ $emp['position'] }}</td>
            <td class="text-right">{{ $emp['net_worth'] }}</td>
        </tr>
    @endforeach
    </tbody>
</table>

<table class="no-border">
    <tr>
        <td width="50%"><strong>Total Number of Filers:</strong> _______<br><br> <strong>Total Number of Personnel
                Complement:</strong> _______
        </td>
    </tr>
</table>
<div class="signature">
    <table class="no-border">
        <tr>
            <td width="50%"> Prepared by:<br>
                <div class="signature-line"></div>
                Name and Signature of SALN In-Charge<br><br> Position: ___________<br> Mailing Address: ___________<br>
                Contact No.: ___________<br> Date: ___________
            </td>
            <td width="50%"> Noted by:<br>
                <div class="signature-line"></div>
                Name and Signature of Head of Agency<br><br> Position: ___________<br> Mailing Address: ___________<br>
                Contact No.: ___________<br> Date: ___________
            </td>
        </tr>
    </table>
</div>
<!-- ===== PAGE NUMBERING (DomPDF NATIVE) ===== -->
<script type="text/php">
    if (isset($pdf)) {
        $pdf->page_script(function ($pageNum, $pageCount, $pdf, $fontMetrics) {

            $font = $fontMetrics->get_font("Helvetica", "normal");
            $size = 10;

            $text = "Page $pageNum of $pageCount";

            $width = $fontMetrics->getTextWidth($text, $font, $size);
            $x = ($pdf->get_width() - $width) / 2;
            $y = $pdf->get_height() - 30;

            $pdf->text($x, $y, $text, $font, $size);
        });
    }

</script>

</body>
</html>

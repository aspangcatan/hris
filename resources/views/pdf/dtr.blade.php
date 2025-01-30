<html>
<head>
    <title>DTR</title>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
</head>
<body>
<style>
    body {
        font-family: Arial, sans-serif;
        font-size: 10px; /* Reduced font size for better fit */
        margin: 0;
        padding: 0;
    }

    .page {
        page-break-after: always;
        width: 100%;
    }

    .twin-container {
        display: flex;
        justify-content: space-between;
        gap: 8px; /* Adjusted gap between sections */
        width: 100%;
    }

    .dtr-section {
        width: 49%; /* Ensure sections fit side by side */
        border: 1px solid #000;
        padding: 5px; /* Reduced padding for compact layout */
        box-sizing: border-box;
    }

    .dtr-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 5px;
    }

    .dtr-table th, .dtr-table td {
        border: 1px solid black;
        padding: 3px; /* Reduced cell padding */
        text-align: center;
        font-size: 9px; /* Slightly smaller text for table content */
    }

    .title {
        text-align: center;
        font-size: 12px; /* Adjusted font size for titles */
        font-weight: bold;
        margin: 5px 0;
    }

    .header-info {
        margin-top: 5px;
        font-size: 10px; /* Adjusted font size */
        text-align: center;
    }

    .name {
        text-align: center;
        font-weight: bold;
        text-transform: uppercase;
        margin-top: 5px;
    }

    .certify-section {
        margin-top: 10px;
        text-align: center;
        font-size: 9px; /* Reduced font size for certification text */
    }

    .certify-section p {
        margin-bottom: 5px;
    }
</style>

<div class="page">
    <div class="twin-container">
        <!-- First Section -->
        <div class="dtr-section">
            <div class="title">DAILY TIME RECORD</div>
            <div class="name">Sample Name</div>
            <div class="header-info">
                For the month of: <strong>{{ date('F Y', strtotime($date)) }}</strong>
            </div>

            <table class="dtr-table">
                <thead>
                <tr>
                    <th>Day</th>
                    <th>A.M. Arrival</th>
                    <th>A.M. Departure</th>
                    <th>P.M. Arrival</th>
                    <th>P.M. Departure</th>
                    <th>Undertime Hours</th>
                    <th>Undertime Minutes</th>
                </tr>
                </thead>
                <tbody>
                @foreach($dtrs as $dtr)
                    <tr>
                        <td>{{ $dtr['day'] }}</td>
                        <td>{{ $dtr['date_timein'] ?? '-' }}</td>
                        <td>-</td>
                        <td>-</td>
                        <td>{{ $dtr['date_timeout'] ?? '-' }}</td>
                        <td>-</td>
                        <td>-</td>
                    </tr>
                @endforeach
                </tbody>
            </table>

            <div class="certify-section">
                <p>
                    I CERTIFY on my honor that the above is a true and correct report of the hours of work
                    performed, recorded daily at the time of arrival and departure from the office.
                </p>
                <div class="name">SHEEESH</div>
                <p>Verified as to the prescribed office hours</p>
            </div>
        </div>

        <!-- Second Section -->
        <div class="dtr-section">
            <div class="title">DAILY TIME RECORD</div>
            <div class="name">Sample Name</div>
            <div class="header-info">
                For the month of: <strong>{{ date('F Y', strtotime($date)) }}</strong>
            </div>

            <table class="dtr-table">
                <thead>
                <tr>
                    <th>Day</th>
                    <th>A.M. Arrival</th>
                    <th>A.M. Departure</th>
                    <th>P.M. Arrival</th>
                    <th>P.M. Departure</th>
                    <th>Undertime Hours</th>
                    <th>Undertime Minutes</th>
                </tr>
                </thead>
                <tbody>
                @foreach($dtrs as $dtr)
                    <tr>
                        <td>{{ $dtr['day'] }}</td>
                        <td>{{ $dtr['date_timein'] ?? '-' }}</td>
                        <td>-</td>
                        <td>-</td>
                        <td>{{ $dtr['date_timeout'] ?? '-' }}</td>
                        <td>-</td>
                        <td>-</td>
                    </tr>
                @endforeach
                </tbody>
            </table>

            <div class="certify-section">
                <p>
                    I CERTIFY on my honor that the above is a true and correct report of the hours of work
                    performed, recorded daily at the time of arrival and departure from the office.
                </p>
                <div class="name">SHEEESH</div>
                <p>Verified as to the prescribed office hours</p>
            </div>
        </div>
    </div>
</div>
</body>
</html>

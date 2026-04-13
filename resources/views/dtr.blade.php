@extends('master')
@section('content')
    <style>
        .dtr-board {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 20px;
            padding: 1.5rem;
            box-shadow: 0 18px 38px rgba(15, 23, 42, 0.08);
        }

        .dtr-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            margin-bottom: 1.25rem;
        }

        .dtr-header-title {
            display: flex;
            flex-direction: column;
            gap: 0.15rem;
        }

        .dtr-header-title h4 {
            margin: 0;
            font-weight: 700;
            color: #0f172a;
        }

        .dtr-header-title span {
            font-size: 0.9rem;
            color: #64748b;
        }

        .dtr-filters {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 1rem;
            margin-bottom: 1.25rem;
        }

        .dtr-filters .form-floating > .form-control,
        .dtr-filters .form-floating > .form-select {
            border-radius: 12px;
            border: 1px solid #d8e1ee;
            background: #fff;
        }

        .dtr-table-shell {
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            overflow: hidden;
            background: #fff;
        }

        .dtr-table-shell table {
            margin-bottom: 0;
        }

        .dtr-table-shell table thead th {
            background: #f8fafc;
            color: #0f172a;
            font-size: 0.72rem;
            text-transform: uppercase;
            letter-spacing: 0.08em;
        }

        .dtr-table-shell table tbody td {
            color: #1e293b;
            font-weight: 500;
            vertical-align: middle;
        }

        .dtr-section-title {
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 0.65rem;
        }

        .dtr-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 1.25rem;
            align-items: stretch;
        }

        .dtr-panel {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 18px;
            padding: 1rem;
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }

        .dtr-panel .dtr-table-shell {
            flex: 1 1 auto;
        }
    </style>

    <div class="dtr-board">
        <div class="dtr-header">
            <div class="dtr-header-title">
                <h4>Daily Time Record</h4>
                <span>Time Log Record Viewer</span>
            </div>
            @if (Auth::user()->division != "2")
                <div>
                <button class="btn btn-danger" onclick="printDTR()" title="Print Individual DTR">
                    <i class="bi bi-printer"></i>
                    PRINT DTR
                </button>
            </div>                
            @endif
        </div>

        <div class="dtr-filters">
            <div class="row g-3 align-items-end">
                <div class="col-md-3">
                    <div class="form-floating">
                        <select class="form-control" id="month">
                            <option value="" selected disabled>Select month</option>
                            <option value="1">January</option>
                            <option value="2">February</option>
                            <option value="3">March</option>
                            <option value="4">April</option>
                            <option value="5">May</option>
                            <option value="6">June</option>
                            <option value="7">July</option>
                            <option value="8">August</option>
                            <option value="9">September</option>
                            <option value="10">October</option>
                            <option value="11">November</option>
                            <option value="12">December</option>
                        </select>
                        <label for="month">Month</label>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-floating">
                        <select class="form-control" id="year">
                            <option value="">Select year</option>
                            @for($year=2023; $year<= \Carbon\Carbon::now()->format('Y'); $year++)
                                <option>{{ $year }}</option>
                            @endfor
                        </select>
                        <label for="year" class="form-label">Year</label>
                    </div>
                </div>
            </div>
        </div>

        <div class="dtr-grid">
            <div class="dtr-panel">
                <div class="dtr-section-title">DTR</div>
                <div class="table-responsive dtr-table-shell">
                    <table class="table table-bordered text-center">
                        <thead>
                            <tr>
                                <th>Day</th>
                                <th>Time In</th>
                                <th>Time Out</th>
                            </tr>
                        </thead>
                        <tbody id="dtr_list">
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="dtr-panel">
                <div class="dtr-section-title">Raw Data</div>
                <div class="table-responsive dtr-table-shell">
                    <table class="table table-bordered text-center">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Status</th>
                                <th>Time</th>
                            </tr>
                        </thead>
                        <tbody id="raw_list">
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    @include('modals.dtr')
@stop

@section('js')
    <script>
        $(document).ready(function () {
            $("#month,#year").change(function () {
                getDtrLogs();
            });

            $("#btn_print").click(function () {
                const month = $("#month").val();
                const year = $("#year").val();
                const cutoff = $("#cutoff").val();
                const reg_days_am = $("#reg_days_am").val();
                const reg_days_pm = $("#reg_days_pm").val();
                const sat_days_am = $("#sat_days_am").val();
                const sat_days_pm = $("#sat_days_pm").val();

                window.open(
                    '{{ route('printDTR') }}?month=' + month + "&year=" + year + "&cutoff=" + cutoff + "&reg_days_am=" + reg_days_am + "&reg_days_pm=" + reg_days_pm + "&sat_days_am=" + sat_days_am + "&sat_days_pm=" + sat_days_pm,
                    '_blank' // <- This is what makes it open in a new window.
                );
            });
        });

        function printDTR() {
            $("#print_dtr_modal").modal("show");
        }


        async function getDtrLogs() {
            const month = $("#month").val();
            const year = $("#year").val();

            if (month === "" || year === "") return;

            fetch('{{ route('getDTRLogs') }}?month=' + month + "&year=" + year, {
                method: "GET"
            })
                .then(res => res.json())
                .then(data => {
                    $("#dtr_list").empty();
                    $("#raw_list").empty();
                    if (data.code == 500) {
                        alert("Something went wrong, please reload the page");
                        return;
                    }

                    data.results.forEach(it => {
                        const date_timein = (it.date_timein) ? moment(it.date_timein).format("hh:mm A") : "";
                        const date_timeout = (it.date_timeout) ? moment(it.date_timeout).format("hh:mm A") : "";

                        let tr = `<tr>`;
                        tr += `<td>` + it.day + `</td>`;
                        tr += `<td>` + date_timein + `</td>`;
                        tr += `<td>` + date_timeout + `</td>`;
                        tr += "</tr>";
                        $("#dtr_list").append(tr);
                    });

                    if (data.raw_data.length < 1) {
                        $("#raw_list").append("<tr><td class='text-center' colspan='3'>No record found</td></tr>");
                        return;
                    }

                    data.raw_data.forEach(it => {
                        const date = moment(it.created_at).format("MM/DD/YYYY");
                        const time = moment(it.created_at).format("hh:mm:ss A");

                        const imageUrl = "/hris/storage/" + it.photo_path; // Assuming 'photo_path' is the column where the image file path is stored

                        let tr = `<tr>`;
                        tr += `<td>` + date + `</td>`;
                        tr += `<td>` + it.status + `</td>`;
                        tr += `<td><a>${time}</a></td>`;
                        tr += "</tr>";
                        $("#raw_list").append(tr);
                    });

                }).catch(err => {
                console.log(err);
            });
        }
    </script>
@stop

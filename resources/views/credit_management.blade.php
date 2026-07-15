@extends('master')
@section('content')
<style>
.credit-mgmt-board{background:#fff;border:1px solid #e2e8f0;border-radius:20px;padding:1.5rem;box-shadow:0 18px 38px rgba(15,23,42,.08)}
.credit-mgmt-head{margin-bottom:1.25rem}
.credit-mgmt-head h4{margin:0;font-weight:700;color:#0f172a}
.credit-mgmt-head span{font-size:.9rem;color:#64748b}
.credit-mgmt-filter{background:#f8fafc;border:1px solid #e2e8f0;border-radius:16px;padding:1rem;margin-bottom:1.25rem}
.credit-mgmt-filter .form-control,.credit-mgmt-filter .form-select{border-radius:12px;border:1px solid #d8e1ee;background:#fff}
.credit-mgmt-table-shell{border:1px solid #e2e8f0;border-radius:16px;overflow:visible;background:#fff}
.credit-mgmt-table-shell table{margin-bottom:0}
.credit-mgmt-table-shell thead th{background:#f8fafc;color:#0f172a;font-size:.72rem;text-transform:uppercase;letter-spacing:.08em}
.credit-mgmt-table-shell tbody td{color:#1e293b;font-weight:500;vertical-align:middle}
</style>
    <div class="credit-mgmt-board">
        <div class="credit-mgmt-head">
            <h4>Leave Credit Management</h4>
            <span>Update and review monthly leave credit earnings by division and section.</span>
        </div>
        <div class="credit-mgmt-filter">
            <div class="row g-3 align-items-end">
                <div class="col-md-2">
                    <div class="form-floating">
                        <select class="form-select form-control-sm" id="month">
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

                <div class="col-md-2">
                    <div class="form-floating">
                        <select class="form-select form-control-sm" id="year">
                            <option value="" selected disabled>Select year</option>
                            @for($year = 2023; $year <= \Carbon\Carbon::now()->format('Y'); $year++)
                                <option>{{ $year }}</option>
                            @endfor
                        </select>
                        <label for="year">Year</label>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-floating">
                        <select class="form-select form-control-sm" id="division">

                        </select>
                        <label for="month">Division</label>
                    </div>
                </div>

                <div class="col-md-3">
                    <div class="form-floating">
                        <select class="form-select form-control-sm" id="section">

                        </select>
                        <label for="year">Section</label>
                    </div>
                </div>
            </div>
        </div>

        <div class="fw-semibold mb-2" style="color:#0f172a">Leave Credit Earned Logs</div>
        <div class="table-responsive credit-mgmt-table-shell">
            <table class="table table-bordered align-middle text-center">
                <thead>
                <tr>
                    <th>Employee</th>
                    <th>Month &amp; Year</th>
                    <th>Total Present</th>
                    <th>Total Late/Undertime (mins)</th>
                    <th>Leave Type</th>
                    <th>Points Earned</th>
                    <th>Points Deducted</th>
                    <th>Total</th>
                </tr>
                </thead>
                <tbody id="employee_list">
                <tr>
                    <td colspan="9" class="text-center text-muted">No records found. Please select month &amp; year.</td>
                </tr>
                </tbody>
            </table>
        </div>

    </div>
@stop

@section('js')
    <script>
        $(document).ready(function () {
            loadDivision();

            $("#month,#year,#division,#section").change(function () {
                const month = $("#month").val();
                const year = $("#year").val();
                const division = $("#division").val();
                const section = $("#section").val();

                if (month == "" || year == "" || division == "" || section == "") return;

                getCreditEarned(month, year, division, section);
            });

            $("#division").change(function () {
                const division = $("#division").val();
                fetch("{{ route('getSectionList') }}?division=" + division, {
                    method: "GET"
                })
                    .then(response => response.json())
                    .then(data => {
                        $("#section").empty();
                        if (division == 0) {
                            $("#section").addClass("d-none");
                        } else {
                            $("#section").removeClass("d-none");
                        }

                        $("#section").append(`<option value="">----Select Section----</option>`);

                        data.forEach(it => {
                            const option = `<option value="` + it.id + `">` + it.description + `</option>`;
                            $("#section").append(option);
                        });
                    }).catch(err => {
                    alert("Ooops, something went wrong. Please reload");
                });
            });

            getCreditEarned();
        });

        function loadDivision() {
            fetch("{{ route('getDivisionList') }}", {
                method: "GET"
            })
                .then(response => response.json())
                .then(data => {
                    $("#division").append(`<option value="">----Select Division----</option>`);
                    data.forEach(it => {
                        const option = `<option value="${it.id}">${it.description}</option>`;
                        $("#division").append(option);
                    });
                })
                .catch(err => {
                    alert("Ooops, something went wrong. Please reload");
                });
        }

        function getCreditEarned(month, year, division, section) {

            const url = `{{ route('getCreditEarned') }}?month=${month}&year=${year}&division=${division}&section=${section}`;

            fetch(url, {method: "GET"})
                .then(response => response.json())
                .then(data => {
                    const $tableBody = $("#employee_list");
                    $tableBody.empty();

                    if (data.error) {
                        toastr.error(data.error, "Oops");
                        return;
                    }

                    if (data.length === 0) {
                        $tableBody.append(`
                        <tr>
                            <td colspan="9" class="text-center text-muted">No records found for the selected month and year.</td>
                        </tr>
                    `);
                        return;
                    }

                    data.forEach(log => {
                        const fullName = `${log.fname} ${log.mname ? log.mname + ' ' : ''}${log.lname}${log.suffix ? ', ' + log.suffix : ''}`;
                        const monthYear = `${getMonthName(log.month)} ${log.year}`;
                        const row = `
                        <tr class="text-center">
                            <td class="text-start">${fullName}</td>
                            <td>${monthYear}</td>
                            <td>${log.total_present}</td>
                            <td>${log.total_lateundertime_minutes}</td>
                            <td>${log.description}</td>
                           <td>${formatThreeDecimals(log.earned_points)}</td>
                           <td>${formatThreeDecimals(log.deducted_points)}</td>
                           <td>${formatThreeDecimals((parseFloat(log.earned_points || 0) - parseFloat(log.deducted_points || 0)))}</td>
                        </tr>
                    `;
                        $tableBody.append(row);
                    });
                })
                .catch(err => {
                    toastr.error("Failed to fetch credit earned logs.", "Oops");
                    console.error(err);
                });
        }

        function getMonthName(monthNumber) {
            const monthNames = [
                "January", "February", "March", "April", "May", "June",
                "July", "August", "September", "October", "November", "December"
            ];
            return monthNames[monthNumber - 1];
        }

        function formatThreeDecimals(value) {
            const numeric = parseFloat(value ?? 0);
            return Number.isNaN(numeric) ? "0.000" : numeric.toFixed(3);
        }
    </script>
@stop

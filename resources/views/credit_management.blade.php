@extends('master')
@section('content')
    <div class="card shadow-sm border-0 rounded-3" style="background-color: #f8f9fa;">
        <div class="card-header bg-white border-bottom py-3">
            <h4 class="mb-0 fw-bold text-dark">📊 Leave Credit Management</h4>
        </div>
        <div class="card-body bg-white">
            <!-- Filter Section -->
            <div class="row g-3 align-items-end mb-4 mt-3">
                <div class="col-md-3">
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

                <div class="col-md-3">
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
                    <button class="btn btn-success w-100 btn-lg" onclick="updateCredits()">Update Credits</button>
                </div>
            </div>

            <!-- Divider -->
            <hr class="my-4">

            <!-- Leave Credit Earned Logs -->
            <h5 class="fw-bold text-dark mb-3">📅 Leave Credit Earned Logs</h5>

            <div class="table-responsive">
                <table class="table table-bordered align-middle" style="background-color: #fff;">
                    <thead class="table-light">
                    <tr class="text-center">
                        <th>Employee</th>
                        <th>Month & Year</th>
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
                        <td colspan="9" class="text-center text-muted">No records found. Please select month & year.
                        </td>
                    </tr>
                    </tbody>
                </table>
            </div>

        </div>
    </div>
@stop

@section('js')
    <script>
        $(document).ready(function () {
            $("#month,#year").change(function () {
                getCreditEarned();
            });

            getCreditEarned();
        });

        function updateCredits() {
            const month = $("#month").val();
            const year = $("#year").val();

            if (month == "" || year == "") {
                alert("Please fill in month and year");
                return;
            }

            if (confirm(`Are you sure you want to update credits?`)) {
                fetch('{{ route('updateCreditBalance') }}', {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json",
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    }, body: JSON.stringify({month: month, year: year})
                })
                    .then(res => res.json())
                    .then(data => {
                        toastr.success(data.message, 'Information');
                    }).catch(err => {
                    toastr.error(err, "Oops");
                });
            }
        }

        function getCreditEarned() {
            const month = $("#month").val();
            const year = $("#year").val();

            if (!month || !year) return;


            const url = `{{ route('getCreditEarned') }}?month=${month}&year=${year}`;

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
                           <td>${parseFloat(log.earned_points).toFixed(3)}</td>
                           <td>${parseFloat(log.deducted_points).toFixed(3)}</td>
                           <td>${(parseFloat(log.earned_points) - parseFloat(log.deducted_points)).toFixed(3)}</td>
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
    </script>
@stop

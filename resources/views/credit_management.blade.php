@extends('master')
@section('content')
    <div class="card shadow-sm border-0 rounded-3" style="background-color: #f8f9fa;">
        <div class="card-header bg-white border-bottom py-3">
            <h4 class="mb-0 fw-bold text-dark">📊 Leave Credit Management</h4>
        </div>
        <div class="card-body bg-white">
            <!-- Filter Section -->
            <div class="row g-3 align-items-end mb-4 mt-3">
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
                <div class="col-md-2">
                    <button id="updateCreditsBtn" class="btn btn-success w-100 btn-lg" onclick="updateCredits()">
                        <span id="updateCreditsText">Update Credits</span>
                        <span id="updateCreditsLoader" class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                    </button>
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
                    data.forEach(it => {
                        const option = `<option value="${it.id}">${it.description}</option>`;
                        $("#division").append(option);
                    });
                })
                .catch(err => {
                    alert("Ooops, something went wrong. Please reload");
                });
        }

        function updateCredits() {
            const month = $("#month").val();
            const year = $("#year").val();
            const division = $("#division").val();
            const section = $("#section").val();

            if (month === "" || year === "" || division === "" || section === "") {
                Swal.fire({
                    icon: "warning",
                    title: "Missing Fields",
                    text: "Please fill in all filter fields"
                });
                return;
            }

            Swal.fire({
                title: "Are you sure?",
                text: "Do you want to update credits?",
                icon: "question",
                showCancelButton: true,
                confirmButtonText: "Yes, update",
                cancelButtonText: "Cancel"
            }).then((result) => {
                if (result.isConfirmed) {
                    // disable button and show loader
                    const btn = document.getElementById("updateCreditsBtn");
                    const btnText = document.getElementById("updateCreditsText");
                    const btnLoader = document.getElementById("updateCreditsLoader");

                    btn.disabled = true;
                    btnText.textContent = "Updating...";
                    btnLoader.classList.remove("d-none");

                    fetch('{{ route('updateCreditBalance') }}', {
                        method: "POST",
                        headers: {
                            "Content-Type": "application/json",
                            "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr("content")
                        },
                        body: JSON.stringify({
                            month: month,
                            year: year,
                            division: division,
                            section: section
                        })
                    })
                        .then((res) => res.json())
                        .then((data) => {
                            Swal.fire({
                                icon: "success",
                                title: "Updated",
                                text: data.message
                            });
                            getCreditEarned(month, year, division, section);
                        })
                        .catch((err) => {
                            Swal.fire({
                                icon: "error",
                                title: "Oops!",
                                text: err
                            });
                        })
                        .finally(() => {
                            // re-enable button
                            btn.disabled = false;
                            btnText.textContent = "Update Credits";
                            btnLoader.classList.add("d-none");
                        });
                }
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

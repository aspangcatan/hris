@extends('master')
@section('content')

    <div class="card">
        <div class="card-header">
            <h4>Time Log Record Viewer</h4>
        </div>
        <div class="card-body">
            <!-- Filter Section -->
            <div class="row mb-3 mt-3">
                <div class="col-md-3">
                    <div class="form-floating mb-3">
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
                        <label for="floatingInput">Month</label>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-floating mb-3">
                        <select class="form-control" id="year">
                            <option value="">Select year</option>
                            @for($year=2023; $year<= \Carbon\Carbon::now()->format('Y'); $year++)
                                <option>{{ $year }}</option>
                            @endfor
                        </select>
                        <label for="year" class="form-label">Year</label>
                    </div>
                </div>
                <!-- DTR Table -->
                <div class="col-md-2">
                    <button class="btn btn-danger" style="padding: 1rem 1rem;" onclick="printDTR()">
                        <i class="bi bi-printer mr-2"></i>
                        PRINT INDIVIDUAL DTR
                    </button>
                    @if(false)
                        <button class="btn btn-danger" style="padding: 1rem 1rem;" onclick="printDTR()">
                            <i class="bi bi-printer mr-2"></i>
                            PRINT DTR
                        </button>
                    @endif
                </div>
            </div>


            <div class="row">
                <div class="col-md-6">
                    <div class="table-responsive">
                        <h3>DTR</h3>
                        <table class="table table-bordered text-center">
                            <thead class="table-dark">
                            <tr>
                                <th>Day</th>
                                <th>Time In</th>
                                <th>Time Out</th>
                            </tr>
                            </thead>
                            <tbody id="dtr_list">

                            <!-- More rows can be dynamically added here -->
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="table-responsive">
                        <h3>Raw Data</h3>
                        <table class="table table-bordered text-center">
                            <thead class="table-dark">
                            <tr>
                                <th>Date</th>
                                <th>Status</th>
                                <th>Time</th>
                            </tr>
                            </thead>
                            <tbody id="raw_list">

                            <!-- More rows can be dynamically added here -->
                            </tbody>
                        </table>
                    </div>
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
        });

        function printDTR() {
            const month = $("#month").val();
            const year = $("#year").val();

            window.open(
                '{{ route('printDTR') }}?month=' + month + "&year=" + year,
                '_blank' // <- This is what makes it open in a new window.
            );
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
                        tr += `<td><a href="${imageUrl}" target="_blank">${time}</a></td>`;
                        tr += "</tr>";
                        $("#raw_list").append(tr);
                    });

                }).catch(err => {
                console.log(err);
            });
        }
    </script>
@stop

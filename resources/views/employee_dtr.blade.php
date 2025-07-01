@extends('master')
@section('content')
    <div class="d-flex justify-content-end mb-2">
        @if(session('access_rights') == 'admin')
            <div class="ms-2 me-1">
                <select class="form-control" id="search_division">
                    <option value="">Search division</option>
                </select>
            </div>
            <div>
                <select class="form-control d-none" id="search_section">
                </select>
            </div>
        @endif
        <div class="ms-2 me-1">
            <input class="form-control" placeholder="Search by firstname" id="search_fname"/>
        </div>
        <div class="ms-2 me-1">
            <input type="text" class="form-control" placeholder="Search by lastname" id="search_lname"/>
        </div>
    </div>

    <div class="row mt-5" id="employee-container"></div>

    @include('modals.employee_dtr')
@stop
@section('js')
    <script>
        let userid = 0;
        $(document).ready(function () {
            getEmployeeDtr();
            loadDivision();

            $("#month,#year").change(function () {
                viewDtr(userid);
            });

            $("#btn_print").click(function () {
                const month = $("#month").val();
                const year = $("#year").val();
                const cutoff = undefined;
                window.open(
                    '{{ route('printDTR') }}?month=' + month + "&year=" + year + "&cutoff=" + cutoff + "&userid=" + userid,
                    '_blank' // <- This is what makes  t open in a new window.
                );
            });

            $("#btn_save_log").click(async function () {
                const date = $("#log_date").val();
                const time = $("#log_time").val();
                const status = $("#log_status").val();
                const image = $("#log_image")[0].files[0];

                if (!date || !time || !image) {
                    alert("All fields are required.");
                    return;
                }

                const formData = new FormData();
                formData.append("userid", userid);
                formData.append("date", date);
                formData.append("time", time);
                formData.append("status", status);
                formData.append("photo", image);
                try {
                    const response = await fetch("{{ route('storeManual') }}", {
                        method: "POST",
                        headers: {
                            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                        },
                        body: formData
                    });

                    if (!response.ok) {
                        const error = await response.json();
                        throw new Error(error.message || "Upload failed");
                    }

                    const result = await response.json();
                    alert("Log saved successfully!");
                    $("#add_log_modal").modal('hide');
                } catch (err) {
                    console.error(err);
                    alert("Error saving log: " + err.message);
                }
            });


            $("#search_division").change(function () {
                const division = $("#search_division").val();
                fetch("{{ route('getSectionList') }}?division=" + division, {
                    method: "GET"
                })
                    .then(response => response.json())
                    .then(data => {
                        $("#search_section").empty();
                        if (division == 0) {
                            $("#search_section").addClass("d-none");
                        } else {
                            $("#search_section").removeClass("d-none");
                        }

                        data.forEach(it => {
                            const option = `<option value="` + it.id + `">` + it.description + `</option>`;
                            $("#search_section").append(option);
                        });
                    }).catch(err => {
                    alert("Ooops, something went wrong. Please reload");
                });
            });

            $("#search_section").change(function () {
                getEmployeeDtr();
            });

            $("#search_fname, #search_lname").on("keypress", function (e) {
                if (e.which === 13) { // 13 is the Enter key code
                    getEmployeeDtr(); // Call your fetch function
                }
            });
        });

        function loadDivision() {
            fetch("{{ route('getDivisionList') }}", {
                method: "GET"
            })
                .then(response => response.json())
                .then(data => {
                    data.forEach(it => {
                        const option = `<option value="` + it.id + `">` + it.description + `</option>`;
                        $("#search_division").append(option);
                    });
                }).catch(err => {
                alert("Ooops, something went wrong. Please reload");
            });
        }

        async function getEmployeeDtr() {
            const search_division = ($("#search_division").val() === undefined) ? "" : $("#search_division").val();
            let search_section = $("#search_section").val();
            if (search_division == "")
                search_section = "";

            const search_fname = $("#search_fname").val();
            const search_lname = $("#search_lname").val();

            fetch("{{ route('getEmployeeDtr') }}?search_section=" + search_section +
                "&search_division=" + search_division +
                "&search_fname=" + search_fname +
                "&search_lname=" + search_lname, {
                method: "GET"
            })
                .then(response => response.json())
                .then(data => {
                    const container = $("#employee-container"); // jQuery selector
                    container.empty(); // Clear previous content
                    const path = '{{ url('/avatar/')}}';

                    data.forEach(it => {

                        const image = (it.picture) ? (path + "/" + it.picture) : "assets/img/logo.png";
                        const timeIn = it.time_in ? new Date(it.time_in).toLocaleTimeString([], {
                            hour: '2-digit',
                            minute: '2-digit'
                        }) : "N/A";
                        const timeOut = it.time_out ? new Date(it.time_out).toLocaleTimeString([], {
                            hour: '2-digit',
                            minute: '2-digit'
                        }) : "N/A";

                        const employeeCard = `
                <div class="col-md-3">
                    <div class="card custom-card">
                        <div class="card-header custom-card-header d-flex justify-content-between align-items-center">
                            <div class="d-flex align-items-center">
                                <img src="` + image + `"
                                     class="rounded-circle custom-avatar">
                                <div class="ms-3">
                                    <p class="font-weight-bold mb-0 custom-name" style="font-size: 18px;">
                                        ${it.fname} ${it.mname ? it.mname[0] + '.' : ''} ${it.lname}
                                    </p>
                                    <p class="text-muted custom-designation" style="font-size: 14px; font-weight: 300;">
                                        ${it.designation}
                                    </p>
                                </div>
                            </div>
                        </div>
                        <div class="card-body custom-card-body">
                            <div class="d-flex align-items-center mt-3">
                                <div class="font-weight-bold custom-label">Time In:</div>
                                <div class="ms-2 custom-value">${timeIn}</div>
                            </div>
                            <div class="d-flex align-items-center">
                                <div class="font-weight-bold custom-label">Time Out:</div>
                                <div class="ms-2 custom-value text-warning">${timeOut}</div>
                            </div>
                            <div class="d-flex justify-content-end mt-3">
                                <button class="btn btn-sm btn-success me-1" onclick="addLog(` + it.id + `)">
                                    <i class="fas fa-clock mr-1"></i>
                                    ADD LOG
                                </button>
                                <button class="btn btn-sm btn-primary ms-1" onclick="viewDtr(` + it.id + `)">
                                    <i class="fas fa-eye mr-1"></i>
                                    VIEW DTR
                                </button>
                            </div>
                        </div>
                    </div>
                </div>`;

                        container.append(employeeCard); // jQuery append
                    });
                })
                .catch(error => {
                    console.log("Error fetching employee DTR:", error);
                });
        }

        async function addLog(id) {
            userid = id;
            $("#add_log_modal").modal("show");
        }

        async function viewDtr(id) {
            userid = id;
            let month = $("#month").val();
            let year = $("#year").val();

            if (!month && !year) {
                month = moment().format("M");
                year = moment().format("YYYY");
            }

            fetch('{{ route('getDTRLogs') }}?month=' + month + "&year=" + year + "&userid=" + id, {
                method: "GET"
            })
                .then(res => res.json())
                .then(data => {
                    $("#dtr_modal").modal("show");

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

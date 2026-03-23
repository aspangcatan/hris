@extends('master')
@section('content')
    <div class="d-flex justify-content-end mb-2">
        @if(session('access_rights') == 'admin' || session('access_rights') == 'DH')
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

    <div id="loading-message" class="text-center my-4" style="display: none;">
        <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Loading...</span>
        </div>
        <p class="mt-2">Loading, please wait while we fetch the data...</p>
    </div>

    <div class="row mt-5" id="employee-container">
        <!-- DTR cards will be appended here -->
    </div>

    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mt-3">
        <div class="text-muted small" id="employee_range">Showing 0-0 of 0</div>
        <nav aria-label="Employee DTR pagination">
            <ul class="pagination mb-0" id="employee-pagination"></ul>
        </nav>
    </div>

    @include('modals.employee_dtr')
@stop
@section('js')
    <script>
        let userid = 0;
        let log_id = 0;
        let currentPage = 1;
        const perPage = 12;
        const accessRights = @json(strtolower((string) session('access_rights')));
        const canEditRawTimelog = ["admin", "it","dh"].includes(accessRights);
        $(document).ready(function () {
            getEmployeeDtr(1);
            loadDivision();

            $("#month,#year").change(function () {
                viewDtr(userid);
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
                    '{{ route('printDTR') }}?month=' + month + "&year=" + year + "&cutoff=" + cutoff + "&userid=" + userid + "&reg_days_am=" + reg_days_am + "&reg_days_pm=" + reg_days_pm + "&sat_days_am=" + sat_days_am + "&sat_days_pm=" + sat_days_pm,
                    '_blank' // <- This is what makes  t open in a new window.
                );
            });



            $("#btn_save_log").click(async function () {
                if (!canEditRawTimelog) {
                    alert("Permission Denied");
                    return;
                }

                const date = $("#log_date").val();
                const time = $("#log_time").val();
                const status = $("#log_status").val();

                const datetime = moment(`${date} ${time}`, "YYYY-MM-DD HH:mm");


                if (!date || !time) {
                    alert("All fields are required.");
                    return;
                }
                    Swal.fire({
                        title: "Confirm Action",
                        html: `Are you sure you want to set the time
               <b>${datetime.format("hh:mm A")}</b>
               on <b>${datetime.format("MMMM D, YYYY")}</b>
               with a status of <b>${status}</b>?`,
                        icon: "question",
                        showCancelButton: true,
                        confirmButtonText: "Yes, confirm",
                        cancelButtonText: "Cancel",
                        confirmButtonColor: "#3085d6",
                        cancelButtonColor: "#d33"
                    }).then(async (result) => {
                        if (result.isConfirmed) {
                            const params = {
                                log_id: log_id,
                                userid: userid,
                                date: date,
                                time: time,
                                status: status
                            }

                            try {
                                const response = await fetch("{{ route('storeManual') }}", {
                                    method: "POST",
                                    headers: {
                                        "Content-Type": "application/json",
                                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                                    },
                                    body: JSON.stringify(params)
                                });

                                if (!response.ok) {
                                    const error = await response.json();
                                    throw new Error(error.message || "Upload failed");
                                }

                                const result = await response.json();
                                toastr.success(result.message, "Information");
                                $("#add_log_modal").modal("hide");
                                // optionally reset form
                                $("#log_date").val("");
                                $("#log_time").val("");
                                $("#log_status").val("");

                                viewDtr(userid);

                            } catch (err) {
                                console.error(err);
                                alert("Error saving log: " + err.message);
                            }
                        }
                });
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
                getEmployeeDtr(1);
            });

            $("#search_fname, #search_lname").on("keypress", function (e) {
                if (e.which === 13) { // 13 is the Enter key code
                    getEmployeeDtr(1); // Call your fetch function
                }
            });

            $(document).on("click", ".empty-field", function (e) {
                e.preventDefault();

                log_id = 0;
                const status = $(this).data("type");   // "timein" or "timeout"
                const day = $(this).data("day");

                // Get values or fallback to current date
                let month = $("#month").val();
                let year = $("#year").val();

                const now = moment(); // current date/time

                if (!month) {
                    month = now.format("MM"); // current month
                }

                if (!year) {
                    year = now.format("YYYY"); // current year
                }

                // Construct and format the date
                const date = moment(`${year}-${month}-${day}`, "YYYY-MM-DD").format("YYYY-MM-DD");

                // Show modal and populate fields
                $("#add_log_modal").modal("show");
                $("#log_date").val(date);
                $("#log_status").val(status);
            });

            $("#raw_list").on("click", ".edit-btn", function () {
                if (!canEditRawTimelog) {
                    alert("Permission Denied");
                    return;
                }

                log_id = $(this).data("id");
                const date = $(this).data("date");
                const status = $(this).data("status");
                const time = $(this).data("time");

                // Populate modal fields
                $("#log_date").val(moment(date, "MM/DD/YYYY").format("YYYY-MM-DD"));
                $("#log_status").val(status);
                $("#log_time").val(moment(time, "hh:mm:ss A").format("HH:mm"));

                // Show the modal
                $("#add_log_modal").modal("show");
            });

            $("#raw_list").on("click", ".delete-btn", function () {
                if (!canEditRawTimelog) {
                    alert("Permission Denied");
                    return;
                }

                log_id = $(this).data("id");
                const date = $(this).data("date");
                const status = $(this).data("status");
                const time = $(this).data("time");

                Swal.fire({
                    title: "Confirm Action",
                    html: `Are you sure you want to delete the log time
               <b>${time}</b>
               on <b>${date}</b>
               with a status of <b>${status}</b>?`,
                    icon: "question",
                    showCancelButton: true,
                    confirmButtonText: "Yes, confirm",
                    cancelButtonText: "Cancel",
                    confirmButtonColor: "#3085d6",
                    cancelButtonColor: "#d33"
                }).then(async (result) => {
                    if (result.isConfirmed) {
                        const params = {
                            log_id: log_id
                        }

                        try {
                            const response = await fetch("{{ route('deleteTimeLog') }}", {
                                method: "DELETE",
                                headers: {
                                    "Content-Type": "application/json",
                                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                                },
                                body: JSON.stringify(params)
                            });

                            if (!response.ok) {
                                const error = await response.json();
                                throw new Error(error.message || "Upload failed");
                            }

                            const result = await response.json();
                            toastr.success(result.message, "Information");
                            viewDtr(userid);

                        } catch (err) {
                            console.error(err);
                            alert("Error saving log: " + err.message);
                        }
                    }
                });
            });
        });

        function addRawLog(){
            if (!canEditRawTimelog) {
                alert("Permission Denied");
                return;
            }

            // Populate modal fields
            $("#log_date").val("");
            $("#log_status").val("");
            $("#log_time").val("");

            // Show the modal
            $("#add_log_modal").modal("show");
        }

        function loadDivision() {
            const division = @json(Auth::user()->division);
            const access = @json(session('access_rights'));

            fetch("{{ route('getDivisionList') }}", {
                method: "GET"
            })
                .then(response => response.json())
                .then(data => {
                    data.forEach(it => {
                        if (access === "DH" && division != it.id) {
                            return; // Skip non-matching divisions for DH
                        }

                        const option = `<option value="${it.id}">${it.description}</option>`;
                        $("#search_division").append(option);
                    });
                })
                .catch(err => {
                    alert("Ooops, something went wrong. Please reload");
                });
        }

        function renderPagination(meta) {
            const container = $("#employee-pagination");
            container.empty();

            if (!meta || meta.last_page <= 1) {
                return;
            }

            const current = meta.current_page;
            const last = meta.last_page;

            const buildItem = (page, label, disabled = false, active = false) => {
                const liClass = `page-item${disabled ? " disabled" : ""}${active ? " active" : ""}`;
                const dataAttr = disabled ? "" : `data-page=\"${page}\"`;
                return `<li class=\"${liClass}\"><a class=\"page-link\" href=\"#\" ${dataAttr}>${label}</a></li>`;
            };

            container.append(buildItem(current - 1, "&laquo;", current === 1));

            const start = Math.max(1, current - 2);
            const end = Math.min(last, current + 2);

            if (start > 1) {
                container.append(buildItem(1, "1", false, current === 1));
                if (start > 2) {
                    container.append(buildItem(0, "...", true));
                }
            }

            for (let page = start; page <= end; page++) {
                container.append(buildItem(page, page, false, page === current));
            }

            if (end < last) {
                if (end < last - 1) {
                    container.append(buildItem(0, "...", true));
                }
                container.append(buildItem(last, last, false, current === last));
            }

            container.append(buildItem(current + 1, "&raquo;", current === last));
        }

        $(document).on("click", "#employee-pagination .page-link", function (e) {
            e.preventDefault();
            const page = parseInt($(this).data("page"), 10);
            if (!page || page === currentPage) {
                return;
            }
            getEmployeeDtr(page);
        });

        async function getEmployeeDtr(page = 1) {
            const search_division = ($("#search_division").val() === undefined) ? "" : $("#search_division").val();
            let search_section = $("#search_section").val();
            if (search_division == "")
                search_section = "";

            const search_fname = $("#search_fname").val();
            const search_lname = $("#search_lname").val();

            const container = $("#employee-container");
            const loading = $("#loading-message");

            container.empty();         // Clear old results
            loading.show();            // Show loading spinner
            $("#employee-pagination").empty();
            $("#employee_range").text("Showing 0-0 of 0");
            currentPage = page;

            fetch("{{ route('getEmployeeDtr') }}?search_section=" + search_section +
                "&search_division=" + search_division +
                "&search_fname=" + search_fname +
                "&search_lname=" + search_lname +
                "&page=" + page +
                "&per_page=" + perPage, {
                method: "GET"
            })
                .then(response => response.json())
                .then(data => {
                    const list = data.data || [];
                    const meta = {
                        current_page: data.current_page || 1,
                        last_page: data.last_page || 1,
                        total: data.total || list.length,
                        from: data.from || (list.length ? 1 : 0),
                        to: data.to || list.length
                    };

                    $("#employee_range").text(`Showing ${meta.from}-${meta.to} of ${meta.total}`);
                    renderPagination(meta);

                    if (!list.length) {
                        container.append(`
                            <div class="col-12">
                                <div class="card p-4 text-center text-muted">
                                    <div class="mb-2"><i class="bi bi-archive"></i></div>
                                    <div>No employee records found.</div>
                                </div>
                            </div>
                        `);
                        loading.hide();
                        return;
                    }

                    list.forEach(it => {
                        const image = (it.picture) ? "https://dohcsmc.com/id/storage/crop/"+it.picture : "assets/img/logo.png";
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
                                <img src="${image}" class="rounded-circle custom-avatar">
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
                            <div class="d-flex justify-content-end mt-3">
                                <button class="btn btn-standard btn-primary ms-1" onclick="viewDtr(${it.id})">
                                    <i class="fas fa-eye mr-1"></i>
                                    VIEW DTR
                                </button>
                            </div>
                        </div>
                    </div>
                </div>`;
                        container.append(employeeCard);
                    });

                    loading.hide(); // Hide loading spinner after rendering
                })
                .catch(error => {
                    console.log("Error fetching employee DTR:", error);
                    loading.hide(); // Hide even on error
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

            $("#month").val(month);
            $("#year").val(year);


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

                        const timeinCell = date_timein
                            ? date_timein
                            : `<a href="#" class="empty-field" data-type="IN" data-id="${it.id}" data-day="${it.day}">EMPTY</a>`;

                        const timeoutCell = date_timeout
                            ? date_timeout
                            : `<a href="#" class="empty-field" data-type="OUT" data-id="${it.id}" data-day="${it.day}">EMPTY</a>`;

                        let tr = `<tr>`;
                        tr += `<td>${it.day}</td>`;
                        tr += `<td>${timeinCell}</td>`;
                        tr += `<td>${timeoutCell}</td>`;
                        tr += `</tr>`;

                        $("#dtr_list").append(tr);
                    });

                    if (data.raw_data.length < 1) {
                        $("#raw_list").append("<tr><td class='text-center' colspan='3'>No record found</td></tr>");
                        return;
                    }

                    data.raw_data.forEach(it => {
                        const date = moment(it.created_at).format("MM/DD/YYYY");
                        const time = moment(it.created_at).format("hh:mm:ss A");

                        let tr = "";
                        if (canEditRawTimelog) {
                            tr = $(`
                            <tr>
                                <td>${date}</td>
                                <td>${it.status}</td>
                                <td><a target="_blank">${time}</a></td>
                                <td>
                                    <button
                                        class="btn btn-action btn-edit edit-btn"
                                        data-id="${it.id}"
                                        data-date="${date}"
                                        data-status="${it.status}"
                                        data-time="${time}"
                                        title="Edit">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                </td>
                                 <td>
                                    <button
                                        class="btn btn-action btn-delete delete-btn"
                                        data-id="${it.id}"
                                        data-date="${date}"
                                        data-status="${it.status}"
                                        data-time="${time}"
                                        title="Delete">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </td>
                            </tr>`);
                        } else {
                            tr = $(`
                            <tr>
                                <td>${date}</td>
                                <td>${it.status}</td>
                                <td><a target="_blank">${time}</a></td>
                            </tr>`);
                        }
                        $("#raw_list").append(tr);
                    });

                }).catch(err => {
                console.log(err);
            });
        }
    </script>
@stop

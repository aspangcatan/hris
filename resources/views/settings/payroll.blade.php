@extends('master')
@section('content')
    <div class="d-flex justify-content-end mb-2">
        <div>
            <input class="form-control" placeholder="Search by firstname" id="search_fname"/>
        </div>
        <div class="ms-2 me-1">
            <input type="text" class="form-control" placeholder="Search by lastname" id="search_lname"/>
        </div>
        <div class="ms-1">
            <button class="btn btn-success" id="btn_search">
                <i class="bi bi-search"></i>
            </button>
        </div>
    </div>
    <div class="card mt-2">
        <div class="card-body">
            <div class="d-flex align-items-center justify-content-between">
                <h5 class="fw-bold card-title">Payroll Settings</h5>
            </div>

            <div class="table-responsive">
                <table class="table text-center">
                    <thead>
                    <tr>
                        <th></th>
                        <th>Firstname</th>
                        <th>Middlename</th>
                        <th>Lastname</th>
                        <th>Suffix</th>
                        <th>Designation</th>
                        <th>Section</th>
                        <th style="width: 4%"></th>
                    </tr>
                    </thead>
                    <tbody id="employee_list"></tbody>
                </table>
                <div id="pagination_container">
                    <div class="d-flex flex-column align-items-center justify-content-center gap-2">
                        <div id="page_items_count"></div>
                        <nav aria-label="Payroll employee pagination">
                            <ul class="pagination pagination-sm mb-0" id="pagination_links"></ul>
                        </nav>
                        <div id="page_indicator" class="small text-muted"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @include('modals.payroll')
@stop
@section('js')
    <script>
        let page = 1;
        let payroll_last_page = 1;
        let employee_id = 0;
        $(document).ready(function () {
            getEmployee({fname: "", lname: "", page: page});

            $("#btn_search").click(function () {
                page = 1;
                employee_id = 0;
                const fname = $("#search_fname").val().trim();
                const lname = $("#search_lname").val().trim();
                getEmployee({fname: fname, lname: lname, page: page});
            });

            $("body").on("click", "#pagination_links .page-link[data-page]", function (e) {
                e.preventDefault();
                const targetPage = parseInt($(this).attr("data-page"), 10);
                if (!targetPage || targetPage === page) return;
                if (targetPage < 1 || targetPage > payroll_last_page) return;
                page = targetPage;
                const fname = $("#search_fname").val().trim();
                const lname = $("#search_lname").val().trim();
                getEmployee({fname: fname, lname: lname, page: page});
            });

            $("#btn_save").click(function () {
                let isValid = true;
                let params = {};

                params["userid"] = employee_id;

                // Select all input fields inside the modal (adjust selector if needed)
                $("#modal_salary_details input").each(function () {
                    let input = $(this);
                    let name = input.attr("id");
                    let value = parseFloat(input.val());

                    // Check for invalid value (empty or less than 0)
                    if (isNaN(value) || value < 0) {
                        input.addClass("is-invalid"); // Optional: add red border
                        isValid = false;
                    } else {
                        input.removeClass("is-invalid");
                        if (name === 'salary_grade' || name === 'step') {
                            params[name] = parseInt(value); // keep as integer
                        } else {
                            params[name] = parseFloat(value).toFixed(2); // 2 decimal format
                        }
                    }
                });

                if (!isValid) {
                    alert("All input fields must have a valid number (0.00 or higher).");
                    return;
                }

                fetch('{{ route('savePayrollSettings') }}', {
                        method: "POST",
                        headers: {
                            "Content-Type": "application/json",
                            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                        },
                        body: JSON.stringify(params)
                    }
                )
                    .then(res => res.json())
                    .then(data => {
                        toastr.success(data.message, 'Information');
                    }).catch(err => {
                    console.log(err);
                });
            });

        });

        function editEmployee(id) {
            employee_id = id;
            $("#modal_salary_details input").val("0.00"); // Reset all inputs
            $("#payroll_settings_modal").modal("show");

            // Fetch existing employee payroll data
            fetch('{{ route('getPayrollSettings') }}?userid=' + id)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Fill inputs with the retrieved data
                        for (const [key, value] of Object.entries(data.data)) {
                            const input = $(`#${key}`); // Select input using ID
                            if (input.length) {
                                input.val(key === 'salary_grade' || key === 'step' ? parseInt(value) : parseFloat(value).toFixed(2));
                            }
                        }
                    }
                })
                .catch(error => {
                    console.error("Error fetching data:", error);
                    alert("An error occurred while fetching employee data.");
                });
        }


        function getEmployee(params) {
            $("#employee_list").empty();
            $("#employee_list").append("<tr><td colspan='8'>Loading data, please wait...</td></tr>");
            $("#pagination_links").empty();
            $("#page_indicator").text("");
            fetch('{{ route('getEmployee') }}?fname=' + params.fname +
                "&lname=" + params.lname +
                "&page=" + params.page, {
                    method: "GET"
                }
            )
                .then(res => res.json())
                .then(data => {
                    $("#pagination_container").addClass("d-none");
                    $("#employee_list").empty();
                    $("#pagination_links").empty();
                    $("#page_indicator").text("");

                    const pagination = data.pagination || null;
                    if (pagination && pagination.current_page) {
                        page = pagination.current_page;
                        payroll_last_page = pagination.last_page;
                    }

                    if (data.results.length < 1) {
                        $("#employee_list").append("<tr><td colspan='8'>No record found</td></tr>");
                        return;
                    }

                    $("#pagination_container").removeClass("d-none");
                    let max_visible = data.results.length;
                    if (pagination) {
                        $("#page_items_count").text(pagination.from + " - " + pagination.to + " of " + pagination.total);
                        $("#page_indicator").text("Page " + pagination.current_page + " of " + pagination.last_page);
                        renderPayrollPaginationLinks(pagination);
                    } else {
                        $("#page_items_count").text(((params.page - 1) * 10 + 1) + " - " + ((params.page - 1) * 10 + max_visible));
                        $("#page_indicator").text("Page " + params.page);
                    }
                    for (let i = 0; i < max_visible; i++) {
                        const it = data.results[i];
                        const path = '{{ url('/avatar/')}}';
                        const suffix = (it.suffix) ? it.suffix : "";
                        const mname = (it.mname) ? it.mname : "";
                        const image = (it.picture) ? (path + "/" + it.picture) : "assets/img/logo.png";
                        const tr = `
                    <tr id='employee_` + it.user_id + `'>
                        <td>
                            <img src="` + image + `" style="width: 50px;height: 50px"/>
                        </td>
                        <td>` + it.fname + `</td>
                        <td>` + mname + `</td>
                        <td>` + it.lname + `</td>
                        <td>` + suffix + `</td>
                        <td>` + it.designation + `</td>
                        <td>` + it.section + `</td>
                        <td>
                            <button type='button' class="btn btn-sm" style="background: transparent" onClick="editEmployee(` + it.user_id + `)">
                                <i class="bi bi-pencil-fill text-success"></i>
                            </button>
                        </td>
                    <tr>`;
                        $("#employee_list").append(tr);
                    }
                }).catch(err => {
                console.log(err);
            })
        }

        function renderPayrollPaginationLinks(pagination) {
            const $links = $("#pagination_links");
            if (!$links.length || !pagination || !pagination.last_page) return;

            const current = pagination.current_page;
            const last = pagination.last_page;
            const start = Math.max(1, current - 2);
            const end = Math.min(last, current + 2);

            $links.empty();
            $links.append("<li class='page-item" + (current <= 1 ? " disabled" : "") + "'><a class='page-link' href='#' data-page='" + (current - 1) + "'>Prev</a></li>");

            if (start > 1) {
                $links.append("<li class='page-item'><a class='page-link' href='#' data-page='1'>1</a></li>");
                if (start > 2) {
                    $links.append("<li class='page-item disabled'><span class='page-link'>...</span></li>");
                }
            }

            for (let i = start; i <= end; i++) {
                $links.append("<li class='page-item" + (i === current ? " active" : "") + "'><a class='page-link' href='#' data-page='" + i + "'>" + i + "</a></li>");
            }

            if (end < last) {
                if (end < last - 1) {
                    $links.append("<li class='page-item disabled'><span class='page-link'>...</span></li>");
                }
                $links.append("<li class='page-item'><a class='page-link' href='#' data-page='" + last + "'>" + last + "</a></li>");
            }

            $links.append("<li class='page-item" + (current >= last ? " disabled" : "") + "'><a class='page-link' href='#' data-page='" + (current + 1) + "'>Next</a></li>");
        }
    </script>
@stop

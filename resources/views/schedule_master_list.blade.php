@extends('master')
@section('content')
    <style>
        .schedule-board {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 20px;
            padding: 1.5rem;
            box-shadow: 0 18px 38px rgba(15, 23, 42, 0.08);
        }

        .schedule-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            margin-bottom: 1.25rem;
            flex-wrap: wrap;
        }

        .schedule-head-title h4 {
            margin: 0;
            font-weight: 700;
            color: #0f172a;
        }

        .schedule-head-title span {
            font-size: 0.9rem;
            color: #64748b;
        }

        .schedule-table-shell {
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            overflow: hidden;
            background: #fff;
        }

        .schedule-table-shell table {
            margin-bottom: 0;
        }

        .schedule-table-shell table thead th {
            background: #f8fafc;
            color: #0f172a;
            font-size: 0.72rem;
            text-transform: uppercase;
            letter-spacing: 0.08em;
        }

        .schedule-table-shell table tbody td {
            color: #1e293b;
            font-weight: 500;
            vertical-align: middle;
        }

        .schedule-pagination {
            display: flex;
            justify-content: flex-end;
            margin-top: 1rem;
        }

        .schedule-pagination .page-link {
            color: #0f172a;
        }

        .schedule-pagination .page-item.active .page-link {
            background-color: #0f766e;
            border-color: #0f766e;
            color: #fff;
        }
    </style>

    <div class="schedule-board">
        <div class="schedule-head">
            <div class="schedule-head-title">
                <h4>Schedule Master List</h4>
                <span>Manage schedule templates used by Schedule Management.</span>
            </div>
            <button class="btn btn-success btn-edit" id="btn_add_schedule_master" type="button">
                <i class="bi bi-plus-lg"></i> Add Schedule
            </button>
        </div>

        <div class="table-responsive schedule-table-shell">
            <table class="table table-bordered align-middle text-center">
                <thead>
                <tr>
                    <th>Label</th>
                    <th>Code</th>
                    <th>Type</th>
                    <th>Time In</th>
                    <th>Time In Latest</th>
                    <th>Time Out</th>
                    <th>Time Out Latest</th>
                    <th>Break Out</th>
                    <th>Break In</th>
                    <th style="width: 8%">Action</th>
                </tr>
                </thead>
                <tbody id="schedule_master_tbody"></tbody>
            </table>
        </div>
        <nav class="schedule-pagination" aria-label="Schedule master list pagination">
            <ul class="pagination pagination-sm mb-0" id="schedule_master_pagination"></ul>
        </nav>
    </div>

    <div class="modal fade" id="scheduleMasterModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <div class="modal-heading">
                        <div class="modal-icon"><i class="bi bi-clock"></i></div>
                        <div>
                            <h5 class="modal-title" id="schedule_master_modal_title">Add Schedule Master</h5>
                            <p class="modal-sub">A shift's time-in, time-out and break window.</p>
                        </div>
                    </div>
                    <button type="button" class="btn-close ms-auto" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="schedule_master_id">
                    <div class="mb-3">
                        <label class="form-label">Label</label>
                        <input type="text" class="form-control" id="schedule_label">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Code</label>
                        <input type="text" class="form-control" id="schedule_code">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Type</label>
                        <select class="form-select" id="schedule_type">
                            <option value="FIXED">FIXED</option>
                            <option value="GLIDING">GLIDING</option>
                        </select>
                    </div>
                    <div class="row g-3">
                        <div class="col-6">
                            <label class="form-label">Time In</label>
                            <input type="time" class="form-control" id="schedule_time_in">
                        </div>
                        <div class="col-6">
                            <label class="form-label">Time In Latest</label>
                            <input type="time" class="form-control" id="schedule_time_in_latest">
                        </div>
                        <div class="col-6">
                            <label class="form-label">Time Out</label>
                            <input type="time" class="form-control" id="schedule_time_out">
                        </div>
                        <div class="col-6">
                            <label class="form-label">Time Out Latest</label>
                            <input type="time" class="form-control" id="schedule_time_out_latest">
                        </div>
                        <div class="col-6">
                            <label class="form-label">Break Out <span class="text-muted small">(optional)</span></label>
                            <input type="time" class="form-control" id="schedule_break_out">
                        </div>
                        <div class="col-6">
                            <label class="form-label">Break In <span class="text-muted small">(optional)</span></label>
                            <input type="time" class="form-control" id="schedule_break_in">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-modal-close" data-bs-dismiss="modal">Close</button>
                    <button class="btn btn-modal-save" id="btn_save_schedule_master" type="button">Save</button>
                </div>
            </div>
        </div>
    </div>
@stop

@section('js')
    <script>
        let scheduleMasterModal = null;
        let scheduleMasterRows = [];
        let scheduleMasterPage = 1;
        const scheduleMasterPageSize = 10;

        $(document).ready(function () {
            scheduleMasterModal = new bootstrap.Modal(document.getElementById("scheduleMasterModal"));
            loadScheduleMasterListPage();

            $("#btn_add_schedule_master").on("click", function () {
                openScheduleMasterModal();
            });

            $("body").on("click", ".btn-edit-schedule-master", function () {
                const id = $(this).data("id");
                const item = scheduleMasterRows.find(row => Number(row.id) === id);
                openScheduleMasterModal(item || null);
            });

            $("body").on("click", ".btn-delete-schedule-master", function () {
                const id = $(this).data("id");
                const item = scheduleMasterRows.find(row => Number(row.id) === id);
                confirmDeleteScheduleMaster(item);
            });

            $("#btn_save_schedule_master").on("click", function () {
                saveScheduleMasterListEntry();
            });

            $("body").on("click", "#schedule_master_pagination .page-link", function (event) {
                event.preventDefault();
                const targetPage = Number($(this).data("page") || 1);
                if (Number.isFinite(targetPage) && targetPage > 0) {
                    scheduleMasterPage = targetPage;
                    renderScheduleMasterRows();
                }
            });
        });

        function loadScheduleMasterListPage() {
            fetch("{{ route('getScheduleMasterList') }}")
                .then(res => res.json())
                .then(data => {
                    if (!Array.isArray(data)) {
                        throw new Error(data.message || "Failed to load schedule master list.");
                    }
                    scheduleMasterRows = data;
                    renderScheduleMasterRows();
                })
                .catch(err => {
                    toastr.error(err.message || "Failed to load schedule master list.", "Error");
                });
        }

        function renderScheduleMasterRows() {
            const tbody = $("#schedule_master_tbody");
            tbody.empty();

            if (!scheduleMasterRows.length) {
                tbody.append(`<tr><td colspan="8" class="text-center text-muted py-4">No schedule master list found.</td></tr>`);
                renderScheduleMasterPagination(0);
                return;
            }

            const sortedRows = [...scheduleMasterRows].sort((a, b) => {
                const aTime = String(a.time_in || "").slice(0, 5);
                const bTime = String(b.time_in || "").slice(0, 5);
                return aTime.localeCompare(bTime);
            });

            const startIndex = (scheduleMasterPage - 1) * scheduleMasterPageSize;
            const pageRows = sortedRows.slice(startIndex, startIndex + scheduleMasterPageSize);

            pageRows.forEach(item => {
                tbody.append(`
                    <tr>
                        <td>${item.label}</td>
                        <td>${item.code}</td>
                        <td>${item.type || ""}</td>
                        <td>${item.time_in || ""}</td>
                        <td>${item.time_in_latest || ""}</td>
                        <td>${item.time_out || ""}</td>
                        <td>${item.time_out_latest || ""}</td>
                        <td>${item.break_out || ""}</td>
                        <td>${item.break_in || ""}</td>
                        <td>
                            <button type="button" class="btn btn-action btn-edit btn-edit-schedule-master" data-id="${item.id}" title="Edit">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <button type="button" class="btn btn-action btn-delete btn-delete-schedule-master" data-id="${item.id}" title="Delete">
                                <i class="bi bi-trash"></i>
                            </button>
                        </td>
                    </tr>
                `);
            });

            renderScheduleMasterPagination(sortedRows.length);
        }

        function renderScheduleMasterPagination(totalItems) {
            const pagination = $("#schedule_master_pagination");
            pagination.empty();

            const totalPages = Math.max(1, Math.ceil(totalItems / scheduleMasterPageSize));
            if (scheduleMasterPage > totalPages) {
                scheduleMasterPage = totalPages;
            }

            const prevPage = Math.max(1, scheduleMasterPage - 1);
            const nextPage = Math.min(totalPages, scheduleMasterPage + 1);

            const prevDisabled = scheduleMasterPage === 1 ? " disabled" : "";
            const nextDisabled = scheduleMasterPage === totalPages ? " disabled" : "";

            pagination.append(`
                <li class="page-item${prevDisabled}">
                    <a class="page-link" href="#" data-page="${prevPage}">Previous</a>
                </li>
            `);

            for (let page = 1; page <= totalPages; page++) {
                const active = page === scheduleMasterPage ? " active" : "";
                pagination.append(`
                    <li class="page-item${active}">
                        <a class="page-link" href="#" data-page="${page}">${page}</a>
                    </li>
                `);
            }

            pagination.append(`
                <li class="page-item${nextDisabled}">
                    <a class="page-link" href="#" data-page="${nextPage}">Next</a>
                </li>
            `);
        }

        function openScheduleMasterModal(item = null) {
            const isEdit = !!item;
            const normalizeTime = (value) => {
                if (!value) return "";
                return String(value).slice(0, 5);
            };
            $("#schedule_master_modal_title").text(isEdit ? "Edit Schedule Master" : "Add Schedule Master");
            $("#schedule_master_id").val(isEdit ? item.id : "");
            $("#schedule_label").val(isEdit ? item.label : "");
            $("#schedule_code").val(isEdit ? item.code : "");
            $("#schedule_type").val(isEdit ? item.type : "FIXED");
            $("#schedule_time_in").val(isEdit ? normalizeTime(item.time_in) : "");
            $("#schedule_time_in_latest").val(isEdit ? normalizeTime(item.time_in_latest) : "");
            $("#schedule_time_out").val(isEdit ? normalizeTime(item.time_out) : "");
            $("#schedule_time_out_latest").val(isEdit ? normalizeTime(item.time_out_latest) : "");
            $("#schedule_break_out").val(isEdit ? normalizeTime(item.break_out) : "");
            $("#schedule_break_in").val(isEdit ? normalizeTime(item.break_in) : "");
            scheduleMasterModal.show();
        }

        function saveScheduleMasterListEntry() {
            const normalizeTime = (value) => {
                if (!value) return "";
                return String(value).slice(0, 5);
            };
            const payload = {
                id: Number($("#schedule_master_id").val() || 0),
                label: $("#schedule_label").val(),
                code: $("#schedule_code").val(),
                type: $("#schedule_type").val(),
                time_in: normalizeTime($("#schedule_time_in").val()),
                time_in_latest: normalizeTime($("#schedule_time_in_latest").val()),
                time_out: normalizeTime($("#schedule_time_out").val()),
                time_out_latest: normalizeTime($("#schedule_time_out_latest").val()),
                break_out: normalizeTime($("#schedule_break_out").val()) || null,
                break_in: normalizeTime($("#schedule_break_in").val()) || null
            };

            fetch("{{ route('saveScheduleMasterList') }}", {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    "Accept": "application/json",
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                body: JSON.stringify(payload)
            })
                .then(async res => {
                    const contentType = res.headers.get("content-type") || "";
                    const isJson = contentType.includes("application/json");
                    const data = isJson ? await res.json() : { message: await res.text() };
                    if (!res.ok) {
                        throw new Error(data.message || data.error || "Failed to save schedule master list.");
                    }
                    return data;
                })
                .then(data => {
                    toastr.success(data.message || "Saved.", "Success");
                    scheduleMasterModal.hide();
                    loadScheduleMasterListPage();
                })
                .catch(err => {
                    toastr.error(err.message || "Failed to save schedule master list.", "Error");
                });
        }

        function confirmDeleteScheduleMaster(item) {
            if (!item) return;
            Swal.fire({
                icon: 'warning',
                title: "Delete Schedule?",
                text: `Are you sure you want to delete ${item.label}?`,
                showCancelButton: true,
                confirmButtonText: 'Delete',
                confirmButtonColor: '#dc3545'
            }).then((result) => {
                if (!result.isConfirmed) return;

                fetch(`{{ url('/delete_schedule_master_list') }}/${item.id}`, {
                    method: "DELETE",
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    }
                })
                    .then(async res => {
                        const data = await res.json();
                        if (!res.ok) {
                            throw new Error(data.message || "Failed to delete schedule master list.");
                        }
                        return data;
                    })
                    .then(data => {
                        toastr.success(data.message || "Deleted.", "Success");
                        loadScheduleMasterListPage();
                    })
                    .catch(err => {
                        toastr.error(err.message || "Failed to delete schedule master list.", "Error");
                    });
            });
        }
    </script>
@stop

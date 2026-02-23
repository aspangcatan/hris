@extends('master')

@section('content')
    <style>
        .schedule-master-page {
            --sm-line: #e4eaf3;
            --sm-card: #ffffff;
            --sm-text: #1b2a41;
            --sm-muted: #5d6b82;
            --sm-brand: #0f766e;
            --sm-brand-2: #0ea5a4;
        }

        .schedule-master-page .hero {
            border-radius: 18px;
            padding: 1.2rem 1.35rem;
            background: linear-gradient(135deg, var(--sm-brand), var(--sm-brand-2));
            color: #fff;
            box-shadow: 0 14px 30px rgba(15, 118, 110, 0.24);
        }

        .schedule-master-page .hero h3 {
            margin: 0;
            font-weight: 700;
            letter-spacing: .25px;
        }

        .schedule-master-page .hero p {
            margin: .35rem 0 0;
            opacity: .9;
            font-size: .92rem;
        }

        .schedule-master-page .table-wrap {
            margin-top: 1rem;
            border: 1px solid var(--sm-line);
            border-radius: 14px;
            background: var(--sm-card);
            overflow: hidden;
        }

        .schedule-master-page table thead th {
            background: #f8fbff;
            color: var(--sm-muted);
            font-size: .74rem;
            text-transform: uppercase;
            letter-spacing: .7px;
            font-weight: 700;
            border-bottom-color: var(--sm-line);
            white-space: nowrap;
        }

        .schedule-master-page table tbody td {
            color: var(--sm-text);
            vertical-align: middle;
        }

        #scheduleMasterModal .modal-content {
            border: 0;
            border-radius: 18px;
            overflow: hidden;
            box-shadow: 0 24px 40px rgba(18, 27, 44, 0.2);
        }

        #scheduleMasterModal .modal-header {
            border-bottom: 1px solid var(--sm-line);
            background: linear-gradient(135deg, #f8fbff 0%, #ffffff 100%);
        }
    </style>

    <div class="schedule-master-page">
        @if(strtolower((string) session('access_rights')) !== 'admin')
            <div class="alert alert-danger mb-0">
                You are not authorized to access this page.
            </div>
        @else
            <div class="hero d-flex flex-wrap justify-content-between align-items-center gap-2">
                <div>
                    <h3>Schedule Master List</h3>
                    <p>Manage schedule templates used by Schedule Management.</p>
                </div>
                <button class="btn btn-light fw-semibold" id="btn_add_schedule_master" type="button">
                    <i class="bi bi-plus-lg me-1"></i> Add Schedule
                </button>
            </div>

            <div class="table-wrap">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                        <tr>
                            <th style="width: 90px;">Code</th>
                            <th>Label</th>
                            <th style="width: 110px;">Type</th>
                            <th style="width: 110px;">Time In</th>
                            <th style="width: 140px;">Time In Latest</th>
                            <th style="width: 110px;">Time Out</th>
                            <th style="width: 150px;">Time Out Latest</th>
                            <th style="width: 150px;">Action</th>
                        </tr>
                        </thead>
                        <tbody id="schedule_master_tbody"></tbody>
                    </table>
                </div>
            </div>
        @endif
    </div>

    @if(strtolower((string) session('access_rights')) === 'admin')
        <div class="modal fade" id="scheduleMasterModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="schedule_master_modal_title">Add Schedule Master</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" id="schedule_master_id">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Label</label>
                                <input class="form-control" id="sm_label" maxlength="50" type="text" placeholder="Regular Day Shift">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Code</label>
                                <input class="form-control" id="sm_code" maxlength="10" type="text" placeholder="RDS">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Type</label>
                                <select class="form-select" id="sm_type">
                                    <option value="FIXED">FIXED</option>
                                    <option value="GLIDING">GLIDING</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Time In</label>
                                <input class="form-control" id="sm_time_in" type="time">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Time In Latest</label>
                                <input class="form-control" id="sm_time_in_latest" type="time">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Time Out</label>
                                <input class="form-control" id="sm_time_out" type="time">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Time Out Latest</label>
                                <input class="form-control" id="sm_time_out_latest" type="time">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-light" data-bs-dismiss="modal" type="button">Cancel</button>
                        <button class="btn btn-primary" id="btn_save_schedule_master" type="button">Save</button>
                    </div>
                </div>
            </div>
        </div>
    @endif
@stop

@section('js')
    @if(strtolower((string) session('access_rights')) === 'admin')
        <script>
            let scheduleMasterModal = null;
            let scheduleMasterRows = [];

            $(document).ready(function () {
                scheduleMasterModal = new bootstrap.Modal(document.getElementById("scheduleMasterModal"));
                loadScheduleMasterListPage();

                $("#btn_add_schedule_master").on("click", function () {
                    openScheduleMasterModal();
                });

                $("body").on("click", ".btn-edit-schedule-master", function () {
                    const id = Number($(this).data("id"));
                    const item = scheduleMasterRows.find(row => Number(row.id) === id);
                    openScheduleMasterModal(item || null);
                });

                $("body").on("click", ".btn-delete-schedule-master", function () {
                    const id = Number($(this).data("id"));
                    const item = scheduleMasterRows.find(row => Number(row.id) === id);
                    if (!item) {
                        return;
                    }
                    confirmDeleteScheduleMaster(item);
                });

                $("#btn_save_schedule_master").on("click", function () {
                    saveScheduleMasterListEntry();
                });
            });

            function loadScheduleMasterListPage() {
                fetch("{{ route('getScheduleMasterList') }}")
                    .then(async res => {
                        const data = await res.json();
                        if (!res.ok) {
                            throw new Error(data.message || "Failed to load schedule master list.");
                        }
                        return data;
                    })
                    .then(data => {
                        scheduleMasterRows = Array.isArray(data) ? data : [];
                        renderScheduleMasterRows();
                    })
                    .catch(err => {
                        console.error(err);
                        toastr.error(err.message || "Failed to load schedule master list.", "Error");
                    });
            }

            function renderScheduleMasterRows() {
                const tbody = $("#schedule_master_tbody");
                tbody.empty();

                scheduleMasterRows.forEach(item => {
                    tbody.append(`
                        <tr>
                            <td class="fw-semibold">${escapeHtml(item.code || "")}</td>
                            <td>${escapeHtml(item.label || "")}</td>
                            <td>${escapeHtml((item.type || "FIXED").toUpperCase())}</td>
                            <td>${formatTime(item.time_in)}</td>
                            <td>${formatTime(item.time_in_latest)}</td>
                            <td>${formatTime(item.time_out)}</td>
                            <td>${formatTime(item.time_out_latest)}</td>
                            <td>
                                <button type="button" class="btn btn-sm btn-primary btn-edit-schedule-master" data-id="${item.id}">
                                    Edit
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-danger btn-delete-schedule-master" data-id="${item.id}">
                                    Delete
                                </button>
                            </td>
                        </tr>
                    `);
                });
            }

            function openScheduleMasterModal(item = null) {
                const isEdit = !!item;
                $("#schedule_master_modal_title").text(isEdit ? "Edit Schedule Master" : "Add Schedule Master");

                $("#schedule_master_id").val(isEdit ? item.id : "");
                $("#sm_label").val(isEdit ? (item.label || "") : "");
                $("#sm_code").val(isEdit ? (item.code || "") : "");
                $("#sm_type").val((isEdit ? (item.type || "FIXED") : "FIXED").toUpperCase());
                $("#sm_time_in").val(normalizeTime(item?.time_in));
                $("#sm_time_in_latest").val(normalizeTime(item?.time_in_latest));
                $("#sm_time_out").val(normalizeTime(item?.time_out));
                $("#sm_time_out_latest").val(normalizeTime(item?.time_out_latest));

                scheduleMasterModal.show();
            }

            function saveScheduleMasterListEntry() {
                const payload = {
                    id: Number($("#schedule_master_id").val() || 0),
                    label: String($("#sm_label").val() || "").trim(),
                    code: String($("#sm_code").val() || "").trim(),
                    type: String($("#sm_type").val() || "FIXED").toUpperCase(),
                    time_in: $("#sm_time_in").val(),
                    time_in_latest: $("#sm_time_in_latest").val(),
                    time_out: $("#sm_time_out").val(),
                    time_out_latest: $("#sm_time_out_latest").val()
                };

                fetch("{{ route('saveScheduleMasterList') }}", {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json",
                        "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr("content")
                    },
                    body: JSON.stringify(payload)
                })
                    .then(async res => {
                        const data = await res.json();
                        if (!res.ok) {
                            throw new Error(data.message || "Failed to save schedule master list.");
                        }
                        return data;
                    })
                    .then(data => {
                        toastr.success(data.message || "Saved successfully.", "Success");
                        scheduleMasterModal.hide();
                        loadScheduleMasterListPage();
                    })
                    .catch(err => {
                        console.error(err);
                        toastr.error(err.message || "Failed to save schedule master list.", "Error");
                    });
            }

            function confirmDeleteScheduleMaster(item) {
                Swal.fire({
                    icon: "warning",
                    title: "Delete Schedule?",
                    text: `Delete "${item.label}" (${item.code})?`,
                    showCancelButton: true,
                    confirmButtonText: "Delete",
                    confirmButtonColor: "#dc3545"
                }).then(result => {
                    if (!result.isConfirmed) {
                        return;
                    }

                    fetch(`{{ url('/delete_schedule_master_list') }}/${item.id}`, {
                        method: "DELETE",
                        headers: {
                            "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr("content")
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
                            toastr.success(data.message || "Deleted successfully.", "Deleted");
                            loadScheduleMasterListPage();
                        })
                        .catch(err => {
                            console.error(err);
                            toastr.error(err.message || "Failed to delete schedule master list.", "Error");
                        });
                });
            }

            function normalizeTime(timeValue) {
                if (!timeValue) {
                    return "";
                }
                return String(timeValue).substring(0, 5);
            }

            function formatTime(timeValue) {
                const val = normalizeTime(timeValue);
                if (!val || !val.includes(":")) {
                    return "";
                }

                const parts = val.split(":");
                let hour = Number(parts[0]);
                const minute = parts[1];
                const ampm = hour >= 12 ? "PM" : "AM";
                hour = hour % 12;
                if (hour === 0) {
                    hour = 12;
                }

                return `${hour}:${minute} ${ampm}`;
            }

            function escapeHtml(value) {
                return String(value)
                    .replace(/&/g, "&amp;")
                    .replace(/</g, "&lt;")
                    .replace(/>/g, "&gt;")
                    .replace(/"/g, "&quot;")
                    .replace(/'/g, "&#039;");
            }
        </script>
    @endif
@stop

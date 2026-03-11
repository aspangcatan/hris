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

        #scheduleMasterModal .modal-content {
            border-radius: 16px;
        }

        #scheduleMasterModal .modal-header {
            background: #0f766e;
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
                    <th>Time In</th>
                    <th>Break Out</th>
                    <th>Break In</th>
                    <th>Time Out</th>
                    <th style="width: 8%">Action</th>
                </tr>
                </thead>
                <tbody id="schedule_master_tbody"></tbody>
            </table>
        </div>
    </div>

    <div class="modal fade" id="scheduleMasterModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="schedule_master_modal_title">Add Schedule Master</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
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
                    <div class="row g-3">
                        <div class="col-6">
                            <label class="form-label">Time In</label>
                            <input type="time" class="form-control" id="schedule_time_in">
                        </div>
                        <div class="col-6">
                            <label class="form-label">Break Out</label>
                            <input type="time" class="form-control" id="schedule_break_out">
                        </div>
                        <div class="col-6">
                            <label class="form-label">Break In</label>
                            <input type="time" class="form-control" id="schedule_break_in">
                        </div>
                        <div class="col-6">
                            <label class="form-label">Time Out</label>
                            <input type="time" class="form-control" id="schedule_time_out">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-action btn-view" id="btn_save_schedule_master" type="button">Save</button>
                </div>
            </div>
        </div>
    </div>
@stop

@section('js')
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
                tbody.append(`<tr><td colspan="7" class="text-center text-muted py-4">No schedule master list found.</td></tr>`);
                return;
            }

            scheduleMasterRows.forEach(item => {
                tbody.append(`
                    <tr>
                        <td>${item.label}</td>
                        <td>${item.code}</td>
                        <td>${item.time_in}</td>
                        <td>${item.break_out}</td>
                        <td>${item.break_in}</td>
                        <td>${item.time_out}</td>
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
        }

        function openScheduleMasterModal(item = null) {
            const isEdit = !!item;
            $("#schedule_master_modal_title").text(isEdit ? "Edit Schedule Master" : "Add Schedule Master");
            $("#schedule_master_id").val(isEdit ? item.id : "");
            $("#schedule_label").val(isEdit ? item.label : "");
            $("#schedule_code").val(isEdit ? item.code : "");
            $("#schedule_time_in").val(isEdit ? item.time_in : "");
            $("#schedule_break_out").val(isEdit ? item.break_out : "");
            $("#schedule_break_in").val(isEdit ? item.break_in : "");
            $("#schedule_time_out").val(isEdit ? item.time_out : "");
            scheduleMasterModal.show();
        }

        function saveScheduleMasterListEntry() {
            const payload = {
                id: Number($("#schedule_master_id").val() || 0),
                label: $("#schedule_label").val(),
                code: $("#schedule_code").val(),
                time_in: $("#schedule_time_in").val(),
                break_out: $("#schedule_break_out").val(),
                break_in: $("#schedule_break_in").val(),
                time_out: $("#schedule_time_out").val()
            };

            fetch("{{ route('saveScheduleMasterList') }}", {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
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

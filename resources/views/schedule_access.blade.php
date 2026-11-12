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
            vertical-align: middle;
            color: #1b2a41;
        }

        .badge-soft {
            background: #eaf7f6;
            color: #0f766e;
            border: 1px solid #ccebe8;
            font-weight: 600;
            margin-right: .35rem;
            margin-bottom: .35rem;
        }

        #scheduleAccessModal .modal-toolbar {
            display: grid;
            grid-template-columns: minmax(220px, 320px) auto;
            gap: .75rem;
            align-items: start;
        }

        #scheduleAccessModal .modal-toolbar-actions {
            display: flex;
            gap: .5rem;
            flex-wrap: wrap;
            justify-content: flex-end;
        }

        #scheduleAccessModal .toolbar-input {
            border: 1px solid #e5ebf3;
            border-radius: 12px;
            padding: .4rem .65rem;
            background: #fff;
            width: 100%;
            min-width: 0;
        }

        #scheduleAccessModal .btn-toolbar-action {
            width: auto;
            min-width: 124px;
            height: 42px;
            padding: .55rem .9rem;
            border-radius: 12px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: .45rem;
            font-size: .92rem;
            line-height: 1;
            white-space: nowrap;
        }

        #scheduleAccessModal .btn-toolbar-action i {
            font-size: .95rem;
        }

        #scheduleAccessModal .modal-footer {
            display: flex;
            justify-content: flex-end;
            gap: .75rem;
            flex-wrap: wrap;
        }

        #scheduleAccessModal .modal-footer .btn {
            min-width: 132px;
            justify-content: center;
        }

        #scheduleAccessModal .section-box {
            max-height: 400px;
            overflow: auto;
            border: 1px solid #e5ebf3;
            border-radius: 12px;
            background: #fbfdff;
            padding: .85rem;
        }

        #scheduleAccessModal .division-block {
            border: 1px solid #e8eef6;
            border-radius: 10px;
            background: #fff;
            margin-bottom: .7rem;
            padding: .65rem .75rem;
        }

        #scheduleAccessModal .division-title {
            color: #5d6b82;
            font-weight: 700;
            font-size: .78rem;
            text-transform: uppercase;
            letter-spacing: .65px;
            margin-bottom: .55rem;
        }

        @media (max-width: 991.98px) {
            #scheduleAccessModal .modal-toolbar {
                grid-template-columns: 1fr;
            }

            #scheduleAccessModal .modal-toolbar-actions {
                justify-content: stretch;
            }

            #scheduleAccessModal .btn-toolbar-action {
                flex: 1 1 160px;
            }
        }
    </style>

    <div class="schedule-board">
        @if(strtolower((string) session('access_rights')) !== 'admin')
            <div class="alert alert-danger mb-0">
                You are not authorized to access this page.
            </div>
        @else
            <div class="schedule-head">
                <div class="schedule-head-title">
                    <h4>Schedule Access Control</h4>
                    <span>Assign one or multiple sections per schedule manager using a quick modal workflow.</span>
                </div>
            </div>

            <div class="schedule-table-shell">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                        <tr>
                            <th style="min-width: 230px;">User</th>
                            <th style="min-width: 170px;">Designation</th>
                            <th>Assigned Sections</th>
                            <th style="width: 130px;">Action</th>
                        </tr>
                        </thead>
                        <tbody id="schedule_manager_list">
                        <tr>
                            <td colspan="4" class="text-center text-muted py-4">Loading schedule users...</td>
                        </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </div>

    @if(strtolower((string) session('access_rights')) === 'admin')
        <div class="modal fade" id="scheduleAccessModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <div class="modal-heading">
                            <div class="modal-icon"><i class="bi bi-diagram-3"></i></div>
                            <div>
                                <h5 class="modal-title">Assign Sections</h5>
                                <p class="modal-sub" id="modal_manager_label"></p>
                            </div>
                        </div>
                        <button type="button" class="btn-close ms-auto" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="modal-toolbar mb-3">
                            <select class="form-select toolbar-input" id="division_filter">
                                <option value="">All divisions</option>
                            </select>
                            <div class="modal-toolbar-actions">
                                <button class="btn btn-action btn-view btn-toolbar-action" id="btn_select_visible" type="button">
                                    <i class="bi bi-check2-square"></i>
                                    <span>Select Visible</span>
                                </button>
                                <button class="btn btn-action btn-delete btn-toolbar-action" id="btn_clear_visible" type="button">
                                    <i class="bi bi-eraser"></i>
                                    <span>Clear Visible</span>
                                </button>
                            </div>
                        </div>
                        <div class="section-box" id="modal_section_list"></div>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-modal-close" data-bs-dismiss="modal" type="button">Cancel</button>
                        <button class="btn btn-modal-save" id="btn_save_modal_sections" type="button">Save Sections</button>
                    </div>
                </div>
            </div>
        </div>
    @endif
@stop

@section('js')
    @if(strtolower((string) session('access_rights')) === 'admin')
        <script>
            let allManagerSections = [];
            let managersById = {};
            let currentManagerId = null;
            let scheduleAccessModal = null;

            $(document).ready(function () {
                scheduleAccessModal = new bootstrap.Modal(document.getElementById('scheduleAccessModal'));
                loadScheduleManagerAssignments();

                $("body").on("click", ".btn-open-assign-modal", function () {
                    openAssignModal(Number($(this).data("user-id")));
                });

                $("#division_filter").on("change", function () {
                    renderSectionChecklist(currentManagerId, $(this).val());
                });

                $("#btn_select_visible").on("click", function () {
                    $("#modal_section_list .section-item:visible input[type='checkbox']").prop("checked", true);
                });

                $("#btn_clear_visible").on("click", function () {
                    $("#modal_section_list .section-item:visible input[type='checkbox']").prop("checked", false);
                });

                $("#btn_save_modal_sections").on("click", function () {
                    if (!currentManagerId) {
                        return;
                    }

                    const sectionIds = $("#modal_section_list input[type='checkbox']:checked").map(function () {
                        return Number($(this).val());
                    }).get();

                    fetch("{{ route('saveScheduleManagerSections') }}", {
                        method: "POST",
                        headers: {
                            "Content-Type": "application/json",
                            "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr("content")
                        },
                        body: JSON.stringify({
                            manager_id: currentManagerId,
                            section_ids: sectionIds
                        })
                    })
                        .then(async res => {
                            const data = await res.json();
                            if (!res.ok) {
                                throw new Error(data.message || "Failed to save section assignments");
                            }
                            return data;
                        })
                        .then(data => {
                            toastr.success(data.message, "Saved");
                            scheduleAccessModal.hide();
                            loadScheduleManagerAssignments();
                        })
                        .catch(err => {
                            console.error(err);
                            toastr.error(err.message || "Failed to save section assignments", "Error");
                        });
                });
            });

            function loadScheduleManagerAssignments() {
                fetch("{{ route('getScheduleManagerAssignments') }}", {method: "GET"})
                    .then(async res => {
                        const data = await res.json();
                        if (!res.ok) {
                            throw new Error(data.message || "Failed to load schedule users.");
                        }
                        return data;
                    })
                    .then(data => {
                        const managers = data.managers || [];
                        allManagerSections = data.sections || [];
                        managersById = {};

                        managers.forEach(manager => {
                            managersById[manager.id] = manager;
                        });

                        renderManagersTable(managers);
                    })
                    .catch(err => {
                        console.error(err);
                        $("#schedule_manager_list").html(`<tr><td colspan="4" class="text-center text-danger py-4">Failed to load schedule users.</td></tr>`);
                    });
            }

            function renderManagersTable(managers) {
                const tbody = $("#schedule_manager_list");
                tbody.empty();

                if (!managers.length) {
                    tbody.append(`<tr><td colspan="4" class="text-center text-muted py-4">No users with schedule access were found.</td></tr>`);
                    return;
                }

                managers.forEach(manager => {
                    const assignedIds = (manager.assigned_section_ids || []).map(id => Number(id));
                    const assignedSections = allManagerSections.filter(section => assignedIds.includes(Number(section.id)));

                    let badgesHtml = "";
                    if (!assignedSections.length) {
                        badgesHtml = `<span class="text-muted">No assigned sections</span>`;
                    } else {
                        const limited = assignedSections.slice(0, 4);
                        badgesHtml = limited.map(section =>
                            `<span class="badge badge-soft">${section.division_description} - ${section.description}</span>`
                        ).join("");

                        if (assignedSections.length > 4) {
                            badgesHtml += `<span class="badge bg-light text-dark border">+${assignedSections.length - 4} more</span>`;
                        }
                    }

                    tbody.append(`
                        <tr>
                            <td class="fw-semibold">${manager.name}</td>
                            <td>${manager.designation}</td>
                            <td>${badgesHtml}</td>
                            <td>
                                <button type="button" class="btn btn-action btn-view btn-open-assign-modal" data-user-id="${manager.id}" title="Assign">
                                    <i class="bi bi-sliders"></i>
                                </button>
                            </td>
                        </tr>
                    `);
                });
            }

            function openAssignModal(userId) {
                const manager = managersById[userId];
                if (!manager) {
                    return;
                }

                currentManagerId = userId;
                $("#modal_manager_label").text(`${manager.name} - ${manager.designation}`);
                populateDivisionFilter();
                $("#division_filter").val("");
                renderSectionChecklist(userId, "");
                scheduleAccessModal.show();
            }

            function populateDivisionFilter() {
                const select = $("#division_filter");
                const currentValue = String(select.val() || "");
                const divisions = [...new Set(
                    allManagerSections
                        .map(section => String(section.division_description || "").trim())
                        .filter(Boolean)
                )].sort((a, b) => a.localeCompare(b));

                select.html(`<option value="">All divisions</option>`);
                divisions.forEach(division => {
                    select.append($("<option>", {
                        value: division,
                        text: division
                    }));
                });

                select.val(divisions.includes(currentValue) ? currentValue : "");
            }

            function renderSectionChecklist(userId, divisionFilter) {
                const manager = managersById[userId];
                if (!manager) {
                    $("#modal_section_list").html("");
                    return;
                }

                const selectedDivision = String(divisionFilter || "").trim().toLowerCase();
                const assigned = new Set((manager.assigned_section_ids || []).map(id => Number(id)));
                const grouped = {};

                allManagerSections.forEach(section => {
                    const divisionName = String(section.division_description || "").trim();
                    if (selectedDivision && divisionName.toLowerCase() !== selectedDivision) {
                        return;
                    }

                    if (!grouped[divisionName]) {
                        grouped[divisionName] = [];
                    }
                    grouped[divisionName].push(section);
                });

                const divisions = Object.keys(grouped);
                if (!divisions.length) {
                    $("#modal_section_list").html(`<div class="text-muted">No sections found for the selected division.</div>`);
                    return;
                }

                let html = "";
                divisions.forEach(divisionName => {
                    html += `<div class="division-block">`;
                    html += `<div class="division-title">${divisionName}</div>`;
                    grouped[divisionName].forEach(section => {
                        const checked = assigned.has(Number(section.id)) ? "checked" : "";
                        html += `
                            <div class="form-check mb-2 section-item">
                                <input class="form-check-input" type="checkbox" value="${section.id}" id="section_check_${section.id}" ${checked}>
                                <label class="form-check-label" for="section_check_${section.id}">
                                    ${section.description}
                                </label>
                            </div>
                        `;
                    });
                    html += `</div>`;
                });

                $("#modal_section_list").html(html);
            }
        </script>
    @endif
@stop

@extends('master')

@section('content')
    <style>
        .schedule-access-page {
            --sa-bg: #f4f7fb;
            --sa-card: #ffffff;
            --sa-text: #1b2a41;
            --sa-muted: #5d6b82;
            --sa-line: #e5ebf3;
            --sa-brand: #0f766e;
            --sa-brand-2: #0ea5a4;
        }

        .schedule-access-page .hero {
            border-radius: 18px;
            padding: 1.2rem 1.35rem;
            background: linear-gradient(135deg, var(--sa-brand), var(--sa-brand-2));
            color: #fff;
            box-shadow: 0 14px 30px rgba(15, 118, 110, 0.24);
        }

        .schedule-access-page .hero h3 {
            margin: 0;
            font-weight: 700;
            letter-spacing: .25px;
        }

        .schedule-access-page .hero p {
            margin: .35rem 0 0;
            opacity: .9;
            font-size: .92rem;
        }

        .schedule-access-page .premium-table-wrap {
            margin-top: 1rem;
            border: 1px solid var(--sa-line);
            border-radius: 14px;
            background: var(--sa-card);
            overflow: hidden;
        }

        .schedule-access-page table thead th {
            background: #f8fbff;
            color: var(--sa-muted);
            font-size: .74rem;
            text-transform: uppercase;
            letter-spacing: .7px;
            font-weight: 700;
            border-bottom-color: var(--sa-line);
        }

        .schedule-access-page table tbody td {
            vertical-align: middle;
            color: var(--sa-text);
        }

        .schedule-access-page .badge-soft {
            background: #eaf7f6;
            color: #0f766e;
            border: 1px solid #ccebe8;
            font-weight: 600;
            margin-right: .35rem;
            margin-bottom: .35rem;
        }

        .schedule-access-page .manager-action {
            border: 0;
            background: linear-gradient(135deg, #0f766e 0%, #0ea5a4 100%);
            box-shadow: 0 9px 18px rgba(15, 118, 110, 0.25);
            font-weight: 600;
        }

        #scheduleAccessModal .modal-content {
            border: 0;
            border-radius: 18px;
            overflow: hidden;
            box-shadow: 0 24px 40px rgba(18, 27, 44, 0.2);
        }

        #scheduleAccessModal .modal-header {
            border-bottom: 1px solid var(--sa-line);
            background: linear-gradient(135deg, #f8fbff 0%, #ffffff 100%);
        }

        #scheduleAccessModal .modal-title {
            color: var(--sa-text);
            font-weight: 700;
        }

        #scheduleAccessModal .search-line {
            border: 1px solid var(--sa-line);
            border-radius: 12px;
            padding: .4rem .65rem;
            background: #fff;
        }

        #scheduleAccessModal .section-box {
            max-height: 400px;
            overflow: auto;
            border: 1px solid var(--sa-line);
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
            color: var(--sa-muted);
            font-weight: 700;
            font-size: .78rem;
            text-transform: uppercase;
            letter-spacing: .65px;
            margin-bottom: .55rem;
        }
    </style>

    <div class="schedule-access-page">
        @if(strtolower((string) session('access_rights')) !== 'admin')
            <div class="alert alert-danger mb-0">
                You are not authorized to access this page.
            </div>
        @else
            <div class="hero">
                <h3>Schedule Access Control</h3>
                <p>Assign one or multiple sections per schedule manager using a quick modal workflow.</p>
            </div>

            <div class="premium-table-wrap">
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
                        <div>
                            <h5 class="modal-title mb-1">Assign Sections</h5>
                            <div class="text-muted small" id="modal_manager_label"></div>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="d-flex flex-wrap gap-2 align-items-center mb-3">
                            <input type="text" class="form-control search-line" id="section_search" placeholder="Search section or division..." style="max-width: 320px;">
                            <input class="form-control" id="quick_section_search" list="quick_section_list" placeholder="Quick find section..." style="max-width: 360px;">
                            <datalist id="quick_section_list"></datalist>
                            <button class="btn btn-sm btn-outline-secondary" id="btn_select_visible" type="button">Select Visible</button>
                            <button class="btn btn-standard btn-outline-danger btn-sm" id="btn_clear_visible" type="button">Clear Visible</button>
                        </div>
                        <div class="section-box" id="modal_section_list"></div>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-light" data-bs-dismiss="modal" type="button">Cancel</button>
                        <button class="btn btn-primary manager-action" id="btn_save_modal_sections" type="button">Save Sections</button>
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

                $("#section_search").on("input", function () {
                    renderSectionChecklist(currentManagerId, $(this).val());
                });

                $("#quick_section_search").on("change", function () {
                    const selectedValue = String($(this).val() || "");
                    const sectionId = Number(selectedValue.split("||")[0] || 0);
                    if (!sectionId) {
                        return;
                    }

                    const checkbox = $(`#modal_section_list input[type='checkbox'][value='${sectionId}']`);
                    if (!checkbox.length) {
                        return;
                    }

                    checkbox.prop("checked", true);
                    const item = checkbox.closest(".section-item");
                    if (item.length) {
                        item[0].scrollIntoView({ behavior: "smooth", block: "center" });
                        item.addClass("bg-warning-subtle");
                        setTimeout(() => item.removeClass("bg-warning-subtle"), 900);
                    }
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
                                <button type="button" class="btn btn-action btn-view manager-action btn-open-assign-modal" data-user-id="${manager.id}" title="Assign">
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
                $("#modal_manager_label").text(`${manager.name} • ${manager.designation}`);
                $("#section_search").val("");
                renderQuickSectionOptions();
                renderSectionChecklist(userId, "");
                scheduleAccessModal.show();
            }

            function renderQuickSectionOptions() {
                const list = $("#quick_section_list");
                list.empty();
                $("#quick_section_search").val("");

                allManagerSections.forEach(section => {
                    const label = `${section.division_description} - ${section.description}`;
                    list.append(`<option value="${section.id}||${label}"></option>`);
                });
            }

            function renderSectionChecklist(userId, keyword) {
                const manager = managersById[userId];
                if (!manager) {
                    $("#modal_section_list").html("");
                    return;
                }

                const q = String(keyword || "").toLowerCase().trim();
                const assigned = new Set((manager.assigned_section_ids || []).map(id => Number(id)));
                const grouped = {};

                allManagerSections.forEach(section => {
                    const label = `${section.division_description} ${section.description}`.toLowerCase();
                    if (q && !label.includes(q)) {
                        return;
                    }

                    if (!grouped[section.division_description]) {
                        grouped[section.division_description] = [];
                    }
                    grouped[section.division_description].push(section);
                });

                const divisions = Object.keys(grouped);
                if (!divisions.length) {
                    $("#modal_section_list").html(`<div class="text-muted">No sections match your search.</div>`);
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

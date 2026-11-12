<script>
    const promotion_applied_modal = new bootstrap.Modal(document.getElementById('promotion_applied_modal'));
    const csrf_token = () => $('meta[name="csrf-token"]').attr('content');

    let position_applied_id = 0;
    let related_work_id = 0;
    let applications = {};   // id -> application row
    let related_works = {};  // id -> related work row
    let list_dirty = false;  // reload the page table when the modal closes

    function esc(value) {
        return $('<div>').text(value == null ? '' : String(value)).html();
    }

    function linesOf(selector) {
        return ($(selector).val() || '').split('\n').map(s => s.trim()).filter(s => s !== '');
    }

    function sendJson(url, method, params) {
        return fetch(url, {
            method: method,
            headers: {"Content-Type": "application/json", 'X-CSRF-TOKEN': csrf_token()},
            body: JSON.stringify(params)
        }).then(res => res.json());
    }

    /* ---------- modal state ---------- */

    function setUnlocked(unlocked) {
        $("#related_lock_hint").toggleClass("d-none", unlocked);
        $("#btn_add_related").prop("disabled", !unlocked);
        $("#related_work_list").toggleClass("pw-locked", !unlocked);
        $("#btn_print_work, #btn_delete_position").toggleClass("d-none", !unlocked);
        $("#btn_confirm_save_text").text(unlocked ? "Update details" : "Save & continue");
    }

    function openApplication(id) {
        position_applied_id = id || 0;
        const app = applications[position_applied_id];
        $(".is-invalid").removeClass("is-invalid");
        $("#position_applied").val(app ? app.position_applied : "");
        $("#date_applied").val(app ? moment(app.date_applied).format("YYYY-MM-DD") : moment().format("YYYY-MM-DD"));
        hideRelatedForm();
        related_works = {};
        renderRelatedList();
        setUnlocked(!!app);
        promotion_applied_modal.show();
        if (app) {
            getRelatedWork(position_applied_id);
        } else {
            setTimeout(() => $("#position_applied").trigger("focus"), 400);
        }
    }

    /* ---------- related work form ---------- */

    function showRelatedForm(item) {
        related_work_id = item ? item.id : 0;
        $("#related_form .is-invalid").removeClass("is-invalid");
        $("#related_form_title").text(item ? "Edit work experience" : "Add work experience");

        let from = "", to = "", present = false;
        if (item) {
            const parts = String(item.duration || "").split(" - ");
            from = parts[0] ? moment(parts[0].trim(), "MMMM D, YYYY").format("YYYY-MM-DD") : "";
            if ((parts[1] || "").trim() === "Present") {
                present = true;
            } else if (parts[1]) {
                to = moment(parts[1].trim(), "MMMM D, YYYY").format("YYYY-MM-DD");
            }
        }
        $("#work_from").val(from);
        $("#work_to").val(to).prop("disabled", present);
        $("#work_checkbox").prop("checked", present);
        $("#work_position").val(item ? item.position : "");
        $("#work_office").val(item ? item.office_unit : "");
        $("#work_supervisor").val(item ? item.supervisor : "");
        $("#work_agency").val(item ? item.agency : "");
        $("#work_accomplishment").val(item ? (item.accomplishment || []).map(a => a.description).join("\n") : "");
        $("#work_duties").val(item ? (item.duties || []).map(d => d.description).join("\n") : "");

        $(".pw-card").removeClass("is-editing");
        if (item) $("#related_work_id_" + item.id).addClass("is-editing");

        $("#related_form").removeClass("d-none");
        $("#btn_add_related").addClass("d-none");
        $("#related_form")[0].scrollIntoView({behavior: "smooth", block: "nearest"});
        setTimeout(() => $("#work_from").trigger("focus"), 150);
    }

    function hideRelatedForm() {
        related_work_id = 0;
        $("#related_form").addClass("d-none");
        $("#btn_add_related").removeClass("d-none");
        $(".pw-card").removeClass("is-editing");
    }

    function renderRelatedList() {
        const $list = $("#related_work_list").empty();
        const items = Object.values(related_works);
        $("#related_count_label").text(items.length ? "(" + items.length + ")" : "");

        if (!items.length) {
            $list.append(`<div class="pw-empty"><i class="bi bi-briefcase"></i>No work experience added yet.</div>`);
            return;
        }

        items.forEach(it => {
            const listHtml = (rows) => rows && rows.length
                ? `<ul>` + rows.map(r => `<li>` + esc(r.description) + `</li>`).join("") + `</ul>`
                : `<div class="pw-none">None</div>`;

            $list.append(`
                <div class="pw-card" id="related_work_id_` + it.id + `">
                    <div class="pw-card-top">
                        <div>
                            <div class="pw-card-title">` + esc(it.position) + `</div>
                            <div class="pw-card-sub">` + esc(it.office_unit) + `</div>
                            <span class="pw-badge"><i class="bi bi-calendar3 me-1"></i>` + esc(it.duration) + `</span>
                        </div>
                        <div class="d-flex gap-1 flex-shrink-0">
                            <button class="btn btn-action btn-edit-related" data-value="` + it.id + `" title="Edit"><span class="bi bi-pencil-square"></span></button>
                            <button class="btn btn-action btn-delete-related" data-value="` + it.id + `" title="Delete"><span class="bi bi-trash"></span></button>
                        </div>
                    </div>
                    <div class="pw-meta">
                        <div><span>Supervisor:</span> ` + esc(it.supervisor) + `</div>
                        <div><span>Agency / Location:</span> ` + esc(it.agency) + `</div>
                    </div>
                    <div class="pw-lists">
                        <div><div class="pw-list-label">Accomplishments</div>` + listHtml(it.accomplishment) + `</div>
                        <div><div class="pw-list-label">Actual duties</div>` + listHtml(it.duties) + `</div>
                    </div>
                </div>`);
        });
    }

    $(document).ready(function () {
        $("#btn_add_position").click(() => openApplication(0));

        $("body").on("click", ".promo-row", function (e) {
            if ($(e.target).closest("button").length) return;
            openApplication(parseInt($(this).attr("data-id")));
        });

        $("body").on("click", ".btn-open-app", function () {
            openApplication(parseInt($(this).attr("data-id")));
        });

        $("body").on("click", ".btn-print-app", function () {
            window.open('{{ route('printWorkExperience') }}?work_id=' + $(this).attr("data-id"), '_blank');
        });

        $("body").on("click", ".btn-delete-app", function () {
            deleteApplication(parseInt($(this).attr("data-id")));
        });

        $("#btn_delete_position").click(function () {
            deleteApplication(position_applied_id, true);
        });

        $("#btn_print_work").click(function () {
            if (!$("#related_form").hasClass("d-none")) {
                toastr.warning("Save or cancel the work experience you are editing first.", "Unsaved changes");
                return;
            }
            window.open('{{ route('printWorkExperience') }}?work_id=' + position_applied_id, '_blank');
        });

        $("#promotion_applied_modal").on("hidden.bs.modal", function () {
            if (list_dirty) {
                list_dirty = false;
                getPromotionWork();
            }
        });

        $("#position_applied, #date_applied").on("input change", function () {
            $(this).removeClass("is-invalid");
        });
        $("#position_applied").on("keydown", function (e) {
            if (e.key === "Enter") $("#btn_confirm_save").trigger("click");
        });

        $("#btn_confirm_save").click(function () {
            const position_applied = $("#position_applied").val().trim().toUpperCase();
            const date_applied = $("#date_applied").val();
            let is_valid = true;

            if (position_applied === "") {
                is_valid = false;
                $("#position_applied").addClass("is-invalid");
            }
            if (date_applied === "") {
                is_valid = false;
                $("#date_applied").addClass("is-invalid");
            }
            if (!is_valid) {
                toastr.error("Please fill in the position and date applied.", "Ooops");
                return;
            }

            const $btn = $(this).prop("disabled", true);
            sendJson('{{ route('insertPositionApplied') }}', "POST", {
                id: position_applied_id,
                position_applied: position_applied,
                date_applied: date_applied
            }).then(data => {
                if (data.code == 500) {
                    toastr.error(data.message, "Ooops");
                    return;
                }
                const was_new = !position_applied_id;
                position_applied_id = data.id;
                applications[data.id] = Object.assign(applications[data.id] || {}, {
                    id: data.id, position_applied: position_applied, date_applied: date_applied
                });
                $("#position_applied").val(position_applied);
                list_dirty = true;
                setUnlocked(true);
                toastr.success(data.message, "Information");
                if (was_new) showRelatedForm(null);
            }).catch(err => {
                console.log(err);
                toastr.error("Something went wrong, please try again.", "Ooops");
            }).finally(() => $btn.prop("disabled", false));
        });

        /* related work */

        $("#btn_add_related").click(() => showRelatedForm(null));
        $("#btn_cancel_related").click(hideRelatedForm);

        $("#work_checkbox").change(function () {
            const present = $(this).is(":checked");
            $("#work_to").prop("disabled", present).removeClass("is-invalid");
            if (present) $("#work_to").val("");
        });

        $("#related_form").on("input change", ".form-control", function () {
            $(this).removeClass("is-invalid");
        });

        $("body").on("click", ".btn-edit-related", function () {
            showRelatedForm(related_works[$(this).attr("data-value")]);
        });

        $("body").on("click", ".btn-delete-related", function () {
            const id = $(this).attr("data-value");
            const item = related_works[id];
            if (!confirm("Remove the work experience \"" + (item ? item.position : "") + "\"?")) return;

            sendJson('{{ route('deletePositionApplied') }}', "DELETE", {id: id})
                .then(data => {
                    if (data.code == 500) {
                        toastr.error(data.message, "Ooops");
                        return;
                    }
                    if (String(related_work_id) === String(id)) hideRelatedForm();
                    delete related_works[id];
                    renderRelatedList();
                    list_dirty = true;
                    toastr.success(data.message, "Information");
                }).catch(err => console.log(err));
        });

        $("#btn_save_related").click(function () {
            $("#related_form .is-invalid").removeClass("is-invalid");
            const from_date = $("#work_from").val();
            const present = $("#work_checkbox").is(":checked");
            let to_date = $("#work_to").val();
            const fields = {
                work_position: $("#work_position").val().trim().toUpperCase(),
                work_office: $("#work_office").val().trim().toUpperCase(),
                work_supervisor: $("#work_supervisor").val().trim().toUpperCase(),
                work_agency: $("#work_agency").val().trim().toUpperCase()
            };

            const missing = [];
            if (from_date === "") missing.push("#work_from");
            if (to_date === "" && !present) missing.push("#work_to");
            Object.keys(fields).forEach(k => { if (fields[k] === "") missing.push("#" + k); });
            missing.forEach(sel => $(sel).addClass("is-invalid"));
            if (missing.length) {
                toastr.error("Please fill in the highlighted fields.", "Ooops");
                $(missing[0]).trigger("focus");
                return;
            }
            if (!present && moment(to_date).isBefore(moment(from_date))) {
                $("#work_to").addClass("is-invalid");
                toastr.error("The \"To\" date cannot be earlier than the \"From\" date.", "Ooops");
                return;
            }

            to_date = present ? "Present" : moment(to_date).format("MMMM D, YYYY");
            const params = {
                id: related_work_id,
                work_id: position_applied_id,
                duration: moment(from_date).format("MMMM D, YYYY") + " - " + to_date,
                position: fields.work_position,
                office: fields.work_office,
                supervisor: fields.work_supervisor,
                agency: fields.work_agency,
                accomplishments: linesOf("#work_accomplishment"),
                duties: linesOf("#work_duties")
            };

            const $btn = $(this).prop("disabled", true);
            sendJson('{{ route('insertRelatedWork') }}', "POST", params)
                .then(data => {
                    if (data.code == 500) {
                        toastr.error(data.message, "Ooops");
                        return;
                    }
                    hideRelatedForm();
                    list_dirty = true;
                    toastr.success(data.message, "Information");
                    getRelatedWork(position_applied_id);
                }).catch(err => {
                    console.log(err);
                    toastr.error("Something went wrong, please try again.", "Ooops");
                }).finally(() => $btn.prop("disabled", false));
        });

        getPromotionWork();
    });

    function deleteApplication(id, fromModal) {
        const app = applications[id];
        if (!app) return;
        if (!confirm("Delete the application for \"" + app.position_applied + "\" and all of its work experience?")) return;

        sendJson('{{ route('deletePromotionPosition') }}', "DELETE", {id: id})
            .then(data => {
                if (data.code == 500) {
                    toastr.error(data.message, "Ooops");
                    return;
                }
                toastr.success(data.message, "Information");
                list_dirty = false;
                if (fromModal) promotion_applied_modal.hide();
                getPromotionWork();
            }).catch(err => console.log(err));
    }

    function getPromotionWork() {
        fetch('{{ route('getPromotionWork') }}', {method: "GET"})
            .then(res => res.json())
            .then(data => {
                const $list = $("#work_list").empty();
                if (data.code == 500) {
                    toastr.error("Something went wrong, please reload the page", "Ooops");
                    return;
                }

                applications = {};
                if (!data.results.length) {
                    $list.append(`<tr><td colspan="4" class="promo-empty"><i class="bi bi-briefcase"></i>No applications yet. Click <strong>New Application</strong> to get started.</td></tr>`);
                    return;
                }

                data.results.forEach(it => {
                    applications[it.id] = it;
                    const count = parseInt(it.related_count || 0);
                    $list.append(`
                        <tr class="promo-row" data-id="` + it.id + `" title="Click to open">
                            <td class="text-start">` + esc(it.position_applied) + `</td>
                            <td>` + moment(it.date_applied).format("MM/DD/YYYY") + `</td>
                            <td><span class="promo-count` + (count ? `` : ` is-empty`) + `">` + count + `</span></td>
                            <td class="text-nowrap">
                                <button class="btn btn-action btn-open-app" data-id="` + it.id + `" title="Open / Edit"><span class="bi bi-pencil-square"></span></button>
                                <button class="btn btn-action btn-print-app" data-id="` + it.id + `" title="Print"` + (count ? `` : ` disabled`) + `><span class="bi bi-printer"></span></button>
                                <button class="btn btn-action btn-delete-app" data-id="` + it.id + `" title="Delete"><span class="bi bi-trash"></span></button>
                            </td>
                        </tr>`);
                });
            }).catch(err => console.log(err));
    }

    function getRelatedWork(id) {
        $("#related_work_list").html(`<div class="pw-empty"><span class="spinner-border spinner-border-sm me-1"></span> Loading...</div>`);
        fetch('{{ route('getRelatedWork') }}?work_id=' + id, {method: "GET"})
            .then(res => res.json())
            .then(data => {
                if (id !== position_applied_id) return; // user switched applications meanwhile
                related_works = {};
                (data.results || []).forEach(it => { related_works[it.id] = it; });
                renderRelatedList();
            }).catch(err => console.log(err));
    }
</script>

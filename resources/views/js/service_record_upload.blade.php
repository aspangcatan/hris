<script>
    /* Service record Excel upload: set range → parse → review cards → Confirm All. Uses employee_id from js/employee. */
    const SRU_RANGE_DEFAULT = {start_col: "H", start_row: "13", end_col: "O", end_row: "13"};
    const SRU_RANGE_KEY = "hris.serviceRecordUploadRange";
    const SRU_FIELD_COUNT = 8; // From, To, Designation, Status, Salary, Office, Leave, Separation

    function sruColIndex(col) {
        return col.split("").reduce((n, ch) => n * 26 + (ch.charCodeAt(0) - 64), 0);
    }

    function sruLoadRange() {
        try {
            const saved = JSON.parse(localStorage.getItem(SRU_RANGE_KEY) || "null");
            if (saved && saved.start_col) return saved;
        } catch (e) {}
        return SRU_RANGE_DEFAULT;
    }

    function sruSaveRange(range) {
        try { localStorage.setItem(SRU_RANGE_KEY, JSON.stringify(range)); } catch (e) {}
    }

    function sruFillRange(range) {
        $("#sru_start_col").val(range.start_col);
        $("#sru_start_row").val(range.start_row);
        $("#sru_end_col").val(range.end_col);
        $("#sru_end_row").val(range.end_row);
        sruCheckRange();
    }

    function sruReadRange() {
        return {
            start_col: $("#sru_start_col").val().trim().toUpperCase(),
            start_row: $("#sru_start_row").val().trim(),
            end_col: $("#sru_end_col").val().trim().toUpperCase(),
            end_row: $("#sru_end_row").val().trim(),
        };
    }

    /** Mirrors ServiceRecordExcelParser::rangeError; returns a message or "". */
    function sruRangeError(r) {
        if (!/^[A-Z]{1,3}$/.test(r.start_col) || !/^[A-Z]{1,3}$/.test(r.end_col)) return "Columns must be letters only (e.g. H).";
        if (!/^[1-9]\d*$/.test(r.start_row) || !/^[1-9]\d*$/.test(r.end_row)) return "Rows must be whole numbers starting at 1.";
        if (r.start_row !== r.end_row) return "Starting and ending row must be the same — the range describes the first data row.";
        const span = sruColIndex(r.end_col) - sruColIndex(r.start_col) + 1;
        if (span !== SRU_FIELD_COUNT) {
            return `The range must cover exactly ${SRU_FIELD_COUNT} columns (From, To, Designation, Status, Salary, Office, Leave, Separation); ${r.start_col} to ${r.end_col} covers ${span}.`;
        }
        return "";
    }

    /** Shows the error or a reading preview; returns the range when valid, else null. */
    function sruCheckRange() {
        const r = sruReadRange();
        const error = sruRangeError(r);
        $("#sru_range_error").toggleClass("d-none", !error).text(error);
        $("#btn_sru_choose_file").prop("disabled", !!error);
        if (error) {
            $("#sru_range_hint").empty();
            return null;
        }
        const next = Number(r.start_row) + 1;
        $("#sru_range_hint").html(
            `Reads <code>${r.start_col}${r.start_row}:${r.end_col}${r.end_row}</code>, then <code>${r.start_col}${next}:${r.end_col}${next}</code>, and so on, ` +
            `stopping at the first row where column <code>${r.start_col}</code> is not a date.`
        );
        return r;
    }

    function openServiceRange() {
        sruFillRange(sruLoadRange());
        bootstrap.Modal.getOrCreateInstance(document.getElementById("service_range_modal")).show();
    }
    const SRU_REQUIRED = {
        date_from: "From date is required",
        designation: "Designation is required",
        appointment_status: "Status is required",
        monthly_salary: "Salary is required",
        office_assignment: "Office is required",
    };

    function sruField(label, field, value, opts = {}) {
        const $wrap = $('<div class="sru-field"></div>').addClass(opts.wide ? "sru-wide" : "");
        $wrap.append($('<label class="sru-label"></label>').text(label + (opts.required ? " *" : "")));
        const $input = opts.textarea
            ? $('<textarea class="form-control form-control-sm" rows="2"></textarea>')
            : $('<input class="form-control form-control-sm">').attr("type", opts.type || "text");
        $input.attr("data-field", field).val(value == null ? "" : value);
        $wrap.append($input, $('<div class="sru-field-error"></div>'));
        return $wrap;
    }

    function sruCard(row) {
        const $card = $('<section class="sru-card"></section>');
        const $top = $('<div class="sru-card-top"></div>');
        $top.append($('<span class="sru-row-badge"></span>').text("Row " + row.row));
        $top.append($('<span class="sru-card-title"></span>'));
        $top.append('<button type="button" class="btn btn-action btn-delete sru-remove" title="Remove this row"><i class="bi bi-x-lg"></i></button>');
        $card.append($top);

        const $grid = $('<div class="sru-grid"></div>');
        $grid.append(sruField("From", "date_from", row.date_from, {type: "date", required: true}));

        const $to = sruField("To", "date_to", row.date_to, {type: "date"});
        const switchId = "sru_present_" + row.row + "_" + Math.random().toString(36).slice(2, 7);
        $to.append(`<div class="form-check form-switch sru-present"><input class="form-check-input" type="checkbox" role="switch" data-field="current_work" id="${switchId}"><label class="form-check-label" for="${switchId}">Present</label></div>`);
        $to.find("[data-field=current_work]").prop("checked", row.current_work == 1);
        $grid.append($to);

        $grid.append(sruField("Designation", "designation", row.designation, {required: true}));
        $grid.append(sruField("Status", "appointment_status", row.appointment_status, {required: true}));
        $grid.append(sruField("Salary (annum)", "monthly_salary", row.monthly_salary, {type: "number", required: true}));
        $grid.append(sruField("Leave of absences w/o pay", "leave_absence", row.leave_absence));
        $grid.append(sruField("Office of Assignment", "office_assignment", row.office_assignment, {textarea: true, wide: true, required: true}));
        $grid.append(sruField("Separation", "separation", row.separation, {wide: true}));
        $card.append($grid);

        // Show what the file actually had when a date could not be read
        Object.entries(row.errors || {}).forEach(([field, msg]) => {
            $card.find(`[data-field=${field}]`).closest(".sru-field").attr("data-parse-error", msg);
        });

        sruSyncPresent($card);
        return $card;
    }

    function sruSyncPresent($card) {
        const present = $card.find("[data-field=current_work]").is(":checked");
        $card.find("[data-field=date_to]").prop("disabled", present);
        if (present) $card.find("[data-field=date_to]").val("");
    }

    function sruReadCard($card) {
        const row = {current_work: $card.find("[data-field=current_work]").is(":checked") ? 1 : 0};
        $card.find("input[data-field]:not([type=checkbox]), textarea[data-field]").each(function () {
            row[$(this).data("field")] = $(this).val().trim();
        });
        if (row.current_work) row.date_to = null;
        return row;
    }

    function sruRowErrors(row) {
        const errors = {};
        Object.entries(SRU_REQUIRED).forEach(([field, msg]) => {
            if (!row[field]) errors[field] = msg;
        });
        if (!row.current_work && !row.date_to) errors.date_to = "To date is required unless Present";
        return errors;
    }

    function sruShowErrors($card, errors) {
        $card.find(".sru-field").each(function () {
            const $f = $(this);
            const field = $f.find("[data-field]").first().data("field");
            const msg = errors[field] || "";
            const parseMsg = $f.attr("data-parse-error");
            $f.toggleClass("is-invalid", !!msg);
            $f.find(".sru-field-error").text(msg ? (parseMsg && field === "date_to" ? parseMsg + " — pick the correct date" : msg) : "");
        });
        $card.toggleClass("has-error", Object.keys(errors).length > 0);
    }

    function sruRefresh() {
        const $cards = $("#service_upload_list .sru-card");
        let invalid = 0;
        $cards.each(function (i) {
            const $card = $(this);
            const row = sruReadCard($card);
            const errors = sruRowErrors(row);
            if (Object.keys(errors).length) invalid++;
            sruShowErrors($card, errors);
            $card.find(".sru-card-title").text(row.designation || "Untitled");
        });
        const count = $cards.length;
        $("#service_upload_count").text(count + " record" + (count === 1 ? "" : "s"));
        $("#service_upload_confirm_count").text(count);
        $("#btn_confirm_service_upload").prop("disabled", count === 0 || invalid > 0);
        $("#service_upload_note").text(
            count === 0 ? "All rows removed — nothing to save."
                : invalid ? invalid + " card" + (invalid === 1 ? " needs" : "s need") + " attention before you can confirm."
                : "Records will be added to this employee's existing service records."
        ).toggleClass("text-danger", invalid > 0);
    }

    function openServiceUpload(data) {
        $("#service_upload_file").text(data.file_name);
        $("#service_upload_error").addClass("d-none").text("");
        $("#service_upload_name_warning").toggleClass("d-none", !data.name_mismatch);
        $("#service_upload_file_name").text(data.file_name_text || "(no name in file)");
        $("#service_upload_employee_name").text(data.employee_name_text);

        const $list = $("#service_upload_list").empty();
        data.rows.forEach(row => $list.append(sruCard(row)));
        sruRefresh();
        bootstrap.Modal.getOrCreateInstance(document.getElementById("service_upload_modal")).show();
    }

    function uploadServiceRecordFile(file) {
        const range = sruCheckRange();
        if (!range) return;
        const $btn = $("#btn_sru_choose_file");
        const originalHtml = $btn.html();
        $btn.prop("disabled", true).html('<span class="spinner-border spinner-border-sm me-1"></span>Reading...');

        const form = new FormData();
        form.append("file", file);
        form.append("user_id", employee_id);
        Object.entries(range).forEach(([k, v]) => form.append(k, v));

        fetch('{{ route('parseServiceRecordUpload') }}', {
            method: "POST",
            headers: {'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')},
            body: form
        })
            .then(res => res.json())
            .then(data => {
                if (data.code != 200) {
                    $("#sru_range_error").removeClass("d-none").text(data.message || "Could not read the file");
                    return;
                }
                sruSaveRange(range);
                bootstrap.Modal.getOrCreateInstance(document.getElementById("service_range_modal")).hide();
                openServiceUpload(data);
            })
            .catch(err => {
                console.log(err);
                toastr.error("Something went wrong while reading the file", "Oops");
            })
            .finally(() => $btn.prop("disabled", false).html(originalHtml));
    }

    function confirmServiceUpload() {
        const $cards = $("#service_upload_list .sru-card");
        const rows = $cards.map(function () { return sruReadCard($(this)); }).get();
        if (!rows.length) return;

        const $btn = $("#btn_confirm_service_upload");
        const originalHtml = $btn.html();
        $btn.prop("disabled", true).html('<span class="spinner-border spinner-border-sm me-2"></span>Saving...');

        fetch('{{ route('bulkStoreServiceRecords') }}', {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            body: JSON.stringify({user_id: employee_id, rows: rows})
        })
            .then(res => res.json())
            .then(data => {
                if (data.code != 200) {
                    $("#service_upload_error").removeClass("d-none").text(data.message || "Something went wrong");
                    Object.entries(data.errors || {}).forEach(([i, errors]) => sruShowErrors($cards.eq(i), errors));
                    return;
                }
                bootstrap.Modal.getOrCreateInstance(document.getElementById("service_upload_modal")).hide();
                toastr.success(data.message, "Success");
                is_service_loaded = false;
                $("#service_records_list").empty();
                getServiceRecords(employee_id);
            })
            .catch(err => {
                console.log(err);
                toastr.error("Something went wrong", "Oops");
            })
            .finally(() => {
                $btn.html(originalHtml);
                sruRefresh();
            });
    }

    $(function () {
        $("#btn_upload_service_records").on("click", openServiceRange);
        $("#btn_sru_choose_file").on("click", () => {
            if (sruCheckRange()) $("#service_upload_input").val("").trigger("click");
        });
        $("#btn_sru_range_reset").on("click", () => sruFillRange(SRU_RANGE_DEFAULT));
        $("#sru_start_col, #sru_end_col").on("input", function () {
            $(this).val($(this).val().toUpperCase().replace(/[^A-Z]/g, ""));
            sruCheckRange();
        });
        // Ending row follows the starting row; both describe the same first data row.
        $("#sru_start_row").on("input", function () {
            $("#sru_end_row").val($(this).val());
            sruCheckRange();
        });
        $("#sru_end_row").on("input", sruCheckRange);
        $("#service_upload_input").on("change", function () {
            if (this.files.length) uploadServiceRecordFile(this.files[0]);
        });
        $("#service_upload_list")
            .on("input change", "[data-field]", function () {
                const $card = $(this).closest(".sru-card");
                if ($(this).data("field") === "current_work") sruSyncPresent($card);
                $(this).closest(".sru-field").removeAttr("data-parse-error");
                sruRefresh();
            })
            .on("click", ".sru-remove", function () {
                $(this).closest(".sru-card").remove();
                sruRefresh();
            });
        $("#btn_confirm_service_upload").on("click", confirmServiceUpload);
    });
</script>

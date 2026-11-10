<script>
    // Rule sets on the page, by id (fills the modal when editing).
    let lwopRulesById = {};

    const LWOP_FIELDS = {
        booleans: ['count_rest_days_in_long_runs', 'holidays_bridge_runs', 'partial_day_breaks_run',
            'absences_count_without_pay', 'absences_join_runs', 'after_lwop_includes_holidays'],
        values: ['rest_day_rule', 'long_run_min_days', 'rest_day_source', 'partial_day_mode', 'month_basis_days', 'fractional_key_mode'],
    };

    const LWOP_DEFAULTS = {
        count_rest_days_in_long_runs: true, long_run_min_days: 7, rest_day_source: 'schedule',
        holidays_bridge_runs: true, partial_day_mode: 'fraction', partial_day_breaks_run: true,
        absences_count_without_pay: true, absences_join_runs: true, month_basis_days: 30,
        fractional_key_mode: 'interpolate', legal_basis: 'CSC MC No. 41, s. 1998, Rule XVI, Sec. 33',
        rest_day_rule: 'long_runs', after_lwop_includes_holidays: true,
    };

    // ---------------------------------------------------------------- Cases (day before / day after)
    const LWOP_CASE_STATUSES = [['lwop', 'LWOP'], ['present', 'Present'], ['absent', 'Absent (no leave filed)'], ['paid_leave', 'Paid leave']];
    // HR's examples: LWOP Fri + present Mon = 3; present Fri + LWOP Mon = 1; LWOP Fri + Mon = 4.
    const LWOP_HR_CASES = [
        {before: 'lwop', after: 'present', counts: true},
        {before: 'present', after: 'lwop', counts: false},
        {before: 'lwop', after: 'lwop', counts: true},
    ];
    // Fields that only apply to the CSC Sec. 33 run rule (hidden in Cases mode).
    const LWOP_SEC33_FIELDS = ['count_rest_days_in_long_runs', 'long_run_min_days', 'holidays_bridge_runs',
        'partial_day_breaks_run', 'absences_join_runs'];
    let lwopCases = [];

    const lwopStatusLabel = s => (LWOP_CASE_STATUSES.find(o => o[0] === s) || [s, s])[1];
    const lwopShort = s => ({lwop: 'LWOP', present: 'PRESENT', absent: 'ABSENT', paid_leave: 'LEAVE'}[s] || s);

    function lwopCaseFromMap(map) {
        return Object.entries(map || {}).map(([key, counts]) => {
            const [before, after] = key.split('|');
            return {before, after, counts: !!counts};
        });
    }

    function lwopCasePreview(c) {
        const rest = c.counts ? 'LWOP' : '—';
        return `<span class="small text-nowrap">FRI <b>${lwopShort(c.before)}</b> · SAT <b>${rest}</b> · SUN <b>${rest}</b> · MON <b>${lwopShort(c.after)}</b></span>`;
    }

    function renderLwopCases() {
        const options = sel => LWOP_CASE_STATUSES.map(o => `<option value="${o[0]}" ${o[0] === sel ? 'selected' : ''}>${o[1]}</option>`).join('');
        const $rows = $("#lwop_case_rows").empty();
        if (!lwopCases.length) {
            $rows.append('<tr><td colspan="6" class="text-muted small">No cases: rest days never count. Click "Add case".</td></tr>');
        }
        lwopCases.forEach((c, i) => {
            $rows.append(`
                <tr data-index="${i}">
                    <td>${i + 1}</td>
                    <td><select class="form-select form-select-sm lwop-case-before">${options(c.before)}</select></td>
                    <td><select class="form-select form-select-sm lwop-case-after">${options(c.after)}</select></td>
                    <td><select class="form-select form-select-sm lwop-case-counts">
                        <option value="1" ${c.counts ? 'selected' : ''}>Count as LWOP</option>
                        <option value="0" ${c.counts ? '' : 'selected'}>Do not count</option>
                    </select></td>
                    <td class="lwop-case-preview">${lwopCasePreview(c)}</td>
                    <td><button type="button" class="btn btn-sm btn-outline-danger lwop-case-remove" title="Remove case"><i class="bi bi-x"></i></button></td>
                </tr>`);
        });
    }

    function toggleLwopRuleMode() {
        const cases = $("#lwop_rest_day_rule").val() === 'cases';
        $(".lwop-cases-only").toggle(cases);
        LWOP_SEC33_FIELDS.forEach(f => $("#lwop_" + f).closest('[class^="col-"]').toggle(!cases));
    }

    $(document).ready(function () {
        $("#btn_add_lwop_rule").click(function () {
            openLwopRuleModal(null);
        });
        $("#lwop_rest_day_rule").on("change", toggleLwopRuleMode);
        $("#btn_add_lwop_case").on("click", function () {
            const used = new Set(lwopCases.map(c => c.before + '|' + c.after));
            const free = LWOP_CASE_STATUSES.flatMap(b => LWOP_CASE_STATUSES.map(a => [b[0], a[0]])).find(p => !used.has(p.join('|')));
            if (!free) {
                toastr.info('Every combination already has a case.');
                return;
            }
            lwopCases.push({before: free[0], after: free[1], counts: true});
            renderLwopCases();
        });
        $("#lwop_case_rows").on("change", "select", function () {
            const $row = $(this).closest("tr");
            const c = lwopCases[$row.data("index")];
            c.before = $row.find(".lwop-case-before").val();
            c.after = $row.find(".lwop-case-after").val();
            c.counts = $row.find(".lwop-case-counts").val() === "1";
            $row.find(".lwop-case-preview").html(lwopCasePreview(c));
        });
        $("#lwop_case_rows").on("click", ".lwop-case-remove", function () {
            lwopCases.splice($(this).closest("tr").data("index"), 1);
            renderLwopCases();
        });
        getLwopRules();
    });

    function escapeLwopHtml(text) {
        return String(text ?? '').replace(/[&<>"']/g, c => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[c]));
    }

    // One modal for both: null = add a rule set (pre-filled from the current one), an item = edit it.
    function openLwopRuleModal(item) {
        const current = Object.values(lwopRulesById).find(r => r.is_current) || LWOP_DEFAULTS;
        const source = item || current;

        $("#lwop_rule_id").val(item ? item.id : "");
        $("#lwop_rule_modal_title").text(item ? "Edit Rule Set" : "Add Rule Set");
        $("#lwop_effective_from").val(item ? item.effective_from : "").prop("disabled", !!(item && item.is_base));
        $("#lwop_legal_basis").val(source.legal_basis || "");
        $("#lwop_remarks").val(item ? (item.remarks || "") : "");
        LWOP_FIELDS.booleans.forEach(f => $("#lwop_" + f).val(source[f] ? "1" : "0"));
        LWOP_FIELDS.values.forEach(f => $("#lwop_" + f).val(String(source[f] ?? LWOP_DEFAULTS[f])));
        // A copy, so editing a new rule set never changes the one it was pre-filled from.
        const sourceCases = source.case_list && source.case_list.length ? source.case_list
            : (source.cases ? lwopCaseFromMap(source.cases) : LWOP_HR_CASES);
        lwopCases = sourceCases.map(c => ({before: c.before, after: c.after, counts: !!c.counts, remarks: c.remarks || null}));
        renderLwopCases();
        toggleLwopRuleMode();
        $("#lwop_rule_modal").modal("show");
    }

    function editLwopRule(id) {
        const item = lwopRulesById[id];
        if (item) openLwopRuleModal(item);
    }

    async function lwopRequest(url, method, body) {
        try {
            const response = await fetch(url, {
                method: method,
                headers: {
                    "Content-Type": "application/json",
                    "Accept": "application/json",
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                body: body ? JSON.stringify(body) : undefined
            });
            const data = await response.json();
            if (response.ok) return {ok: true, data: data, message: data.message || 'Saved'};
            const firstError = data.errors ? Object.values(data.errors)[0][0] : null;
            return {ok: false, message: firstError || data.message || 'Something went wrong, please try again'};
        } catch (error) {
            return {ok: false, message: 'Something went wrong, please try again'};
        }
    }

    async function saveLwopRule() {
        const params = {
            id: parseInt($("#lwop_rule_id").val(), 10) || null,
            effective_from: $("#lwop_effective_from").val(),
            legal_basis: $("#lwop_legal_basis").val() || null,
            remarks: $("#lwop_remarks").val() || null,
        };
        LWOP_FIELDS.booleans.forEach(f => params[f] = $("#lwop_" + f).val() === "1");
        LWOP_FIELDS.values.forEach(f => params[f] = $("#lwop_" + f).val());
        params.cases = lwopCases;

        const result = await lwopRequest('{{ route('lwop_settings.save') }}', 'POST', params);
        if (result.ok) {
            $("#lwop_rule_modal").modal("hide");
            toastr.success(result.message, "Information");
            getLwopRules();
        } else {
            toastr.error(result.message);
        }
    }

    async function deleteLwopRule(id) {
        const item = lwopRulesById[id];
        if (!item || !confirm(`Delete the rule set effective ${item.effective_from}?`)) return;

        const result = await lwopRequest('{{ route('lwop_settings.delete') }}', 'DELETE', {id: id});
        if (result.ok) {
            toastr.success(result.message, "Information");
            getLwopRules();
        } else {
            toastr.error(result.message);
        }
    }

    function describeLwopRules(r) {
        const yes = v => v ? 'Yes' : 'No';
        const lwop = [
            r.rest_day_rule === 'cases'
                ? `<b>Cases</b> (day before → day after): ${(r.case_list && r.case_list.length ? r.case_list : lwopCaseFromMap(r.cases))
                    .map(c => `${lwopStatusLabel(c.before)} → ${lwopStatusLabel(c.after)}: <b>${c.counts ? 'count' : 'no'}</b>`).join('; ') || 'none'}; holidays ${r.after_lwop_includes_holidays ? 'are rest days' : 'end the rest days'}`
                : (r.count_rest_days_in_long_runs
                    ? `Rest days/holidays count inside runs longer than <b>${r.long_run_min_days}</b> days`
                    : 'Rest days/holidays never count'),
            `Rest days: ${r.rest_day_source === 'schedule' ? 'plotted OFF (else Sat/Sun)' : 'Sat/Sun'}`,
            `Holidays keep a run continuous: ${yes(r.holidays_bridge_runs)}`,
            `Partial day: ${r.partial_day_mode === 'fraction' ? 'hours ÷ scheduled' : 'whole day'}; breaks a run: ${yes(r.partial_day_breaks_run)}`,
        ];
        const keyMode = {interpolate: 'interpolate', floor: 'round down', nearest: 'round to nearest'}[r.fractional_key_mode];
        const credits = [
            `Key = <b>${r.month_basis_days}</b> − days without pay`,
            `Unexcused absences count: ${yes(r.absences_count_without_pay)}; join runs: ${yes(r.absences_join_runs)}`,
            `Fractional key: ${keyMode}`,
        ];
        const list = items => `<ul class="lwop-rule-list small">${items.map(i => `<li>${i}</li>`).join('')}</ul>`;
        return {lwop: list(lwop), credits: list(credits)};
    }

    async function getLwopRules() {
        const result = await lwopRequest('{{ route('lwop_settings.list') }}', 'GET');
        if (!result.ok) {
            toastr.error(result.message, "Ooops");
            return;
        }

        lwopRulesById = {};
        const $tbody = $("#lwop_rule_list").empty();
        const rows = result.data.data || [];
        if (!rows.length) {
            $tbody.append('<tr><td colspan="8" class="text-muted">No rule sets yet.</td></tr>');
            return;
        }

        rows.forEach(r => {
            lwopRulesById[r.id] = r;
            const text = describeLwopRules(r);
            const badges = (r.is_current ? ' <span class="badge bg-success">In force today</span>' : '')
                + (r.is_base ? ' <span class="badge bg-secondary">Base</span>' : '');
            const who = r.updated_by_name || r.created_by_name || '';
            const actions = r.locked
                ? `<td colspan="2"><i class="bi bi-lock text-muted" title="Used by generated cutoffs - add a newer rule set instead"></i></td>`
                : `<td><button class="btn btn-sm btn-outline-primary" onclick="editLwopRule(${r.id})" title="Edit"><i class="bi bi-pencil"></i></button></td>
                   <td>${r.is_base ? '' : `<button class="btn btn-sm btn-outline-danger" onclick="deleteLwopRule(${r.id})" title="Delete"><i class="bi bi-trash"></i></button>`}</td>`;

            $tbody.append(`
                <tr>
                    <td class="text-nowrap"><b>${escapeLwopHtml(r.effective_from)}</b>${badges}</td>
                    <td>${text.lwop}</td>
                    <td>${text.credits}</td>
                    <td class="text-start small">${escapeLwopHtml(r.legal_basis || '')}${r.remarks ? `<div class="text-muted">${escapeLwopHtml(r.remarks)}</div>` : ''}</td>
                    <td>${r.cutoffs_using > 0 ? `${r.cutoffs_using} cutoff row(s)` : '<span class="text-muted">Not used yet</span>'}</td>
                    <td class="small">${escapeLwopHtml(who)}<div class="text-muted">${escapeLwopHtml(r.updated_at || r.created_at || '')}</div></td>
                    ${actions}
                </tr>`);
        });
    }
</script>

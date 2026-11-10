<div class="modal fade premium-modal" id="lwop_rule_modal" tabindex="-1" aria-labelledby="lwopRuleModalLabel" aria-hidden="true"
     data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-hero">
                    <i class="bi bi-sliders text-white" style="font-size:2.2rem"></i>
                </div>
            </div>
            <div class="modal-body">
                <div class="modal-title-text" id="lwop_rule_modal_title">Add Rule Set</div>
                <input type="hidden" id="lwop_rule_id" value="">

                <div class="mt-3">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <div class="form-floating">
                                <input type="date" class="form-control" id="lwop_effective_from" placeholder="">
                                <label>Effective From</label>
                            </div>
                        </div>
                        <div class="col-md-8">
                            <div class="form-floating">
                                <input type="text" class="form-control" id="lwop_legal_basis" maxlength="255" placeholder="">
                                <label>Legal Basis (e.g. CSC MC No. 41, s. 1998, Rule XVI, Sec. 33)</label>
                            </div>
                        </div>
                    </div>

                    <h6 class="mt-4 mb-2 fw-bold">LWOP identification</h6>
                    <div class="row g-3">
                        <div class="col-12">
                            <div class="form-floating">
                                <select class="form-control" id="lwop_rest_day_rule">
                                    <option value="long_runs">CSC Sec. 33: only inside a continuous run</option>
                                    <option value="cases">Cases: decided by the day before and the day after</option>
                                </select>
                                <label>How rest days and holidays become LWOP</label>
                            </div>
                        </div>

                        {{-- Cases editor (shown when "Cases" is chosen) --}}
                        <div class="col-12 lwop-cases-only">
                            <div class="form-text mb-2">
                                Each case looks at the work day <b>before</b> the rest days and the work day <b>after</b> them.
                                If a case says <b>Count</b>, the rest days in between are LWOP. A combination with no case does not count.
                                Cases affect LWOP days and earned credits only, never pay.
                            </div>
                            <div class="table-responsive">
                                <table class="table table-sm align-middle mb-2">
                                    <thead>
                                    <tr>
                                        <th style="width: 3rem">#</th>
                                        <th>Day before</th>
                                        <th>Day after</th>
                                        <th>Rest days</th>
                                        <th>Example week</th>
                                        <th style="width: 3rem"></th>
                                    </tr>
                                    </thead>
                                    <tbody id="lwop_case_rows"></tbody>
                                </table>
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-primary" id="btn_add_lwop_case">
                                <i class="bi bi-plus-circle me-1"></i> Add case
                            </button>
                            <div class="form-floating mt-3" style="max-width: 360px">
                                <select class="form-control" id="lwop_after_lwop_includes_holidays">
                                    <option value="1">Yes, a holiday is one more rest day</option>
                                    <option value="0">No, a holiday ends the rest days</option>
                                </select>
                                <label>Holidays next to rest days</label>
                            </div>
                            <div class="form-text">E.g. LWOP Thursday, holiday Friday, weekend, present Monday: Yes = 4 days; No = Thursday only.</div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-floating">
                                <select class="form-control" id="lwop_count_rest_days_in_long_runs">
                                    <option value="1">Yes</option>
                                    <option value="0">No</option>
                                </select>
                                <label>Count rest days/holidays inside a long LWOP run</label>
                            </div>
                            <div class="form-text">Sec. 33: an absence next to a weekend/holiday does not make it LWOP, except inside a continuous LWOP run longer than the number of days below.</div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-floating">
                                <input type="number" class="form-control" id="lwop_long_run_min_days" min="0" max="366" step="1" placeholder="">
                                <label>A run must be longer than (calendar days)</label>
                            </div>
                            <div class="form-text">CSC: 7 (a run of 8 or more calendar days).</div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-floating">
                                <select class="form-control" id="lwop_rest_day_source">
                                    <option value="schedule">Plotted OFF days (unplotted dates: Sat/Sun)</option>
                                    <option value="weekend">Always Saturday/Sunday</option>
                                </select>
                                <label>Rest days are</label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-floating">
                                <select class="form-control" id="lwop_holidays_bridge_runs">
                                    <option value="1">Yes</option>
                                    <option value="0">No</option>
                                </select>
                                <label>Holidays keep a run continuous</label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-floating">
                                <select class="form-control" id="lwop_partial_day_mode">
                                    <option value="fraction">Filed hours ÷ scheduled hours (4 of 8 = 0.5)</option>
                                    <option value="whole">One whole day</option>
                                </select>
                                <label>A partial-day LWOP counts as</label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-floating">
                                <select class="form-control" id="lwop_partial_day_breaks_run">
                                    <option value="1">Yes (the employee worked part of the day)</option>
                                    <option value="0">No</option>
                                </select>
                                <label>A partial-day LWOP breaks a run</label>
                            </div>
                        </div>
                    </div>

                    <h6 class="mt-4 mb-2 fw-bold">Earned credits</h6>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="form-floating">
                                <select class="form-control" id="lwop_absences_count_without_pay">
                                    <option value="1">Yes</option>
                                    <option value="0">No</option>
                                </select>
                                <label>Unexcused absences count as days without pay</label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-floating">
                                <select class="form-control" id="lwop_absences_join_runs">
                                    <option value="1">Yes</option>
                                    <option value="0">No</option>
                                </select>
                                <label>Unexcused absences join LWOP runs</label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-floating">
                                <input type="number" class="form-control" id="lwop_month_basis_days" min="1" max="31" step="1" placeholder="">
                                <label>Month basis (days)</label>
                            </div>
                            <div class="form-text">Credit key = month basis − days without pay. CSC: 30.</div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-floating">
                                <select class="form-control" id="lwop_fractional_key_mode">
                                    <option value="interpolate">Interpolate between table rows</option>
                                    <option value="floor">Round down</option>
                                    <option value="nearest">Round to nearest</option>
                                </select>
                                <label>Fractional key (e.g. 29.5)</label>
                            </div>
                        </div>
                    </div>

                    <div class="form-floating mt-4">
                        <textarea class="form-control" id="lwop_remarks" maxlength="2000" style="height: 80px" placeholder=""></textarea>
                        <label>Remarks</label>
                    </div>
                </div>
                <div class="d-flex justify-content-end gap-2 mt-4">
                    <button class="btn btn-secondary btn-modal-close" data-bs-dismiss="modal">Close</button>
                    <button class="btn btn-success btn-modal-save" onclick="saveLwopRule()">SAVE</button>
                </div>
            </div>
        </div>
    </div>
</div>

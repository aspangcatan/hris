<div class="modal fade premium-modal" id="hospital_order_modal" tabindex="-1" aria-labelledby="hospitalOrderModalLabel"
     aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-hero">
                    <i class="bi bi-hospital text-white" style="font-size:2.2rem"></i>
                </div>
            </div>
            <div class="modal-body">
                <div class="modal-title-text">Hospital Order Info</div>
                <form>
                    <div class="mb-3">
                        <label for="hospitalOrderNo" class="form-label">Hospital Order #</label>
                        <input type="text" class="form-control" id="hospitalOrderNo" readonly>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="modal fade premium-modal" id="print_dtr_modal" tabindex="-1" aria-labelledby="printDtrModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-hero">
                    <i class="bi bi-printer text-white" style="font-size:2.2rem"></i>
                </div>
            </div>
            <div class="modal-body">
                <div class="modal-title-text" id="printDtrModalLabel">Print DTR</div>

                {{-- Regular days / Saturdays: free text printed on the DTR form. --}}
                <section class="form-section mb-3">
                    <h6 class="section-title"><i class="bi bi-calendar-week"></i> Official Days</h6>
                    <div class="row g-3">
                        <div class="col-6">
                            <label for="reg_days_am" class="form-label">Regular days (AM)</label>
                            <input type="text" class="form-control" id="reg_days_am" name="reg_days_am">
                        </div>
                        <div class="col-6">
                            <label for="reg_days_pm" class="form-label">Regular days (PM)</label>
                            <input type="text" class="form-control" id="reg_days_pm" name="reg_days_pm">
                        </div>
                        <div class="col-6">
                            <label for="sat_days_am" class="form-label">Saturday (AM)</label>
                            <input type="text" class="form-control" id="sat_days_am" name="sat_days_am">
                        </div>
                        <div class="col-6">
                            <label for="sat_days_pm" class="form-label">Saturday (PM)</label>
                            <input type="text" class="form-control" id="sat_days_pm" name="sat_days_pm">
                        </div>
                    </div>
                    <div class="form-text mt-2"><i class="bi bi-info-circle"></i> Optional. Printed as-is on the DTR form.</div>
                </section>

                <section class="form-section mb-0">
                    <h6 class="section-title"><i class="bi bi-sliders"></i> Print Options</h6>
                    <div class="row g-3">
                        <div class="col-12">
                            <label for="cutoff" class="form-label">Cutoff period</label>
                            <select class="form-select" id="cutoff" name="cutoff" required>
                                <option value="full" selected>Whole Month</option>
                                <option value="first">1st Half (1–15)</option>
                                <option value="second">2nd Half (16–end)</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label for="show_day_names" class="form-label">Weekday names</label>
                            <select class="form-select" id="show_day_names" name="show_day_names">
                                <option value="yes" selected>Show</option>
                                <option value="no">Hide</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label for="show_in_charge_name" class="form-label">In-charge name</label>
                            <select class="form-select" id="show_in_charge_name" name="show_in_charge_name">
                                <option value="yes" selected>Show</option>
                                <option value="no">Hide</option>
                            </select>
                        </div>
                    </div>
                </section>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-modal-close" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-success btn-modal-save" id="btn_print">
                    <i class="bi bi-printer me-1"></i>Print
                </button>
            </div>
        </div>
    </div>
</div>

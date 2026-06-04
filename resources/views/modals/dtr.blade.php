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
                <div class="modal-title-text">Print DTR</div>
                <div class="mb-3">
                    <label for="cutoff" class="form-label">Reg. days (AM)</label>
                    <input type="text" class="form-control" id="reg_days_am" name="reg_days_am" required>
                </div>
                <div class="mb-3">
                    <label for="cutoff" class="form-label">Reg. days (PM)</label>
                    <input type="text" class="form-control" id="reg_days_pm" name="reg_days_pm" required>
                </div>
                 <div class="mb-3">
                    <label for="cutoff" class="form-label">Saturday (AM)</label>
                    <input type="text" class="form-control" id="sat_days_am" name="sat_days_am" >
                </div>
                <div class="mb-3">
                    <label for="cutoff" class="form-label">Saturday (PM)</label>
                    <input type="text" class="form-control" id="sat_days_pm" name="sat_days_pm">
                </div>
                <div class="mb-3">
                    <label for="cutoff" class="form-label">Cutoff Period</label>
                    <select class="form-select" id="cutoff" name="cutoff" required>
                        <option value="full" selected>Whole Month</option>
                        <option value="first">1st Half (1–15)</option>
                        <option value="second">2nd Half (16–end)</option>
                    </select>
                </div>
            </div>

            <div class="d-flex justify-content-end gap-2 mt-4">
                <button type="button" class="btn btn-secondary btn-modal-close" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-success btn-modal-save" id="btn_print">
                    <i class="bi bi-printer me-1"></i>Print
                </button>
            </div>

        </div>
    </div>
</div>

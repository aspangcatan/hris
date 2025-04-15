<div class="modal fade" id="hospital_order_modal" tabindex="-1" aria-labelledby="hospitalOrderModalLabel"
     aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="hospitalOrderModalLabel">Hospital Order Info</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
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

<div class="modal fade" id="print_dtr_modal" tabindex="-1" aria-labelledby="printDtrModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="printDtrModalLabel">Print DTR</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label for="cutoff" class="form-label">Cutoff Period</label>
                    <select class="form-select" id="cutoff" name="cutoff" required>
                        <option value="full" selected>Whole Month</option>
                        <option value="first">1st Half (1–15)</option>
                        <option value="second">2nd Half (16–end)</option>
                    </select>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-primary" id="btn_print">Print</button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>

        </div>
    </div>
</div>

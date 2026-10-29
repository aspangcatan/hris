<div>
    <div class="leave-credit-note mt-2">
        Review the employee's post test answers per module, then mark them Passed or Failed. A failed post test can be revised and resubmitted by the employee.
    </div>
    <div class="table-responsive mt-3 leave-credit-table-shell">
        <table class="table leave-credit-table align-middle" style="font-size: 13px">
            <thead>
            <tr>
                <th style="width: 34%">Module</th>
                <th style="width: 16%">Status</th>
                <th style="width: 18%">Submitted</th>
                <th style="width: 18%">Checked By</th>
                <th style="width: 14%">Action</th>
            </tr>
            </thead>
            <tbody id="reorientation_list">
                <tr>
                    <td colspan="5" class="text-center text-muted py-4">Loading re-orientation...</td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="mt-3 d-none" id="reo_check_panel">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
            <h6 class="fw-bold mb-0" id="reo_check_title"></h6>
            <button type="button" class="btn btn-sm btn-outline-secondary" id="btn_reo_check_close">
                <i class="bi bi-x-lg"></i> Close
            </button>
        </div>
        <div id="reo_check_answers"></div>
        <div class="mt-3">
            <label class="form-label fw-semibold" for="reo_check_remarks">Remarks</label>
            <textarea class="form-control" id="reo_check_remarks" rows="3" placeholder="Required when marking as Failed"></textarea>
        </div>
        <div class="d-flex justify-content-end gap-2 mt-2">
            <button type="button" class="btn btn-danger reo-check-btn" data-status="failed">
                <i class="bi bi-x-circle"></i> Mark as Failed
            </button>
            <button type="button" class="btn btn-success reo-check-btn" data-status="passed">
                <i class="bi bi-check-circle"></i> Mark as Passed
            </button>
        </div>
    </div>
</div>

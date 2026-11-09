{{--
    Service record Excel upload (js/service_record_upload): #service_range_modal sets the
    first data row's span, then #service_upload_modal reviews each parsed row as a .sru-card;
    Confirm All saves them via bulkStoreServiceRecords.
--}}
<input type="file" id="service_upload_input" accept=".xlsx,.xls" class="d-none">

{{-- Step 1: where the first data row sits in the workbook. Reading then moves down row by row. --}}
<div class="modal fade premium-modal lv-modal sru-modal" id="service_range_modal" tabindex="-1" aria-labelledby="service_range_title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header lv-header">
                <div class="modal-hero">
                    <i class="bi bi-grid-3x3-gap text-white" style="font-size:2.2rem"></i>
                </div>
                <div class="lv-header-text">
                    <h5 class="lv-title" id="service_range_title">Set Reading Range</h5>
                    <div class="lv-subtitle">Where is the first service record row in your file?</div>
                </div>
            </div>

            <div class="modal-body lv-body">
                <div class="sru-range">
                    <div class="sru-range-group">
                        <div class="sru-range-heading">Starting</div>
                        <div class="sru-field">
                            <label class="sru-label" for="sru_start_col">Column</label>
                            <input class="form-control form-control-sm sru-col" id="sru_start_col" maxlength="3" autocomplete="off">
                        </div>
                        <div class="sru-field">
                            <label class="sru-label" for="sru_start_row">Row</label>
                            <input class="form-control form-control-sm" id="sru_start_row" type="number" min="1" step="1">
                        </div>
                    </div>
                    <div class="sru-range-group">
                        <div class="sru-range-heading">Ending</div>
                        <div class="sru-field">
                            <label class="sru-label" for="sru_end_col">Column</label>
                            <input class="form-control form-control-sm sru-col" id="sru_end_col" maxlength="3" autocomplete="off">
                        </div>
                        <div class="sru-field">
                            <label class="sru-label" for="sru_end_row">Row</label>
                            <input class="form-control form-control-sm" id="sru_end_row" type="number" min="1" step="1">
                        </div>
                    </div>
                </div>
                <div class="sru-range-hint" id="sru_range_hint"></div>
                <div class="sru-error d-none" id="sru_range_error"></div>
            </div>

            <div class="modal-footer sru-footer">
                <button type="button" class="btn btn-link btn-sm px-0" id="btn_sru_range_reset">Reset to H13 &ndash; O13</button>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-modal-close" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-modal-save" id="btn_sru_choose_file">
                        <i class="bi bi-folder2-open me-1"></i>Choose File
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade premium-modal lv-modal sru-modal" id="service_upload_modal" tabindex="-1" aria-labelledby="service_upload_title" aria-hidden="true"
     data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-xl">
        <div class="modal-content">
            <div class="modal-header lv-header">
                <div class="modal-hero">
                    <i class="bi bi-file-earmark-spreadsheet text-white" style="font-size:2.2rem"></i>
                </div>
                <div class="lv-header-text">
                    <h5 class="lv-title" id="service_upload_title">Review Uploaded Service Records</h5>
                    <div class="lv-subtitle">
                        <i class="bi bi-paperclip"></i><span id="service_upload_file"></span>
                        <span class="sru-dot">&middot;</span><span id="service_upload_count"></span>
                    </div>
                </div>
            </div>

            <div class="modal-body lv-body">
                <div class="sru-warning d-none" id="service_upload_name_warning">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    <div>
                        <strong>Name does not match.</strong>
                        This file is for <span id="service_upload_file_name"></span>,
                        but the selected employee is <span id="service_upload_employee_name"></span>.
                        Double-check before confirming.
                    </div>
                </div>
                <div class="sru-error d-none" id="service_upload_error"></div>
                <div class="sru-list" id="service_upload_list"></div>
            </div>

            <div class="modal-footer sru-footer">
                <div class="sru-footer-note" id="service_upload_note"></div>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-modal-close" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-modal-save" id="btn_confirm_service_upload">
                        <i class="bi bi-check2-all me-1"></i>Confirm All (<span id="service_upload_confirm_count">0</span>)
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

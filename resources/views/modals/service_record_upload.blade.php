{{--
    Review dialog for an uploaded service record workbook (js/service_record_upload).
    Each parsed row is a .sru-card; Confirm All saves them via bulkStoreServiceRecords.
--}}
<input type="file" id="service_upload_input" accept=".xlsx,.xls" class="d-none">

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

<div class="modal fade premium-modal" id="lwop_case_modal" tabindex="-1" aria-labelledby="lwopCaseModalLabel" aria-hidden="true"
     data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-hero">
                    <i class="bi bi-calendar-week text-white" style="font-size:2.2rem"></i>
                </div>
            </div>
            <div class="modal-body">
                <div class="modal-title-text" id="lwop_case_modal_title">Add Case</div>
                <input type="hidden" id="lwop_case_id" value="">

                <div class="table-responsive mt-3">
                    <table class="lwop-week">
                        <thead>
                        <tr>
                            <th>FRI</th>
                            <th class="day-off">SAT</th>
                            <th class="day-off">SUN</th>
                            <th>MON</th>
                        </tr>
                        </thead>
                        <tbody>
                        <tr>
                            <td>
                                <select class="form-select" id="lwop_case_friday">
                                    <option value="lwop">LWOP</option>
                                    <option value="present">PRESENT</option>
                                    <option value="absent">ABSENT (no leave filed)</option>
                                    <option value="paid_leave">PAID LEAVE</option>
                                </select>
                            </td>
                            <td class="day-off">
                                <select class="form-select" id="lwop_case_saturday">
                                    <option value="1">LWOP</option>
                                    <option value="0">PRESENT</option>
                                </select>
                            </td>
                            <td class="day-off">
                                <select class="form-select" id="lwop_case_sunday">
                                    <option value="1">LWOP</option>
                                    <option value="0">PRESENT</option>
                                </select>
                            </td>
                            <td>
                                <select class="form-select" id="lwop_case_monday">
                                    <option value="lwop">LWOP</option>
                                    <option value="present">PRESENT</option>
                                    <option value="absent">ABSENT (no leave filed)</option>
                                    <option value="paid_leave">PAID LEAVE</option>
                                </select>
                            </td>
                        </tr>
                        </tbody>
                    </table>
                </div>
                <div class="mt-2 small text-muted" id="lwop_case_result"></div>

                <div class="form-floating mt-3">
                    <input type="text" class="form-control" id="lwop_case_remarks" maxlength="255" placeholder="">
                    <label>Remarks (e.g. HR memo or basis)</label>
                </div>

                <div class="d-flex justify-content-end gap-2 mt-4">
                    <button class="btn btn-secondary btn-modal-close" data-bs-dismiss="modal">Close</button>
                    <button class="btn btn-success btn-modal-save" onclick="saveLwopCase()">SAVE</button>
                </div>
            </div>
        </div>
    </div>
</div>

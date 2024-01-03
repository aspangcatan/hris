<div class="modal fade" id="print_modal" tabindex="-1" aria-labelledby="updateModalLabel" aria-hidden="true"
     data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-success text-center">
                <div class="p-3 w-100 text-center">
                    <i class="bi bi-printer text-white" style="font-size: 80px;"></i>
                </div>
            </div>
            <div class="modal-body">
                <div class="text-secondary font-weight-bold fs-4 text-center">PRINT DTS</div>
                <div class="card p-3">
                    <div class="card-body">
                        <p>Note:</p>
                        <p>Use the following steps when printing PDS</p>
                        <ol>
                            <li>Set Date then hit <strong class="text-success">PRINT BUTTON</strong></li>
                            <li>On the print preview, Go to <strong>More Settings</strong></li>
                            <li>Set <strong>Paper size</strong> to <strong>8.5 x 13 in</strong></li>
                            <li>Set <strong>Scale</strong> to <strong>Fit to printable area</strong></li>
                            <li>Then hit <strong>Print</strong></li>
                        </ol>
                    </div>
                </div>
                <div class="mt-3 text-secondary fs-5">
                    <div class="form-floating mb-3">
                        <input type="date" class="form-control" id="pds_date" placeholder="">
                        <label>Date</label>
                    </div>
                    <div class="form-floating mb-3">
                        <select class="form-control" id="experience_rows">
                            @for($i=15; $i<50; $i++)
                                @if($i == 23)
                                    <option selected>{{ $i }}</option>
                                @else
                                    <option>{{ $i }}</option>
                                @endif
                            @endfor
                        </select>
                        <label>Number of rows (Work Experience)</label>
                    </div>
                    <div class="form-floating mb-3">
                        <select class="form-control" id="learning_rows">
                            @for($i=15; $i<50; $i++)
                                @if($i == 24)
                                    <option selected>{{ $i }}</option>
                                @else
                                    <option>{{ $i }}</option>
                                @endif
                            @endfor
                        </select>
                        <label>Number of rows (Learning and Development)</label>
                    </div>
                </div>
                <div class="text-center mt-4">
                    <button class="btn btn-lg btn-secondary" data-bs-dismiss="modal" aria-label="Close">Close</button>
                    <button class="btn btn-lg btn-success" id="btn_confirm_print">PRINT</button>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="print_service_modal" tabindex="-1" aria-labelledby="updateModalLabel" aria-hidden="true"
     data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-success text-center">
                <div class="p-3 w-100 text-center">
                    <i class="bi bi-printer text-white" style="font-size: 80px;"></i>
                </div>
            </div>
            <div class="modal-body">
                <div class="text-secondary font-weight-bold fs-4 text-center">PRINT SERVICE RECORD</div>
                <div class="mt-3 text-secondary fs-5">
                    <div class="form-floating mb-3">
                        <input type="number" class="form-control" id="records_row" placeholder="">
                        <label>Number of rows</label>
                    </div>
                </div>
                <div class="text-center mt-4">
                    <button class="btn btn-lg btn-secondary" data-bs-dismiss="modal" aria-label="Close">Close</button>
                    <button class="btn btn-lg btn-success" id="btn_confirm_print_records">PRINT</button>
                </div>
            </div>
        </div>
    </div>
</div>

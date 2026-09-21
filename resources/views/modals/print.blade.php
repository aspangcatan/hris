<div class="modal fade premium-modal" id="print_modal" tabindex="-1" aria-labelledby="updateModalLabel" aria-hidden="true"
     data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-hero">
                    <i class="bi bi-printer text-white text-5xl"></i>
                </div>
            </div>
            <div class="modal-body">
                <div class="modal-title-text">Print PDS</div>
                <div class="mb-3 rounded-2xl border border-slate-200 bg-white p-3 text-sm text-slate-700">
                    <p class="mb-2 font-semibold">Note:</p>
                    <p class="mb-2">Use the following steps when printing PDS</p>
                    <ol class="list-decimal space-y-1 pl-5">
                        <li>Set date then hit <strong class="text-emerald-700">Print</strong></li>
                        <li>On print preview, go to <strong>More Settings</strong></li>
                        <li>Set <strong>Paper size</strong> to <strong>8.5 x 13 in</strong></li>
                        <li>Set <strong>Scale</strong> to <strong>Fit to printable area</strong></li>
                        <li>Then hit <strong>Print</strong></li>
                    </ol>
                </div>
                <div class="mb-3 rounded-2xl border border-slate-200 bg-white p-3 text-sm text-slate-700">
                    <p class="mb-2 font-semibold">Common concerns during printing:</p>
                    <ul class="list-disc space-y-1 pl-5">
                        <li>If work experience overflows, adjust Number of rows (Work Experience), e.g. 23 to 21.</li>
                        <li>If other information overflows, adjust Number of rows (Learning and Development), e.g. 24 to 22.</li>
                    </ul>
                </div>
                <div class="space-y-3">
                    <div>
                        <label class="mb-1 block text-sm font-semibold text-slate-700">Date</label>
                        <input type="date" class="form-control premium-input" id="pds_date" placeholder="">
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-semibold text-slate-700">Number of Rows (Work Experience)</label>
                        <select class="form-control premium-select" id="experience_rows">
                            @for($i=15; $i<50; $i++)
                                @if($i == 23)
                                    <option selected>{{ $i }}</option>
                                @else
                                    <option>{{ $i }}</option>
                                @endif
                            @endfor
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-semibold text-slate-700">Number of Rows (Learning and Development)</label>
                        <select class="form-control premium-select" id="learning_rows">
                            @for($i=15; $i<50; $i++)
                                @if($i == 24)
                                    <option selected>{{ $i }}</option>
                                @else
                                    <option>{{ $i }}</option>
                                @endif
                            @endfor
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-semibold text-slate-700">Subscribe and Sworn Date</label>
                        <input type="date" class="form-control premium-input" id="sworn_date" placeholder="">
                    </div>
                </div>
                <div class="mt-5 flex items-center justify-end gap-2">
                    <button class="btn btn-secondary btn-modal-close" data-bs-dismiss="modal" aria-label="Close">Close</button>
                    <button class="btn btn-success btn-modal-save" id="btn_confirm_print_2026">
                        <i class="bi bi-printer me-1"></i>Print 2026
                    </button>
                    <button class="btn btn-success btn-modal-save" id="btn_confirm_print_2025">
                        <i class="bi bi-printer me-1"></i>Print 2025
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade premium-modal" id="print_service_modal" tabindex="-1" aria-labelledby="updateModalLabel" aria-hidden="true"
     data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-hero">
                    <i class="bi bi-printer text-white text-5xl"></i>
                </div>
            </div>
            <div class="modal-body">
                <div class="modal-title-text">Print Service Record</div>
                <div class="mb-3">
                    <label class="mb-1 block text-sm font-semibold text-slate-700">Date Issued</label>
                    <input type="date" class="form-control premium-input" id="service_date_issued" placeholder="">
                </div>
                <div class="mb-3">
                    <label class="mb-1 block text-sm font-semibold text-slate-700">Paper Size</label>
                    <select class="form-control premium-select" id="service_paper_size">
                        <option value="a4" selected>A4 (8.27 x 11.69 in)</option>
                        <option value="long">Long (8.5 x 13 in)</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="mb-1 block text-sm font-semibold text-slate-700">Rows on Page 1</label>
                    <input type="number" min="1" class="form-control premium-input" id="records_first_rows" placeholder="">
                </div>
                <div class="mb-3">
                    <label class="mb-1 block text-sm font-semibold text-slate-700">Rows on Other Pages</label>
                    <input type="number" min="1" class="form-control premium-input" id="records_other_rows" placeholder="">
                </div>
                <div>
                    <div class="mb-1 flex items-center justify-between">
                        <label class="block text-sm font-semibold text-slate-700">Custom Pages <span class="font-normal text-slate-500">(optional)</span></label>
                        <button type="button" class="btn btn-secondary btn-sm" id="btn_add_page_override">
                            <i class="bi bi-plus-lg me-1"></i>Add page
                        </button>
                    </div>
                    <div id="page_override_list" class="space-y-2"></div>
                    <div class="mt-1 text-xs text-slate-500">Pick a page number (2 or higher) and the rows it should hold. Other pages use "Rows on Other Pages".</div>
                </div>
                <div class="mt-5 flex items-center justify-end gap-2">
                    <button class="btn btn-secondary btn-modal-close" data-bs-dismiss="modal" aria-label="Close">Close</button>
                    <button class="btn btn-success btn-modal-save" id="btn_confirm_print_records">
                        <i class="bi bi-printer me-1"></i>Print
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

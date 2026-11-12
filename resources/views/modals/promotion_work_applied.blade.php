<style>
#promotion_applied_modal .pw-section{border:1px solid #e2e8f0;border-radius:14px;padding:1rem 1.1rem;background:#fff}
#promotion_applied_modal .pw-section + .pw-section{margin-top:1rem}
#promotion_applied_modal .pw-section-head{display:flex;align-items:center;justify-content:space-between;gap:.75rem;margin-bottom:.85rem;flex-wrap:wrap}
#promotion_applied_modal .pw-step{display:flex;align-items:center;gap:.6rem;font-weight:700;color:#0f172a}
#promotion_applied_modal .pw-step-num{width:1.6rem;height:1.6rem;border-radius:50%;background:#1e3a8a;color:#fff;display:inline-flex;align-items:center;justify-content:center;font-size:.8rem}
#promotion_applied_modal .pw-step small{font-weight:500;color:#64748b}
#promotion_applied_modal .pw-locked{opacity:.55;pointer-events:none}
#promotion_applied_modal .pw-lock-hint{font-size:.85rem;color:#92400e;background:#fef3c7;border-radius:10px;padding:.55rem .8rem}
#promotion_applied_modal .pw-form{background:#f8fafc;border:1px dashed #94a3b8;border-radius:12px;padding:1rem;margin-bottom:1rem}
#promotion_applied_modal .pw-form-title{font-weight:700;color:#0f172a;margin-bottom:.75rem}
#promotion_applied_modal .pw-form textarea{min-height:110px;font-size:.9rem}
#promotion_applied_modal .pw-hint{font-size:.75rem;color:#64748b}
#promotion_applied_modal .pw-card{border:1px solid #e2e8f0;border-radius:12px;padding:.85rem 1rem;background:#fff}
#promotion_applied_modal .pw-card + .pw-card{margin-top:.75rem}
#promotion_applied_modal .pw-card.is-editing{border-color:#1e3a8a;box-shadow:0 0 0 2px rgba(30,58,138,.15)}
#promotion_applied_modal .pw-card-top{display:flex;justify-content:space-between;align-items:flex-start;gap:.75rem}
#promotion_applied_modal .pw-card-title{font-weight:700;color:#0f172a}
#promotion_applied_modal .pw-card-sub{font-size:.85rem;color:#475569}
#promotion_applied_modal .pw-badge{display:inline-block;font-size:.75rem;font-weight:600;color:#1e3a8a;background:#e0e7ff;border-radius:999px;padding:.15rem .6rem;margin-top:.3rem}
#promotion_applied_modal .pw-meta{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:.35rem 1rem;font-size:.85rem;margin-top:.6rem}
#promotion_applied_modal .pw-meta span{color:#64748b}
#promotion_applied_modal .pw-lists{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:.5rem 1rem;margin-top:.6rem;font-size:.85rem}
#promotion_applied_modal .pw-lists .pw-list-label{color:#64748b;font-weight:600;font-size:.75rem;text-transform:uppercase;letter-spacing:.05em}
#promotion_applied_modal .pw-lists ul{margin:.2rem 0 0;padding-left:1.1rem;color:#1e293b}
#promotion_applied_modal .pw-none{color:#94a3b8;font-style:italic}
#promotion_applied_modal .pw-empty{text-align:center;color:#64748b;padding:1.5rem .5rem;border:1px dashed #cbd5e1;border-radius:12px}
#promotion_applied_modal .pw-empty i{font-size:1.6rem;display:block;color:#94a3b8;margin-bottom:.3rem}
</style>

<div class="modal fade premium-modal" id="promotion_applied_modal" tabindex="-1" aria-labelledby="promotionModalLabel" aria-hidden="true"
     data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-hero">
                    <i class="bi bi-award text-white" style="font-size:2.2rem"></i>
                </div>
            </div>
            <div class="modal-body">
                <div class="modal-title-text" id="promotionModalLabel">Position Application</div>

                {{-- Step 1: application details --}}
                <div class="pw-section mt-3">
                    <div class="pw-section-head">
                        <div class="pw-step"><span class="pw-step-num">1</span> Application details</div>
                    </div>
                    <div class="row g-2 align-items-stretch">
                        <div class="col-md-6">
                            <div class="form-floating">
                                <input type="text" class="form-control" id="position_applied" placeholder="">
                                <label for="position_applied">Position applied <span class="text-danger">*</span></label>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-floating">
                                <input type="date" class="form-control" id="date_applied" placeholder="">
                                <label for="date_applied">Date applied <span class="text-danger">*</span></label>
                            </div>
                        </div>
                        <div class="col-md-3 d-grid">
                            <button class="btn btn-success btn-modal-save" id="btn_confirm_save">
                                <i class="bi bi-check2 me-1"></i><span id="btn_confirm_save_text">Save &amp; continue</span>
                            </button>
                        </div>
                    </div>
                </div>

                {{-- Step 2: related work experience --}}
                <div class="pw-section" id="work_experience_container">
                    <div class="pw-section-head">
                        <div class="pw-step">
                            <span class="pw-step-num">2</span> Related work experience
                            <small id="related_count_label"></small>
                        </div>
                        <button class="btn btn-sm btn-success" id="btn_add_related">
                            <i class="bi bi-plus-lg me-1"></i> Add experience
                        </button>
                    </div>

                    <div class="pw-lock-hint mb-2" id="related_lock_hint">
                        <i class="bi bi-info-circle me-1"></i> Save the application details first, then you can add your related work experience.
                    </div>

                    {{-- Inline add/edit form --}}
                    <div class="pw-form d-none" id="related_form">
                        <div class="pw-form-title" id="related_form_title">Add work experience</div>
                        <div class="row g-2">
                            <div class="col-md-4">
                                <div class="form-floating">
                                    <input type="date" class="form-control" id="work_from" placeholder="">
                                    <label for="work_from">From <span class="text-danger">*</span></label>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-floating">
                                    <input type="date" class="form-control" id="work_to" placeholder="">
                                    <label for="work_to">To <span class="text-danger">*</span></label>
                                </div>
                            </div>
                            <div class="col-md-4 d-flex align-items-center">
                                <div class="form-check form-switch ms-1">
                                    <input class="form-check-input" type="checkbox" id="work_checkbox">
                                    <label class="form-check-label" for="work_checkbox">Up to present</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-floating">
                                    <input type="text" class="form-control" id="work_position" placeholder="">
                                    <label for="work_position">Position <span class="text-danger">*</span></label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-floating">
                                    <input type="text" class="form-control" id="work_office" placeholder="">
                                    <label for="work_office">Name of Office/Unit <span class="text-danger">*</span></label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-floating">
                                    <input type="text" class="form-control" id="work_supervisor" placeholder="">
                                    <label for="work_supervisor">Immediate Supervisor <span class="text-danger">*</span></label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-floating">
                                    <input type="text" class="form-control" id="work_agency" placeholder="">
                                    <label for="work_agency">Agency/Organization and Location <span class="text-danger">*</span></label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold mb-1" for="work_accomplishment">Accomplishments and contributions <span class="text-muted fw-normal">(optional)</span></label>
                                <textarea class="form-control" id="work_accomplishment" placeholder="One item per line"></textarea>
                                <div class="pw-hint mt-1">Press Enter to start a new item.</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold mb-1" for="work_duties">Summary of actual duties</label>
                                <textarea class="form-control" id="work_duties" placeholder="One duty per line"></textarea>
                                <div class="pw-hint mt-1">Press Enter to start a new item.</div>
                            </div>
                        </div>
                        <div class="d-flex justify-content-end gap-2 mt-3">
                            <button class="btn btn-secondary btn-sm" id="btn_cancel_related">Cancel</button>
                            <button class="btn btn-success btn-sm" id="btn_save_related">
                                <i class="bi bi-check2 me-1"></i> Save experience
                            </button>
                        </div>
                    </div>

                    <div id="related_work_list"></div>
                </div>
            </div>
            <div class="modal-footer justify-content-between">
                <button class="btn btn-outline-danger btn-sm" id="btn_delete_position">
                    <i class="bi bi-trash me-1"></i> Delete application
                </button>
                <div class="d-flex gap-2">
                    <button class="btn btn-secondary btn-modal-close" data-bs-dismiss="modal">Close</button>
                    <button class="btn btn-danger" id="btn_print_work">
                        <i class="bi bi-printer me-1"></i> Print
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

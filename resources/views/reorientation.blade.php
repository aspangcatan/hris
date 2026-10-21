@extends('master')
@section('content')
<style>
    .reo-board { background:#fff; border:1px solid #e2e8f0; border-radius:20px; padding:1.5rem; box-shadow:0 18px 38px rgba(15,23,42,.08); }
    .reo-head  { display:flex; align-items:center; justify-content:space-between; gap:1rem; margin-bottom:1.25rem; flex-wrap:wrap; }
    .reo-head h4 { margin:0; font-weight:700; color:#0f172a; }
    .reo-head span { font-size:.9rem; color:#64748b; }
    .reo-tabs .nav-link { border-radius:12px; color:#334155; font-weight:600; display:flex; align-items:center; gap:.5rem; }
    .reo-tabs .nav-link.active { background:#0f172a; color:#fff; }
    .reo-section { background:#f8fafc; border:1px solid #e2e8f0; border-radius:16px; padding:1rem; margin-top:1.25rem; }
    .reo-section-title { display:flex; align-items:center; justify-content:space-between; gap:.75rem; flex-wrap:wrap; margin-bottom:.75rem; }
    .reo-section-title h5 { margin:0; font-weight:700; color:#0f172a; font-size:1.05rem; }
    .reo-pdf { width:100%; height:75vh; min-height:420px; border:1px solid #e2e8f0; border-radius:12px; background:#fff; }
    .reo-question { background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:1rem; margin-bottom:.85rem; }
    .reo-question label { font-weight:600; color:#0f172a; white-space:pre-wrap; }
    .reo-question textarea { border-radius:10px; border-color:#d8e1ee; min-height:140px; }
    .reo-answer-readonly { white-space:pre-wrap; color:#1e293b; background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; padding:.75rem; }
    .reo-remarks { border-radius:12px; padding:.85rem 1rem; font-size:.9rem; white-space:pre-wrap; }
    .reo-manage-item { display:flex; gap:.75rem; align-items:flex-start; border:1px solid #e2e8f0; border-radius:12px; padding:.75rem; margin-bottom:.5rem; background:#fff; }
    .reo-manage-item .reo-manage-text { flex:1; white-space:pre-wrap; }
</style>

<div class="reo-board">
    <div class="reo-head">
        <div>
            <h4>Re-Orientation</h4>
            <span>Read each module, then answer its post test. HR checks your answers.</span>
        </div>
        {{-- Hidden for now: questions are loaded by ReorientationQuestionSeeder --}}
        @if(false)
        @permission('reorientation.manage')
        <button class="btn btn-sm btn-primary" id="btn_manage_questions">
            <i class="bi bi-pencil-square"></i> Manage Questions
        </button>
        @endpermission
        @endif
    </div>

    <ul class="nav nav-pills reo-tabs gap-2 flex-wrap" id="reo_module_tabs">
        @foreach($modules as $no => $module)
            <li class="nav-item">
                <button class="nav-link {{ $loop->first ? 'active' : '' }}" type="button" data-module="{{ $no }}">
                    Module {{ $no }}
                    <span class="badge bg-secondary reo-tab-badge" data-module="{{ $no }}">&nbsp;</span>
                </button>
            </li>
        @endforeach
    </ul>

    <div class="reo-section">
        <div class="reo-section-title">
            <h5 id="reo_module_title"></h5>
            <a href="#" target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary" id="reo_pdf_link">
                <i class="bi bi-box-arrow-up-right"></i> Open PDF in new tab
            </a>
        </div>
        <iframe class="reo-pdf" id="reo_pdf" title="Module reading material"></iframe>
    </div>

    <div class="reo-section">
        <div class="reo-section-title">
            <h5>Post Test</h5>
            <span id="reo_status_badge"></span>
        </div>
        <div id="reo_remarks"></div>
        <div id="reo_post_test">
            <div class="text-muted text-center py-4">Loading post test...</div>
        </div>
        <div class="text-end d-none" id="reo_submit_wrap">
            <button class="btn btn-success" id="btn_submit_post_test">
                <i class="bi bi-send"></i> Submit Post Test
            </button>
        </div>
    </div>
</div>

@permission('reorientation.manage')
<div class="modal fade" id="manageQuestionsModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Manage Post Test Questions</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label fw-semibold" for="mq_module">Module</label>
                    <select class="form-select" id="mq_module">
                        @foreach($modules as $no => $module)
                            <option value="{{ $no }}">Module {{ $no }} — {{ $module['title'] }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="small text-muted mb-2">
                    Employees who already submitted keep the questions they answered. Changes apply to new and resubmitted post tests.
                </div>
                <div id="mq_list"></div>
                <div class="border-top pt-3 mt-3">
                    <label class="form-label fw-semibold" for="mq_question" id="mq_form_label">Add a question</label>
                    <textarea class="form-control" id="mq_question" rows="3" placeholder="Type the question..."></textarea>
                    <div class="d-flex justify-content-end gap-2 mt-2">
                        <button class="btn btn-sm btn-outline-secondary d-none" id="btn_mq_cancel_edit">Cancel edit</button>
                        <button class="btn btn-sm btn-primary" id="btn_mq_save"><i class="bi bi-check-lg"></i> Save question</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endpermission
@stop
@section('js')
    @include('js.reorientation')
@stop

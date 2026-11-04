@extends('master')
@section('content')
<style>
    .reo-board { background:#fff; border:1px solid #e2e8f0; border-radius:20px; padding:1.5rem; box-shadow:0 18px 38px rgba(15,23,42,.08); }
    .reo-head  { display:flex; align-items:center; justify-content:space-between; gap:1rem; margin-bottom:1.25rem; flex-wrap:wrap; }
    .reo-head h4 { margin:0; font-weight:700; color:#0f172a; }
    .reo-head span { font-size:.9rem; color:#64748b; }
    .reo-head-eyebrow { display:inline-flex; align-items:center; gap:.35rem; font-size:.7rem; font-weight:700; letter-spacing:.14em; text-transform:uppercase; color:#0f766e; background:#f0fdfa; border:1px solid #ccfbf1; padding:.25rem .65rem; border-radius:999px; margin-bottom:.45rem; }
    .reo-head h4 { font-size:1.5rem; }
    .reo-progress { min-width:240px; flex:0 1 300px; }
    .reo-progress-label { display:flex; justify-content:space-between; align-items:baseline; font-size:.85rem; color:#64748b; margin-bottom:.4rem; }
    .reo-progress-label strong { color:#0f766e; font-size:1rem; }
    .reo-progress-track { height:10px; border-radius:999px; background:#e2e8f0; overflow:hidden; }
    .reo-progress-fill { height:100%; width:0; border-radius:999px; background:linear-gradient(90deg,#0f766e,#14b8a6); transition:width .6s ease; }

    /* Module stepper */
    .reo-steps { display:grid; grid-template-columns:repeat(var(--reo-steps,4),minmax(0,1fr)); gap:.85rem; }
    .reo-step { position:relative; display:flex; align-items:flex-start; gap:.75rem; text-align:left; padding:.9rem 1rem; border-radius:16px;
                background:#fff; border:1.5px solid #e2e8f0; cursor:pointer; transition:border-color .15s, box-shadow .15s, transform .15s; }
    .reo-step:hover { border-color:#99f6e4; transform:translateY(-1px); box-shadow:0 8px 20px rgba(15,23,42,.06); }
    /* connector to the next step */
    .reo-step:not(:last-child)::after { content:''; position:absolute; top:50%; right:calc(-.85rem - 1px); width:.85rem; height:2px; background:#e2e8f0; }
    .reo-step.is-done:not(:last-child)::after { background:#14b8a6; }
    .reo-step-dot { flex:0 0 38px; width:38px; height:38px; border-radius:50%; display:flex; align-items:center; justify-content:center;
                    font-weight:800; font-size:.95rem; color:#475569; background:#f1f5f9; border:2px solid #e2e8f0; transition:all .2s; }
    .reo-step-body { min-width:0; display:flex; flex-direction:column; gap:.1rem; }
    .reo-step-body small { font-size:.68rem; font-weight:700; letter-spacing:.12em; text-transform:uppercase; color:#94a3b8; }
    .reo-step-body strong { font-size:.9rem; color:#0f172a; line-height:1.3; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden; }
    .reo-step-status { align-self:flex-start; margin-top:.35rem; font-size:.7rem; font-weight:700; padding:.2rem .55rem; border-radius:999px; background:#f1f5f9; color:#64748b; display:inline-flex; align-items:center; gap:.3rem; }

    .reo-step.is-passed    .reo-step-dot    { background:#10b981; border-color:#10b981; color:#fff; }
    .reo-step.is-passed    .reo-step-status { background:#d1fae5; color:#047857; }
    .reo-step.is-submitted .reo-step-dot    { background:#fef3c7; border-color:#f59e0b; color:#b45309; }
    .reo-step.is-submitted .reo-step-status { background:#fef3c7; color:#92400e; }
    .reo-step.is-failed    .reo-step-dot    { background:#fee2e2; border-color:#ef4444; color:#b91c1c; }
    .reo-step.is-failed    .reo-step-status { background:#fee2e2; color:#b91c1c; }
    .reo-step.is-locked { cursor:not-allowed; background:#f8fafc; border-style:dashed; }
    .reo-step.is-locked:hover { transform:none; box-shadow:none; border-color:#e2e8f0; }
    .reo-step.is-locked .reo-step-body strong, .reo-step.is-locked .reo-step-dot { color:#94a3b8; }
    .reo-step.is-active { border-color:#0f766e; background:linear-gradient(180deg,#f0fdfa,#fff); box-shadow:0 0 0 4px rgba(20,184,166,.15), 0 10px 24px rgba(15,118,110,.12); }
    .reo-step.is-active:not(.is-passed):not(.is-submitted):not(.is-failed) .reo-step-dot { background:#0f766e; border-color:#0f766e; color:#fff; }

    @media (max-width:992px) {
        .reo-steps { grid-template-columns:repeat(2,minmax(0,1fr)); }
        .reo-step::after { display:none; }
    }
    @media (max-width:480px) {
        .reo-steps { grid-template-columns:1fr; }
        .reo-progress { flex-basis:100%; min-width:0; }
    }
    .reo-section { background:#f8fafc; border:1px solid #e2e8f0; border-radius:16px; padding:1rem; margin-top:1.25rem; }
    .reo-section-title { display:flex; align-items:center; justify-content:space-between; gap:.75rem; flex-wrap:wrap; margin-bottom:.75rem; }
    .reo-section-title h5 { margin:0; font-weight:700; color:#0f172a; font-size:1.05rem; }
    .reo-module-card { display:flex; align-items:center; gap:1.1rem; margin-top:1.25rem; padding:1.1rem 1.25rem; border-radius:16px; color:#fff;
                       background:linear-gradient(135deg,#0f766e 0%,#0e7490 55%,#155e75 100%); box-shadow:0 12px 28px rgba(14,116,144,.25); position:relative; overflow:hidden; }
    .reo-module-card::after { content:''; position:absolute; width:220px; height:220px; right:-60px; top:-120px; border-radius:50%; background:rgba(255,255,255,.08); }
    .reo-module-card > * { position:relative; z-index:1; }
    .reo-module-icon { width:52px; height:52px; flex:0 0 52px; border-radius:14px; display:flex; align-items:center; justify-content:center; font-size:1.5rem; background:rgba(255,255,255,.16); border:1px solid rgba(255,255,255,.25); }
    .reo-module-info { flex:1; min-width:0; }
    .reo-module-info small { display:block; font-size:.7rem; font-weight:700; letter-spacing:.14em; text-transform:uppercase; color:rgba(255,255,255,.75); }
    .reo-module-info h5 { margin:.1rem 0 .2rem; font-weight:700; font-size:1.1rem; color:#fff; }
    .reo-module-info span { font-size:.85rem; color:rgba(255,255,255,.85); }
    .reo-read-btn { flex:0 0 auto; display:inline-flex; align-items:center; gap:.5rem; padding:.7rem 1.3rem; border-radius:12px; font-weight:700; text-decoration:none;
                    color:#0f766e; background:#fff; box-shadow:0 8px 18px rgba(15,23,42,.18); transition:transform .15s, box-shadow .15s; }
    .reo-read-btn:hover { color:#0e7490; transform:translateY(-1px); box-shadow:0 12px 24px rgba(15,23,42,.24); }
    @media (max-width:576px) {
        .reo-module-card { flex-wrap:wrap; }
        .reo-read-btn { width:100%; justify-content:center; }
    }

    /* Intro + completion modals */
    .reo-modal .modal-content { border:0; border-radius:22px; overflow:hidden; box-shadow:0 30px 70px rgba(15,23,42,.35); }
    .reo-hero { position:relative; overflow:hidden; color:#fff; padding:2rem 2rem 1.75rem; background:linear-gradient(135deg,#0f766e 0%,#0e7490 55%,#155e75 100%); }
    .reo-hero::before, .reo-hero::after { content:''; position:absolute; border-radius:50%; background:rgba(255,255,255,.08); }
    .reo-hero::before { width:260px; height:260px; right:-70px; top:-110px; }
    .reo-hero::after  { width:160px; height:160px; right:90px; bottom:-100px; background:rgba(255,255,255,.06); }
    .reo-hero > * { position:relative; z-index:1; }
    .reo-hero-icon { width:54px; height:54px; border-radius:16px; display:inline-flex; align-items:center; justify-content:center; font-size:1.6rem; background:rgba(255,255,255,.16); border:1px solid rgba(255,255,255,.25); margin-bottom:1rem; }
    .reo-eyebrow { display:inline-block; font-size:.7rem; font-weight:700; letter-spacing:.14em; text-transform:uppercase; padding:.3rem .7rem; border-radius:999px; background:rgba(255,255,255,.15); margin-bottom:.6rem; }
    .reo-hero h3 { font-weight:800; font-size:1.9rem; margin:0; letter-spacing:-.01em; }
    .reo-hero p { margin:.35rem 0 0; font-size:1rem; color:rgba(255,255,255,.85); }
    .reo-modal .modal-body { padding:1.75rem 2rem 1rem; color:#334155; font-size:.95rem; line-height:1.7; }
    .reo-modal .modal-body p { margin:0 0 1rem; }
    .reo-pillars { display:grid; grid-template-columns:repeat(3,1fr); gap:.75rem; margin:0 0 1.25rem; }
    .reo-pillar { background:#f0fdfa; border:1px solid #ccfbf1; border-radius:14px; padding:.8rem .9rem; font-size:.85rem; font-weight:600; color:#0f766e; display:flex; align-items:center; gap:.55rem; line-height:1.3; }
    .reo-pillar i { font-size:1.15rem; }
    .reo-note { display:flex; gap:.85rem; background:#fffbeb; border:1px solid #fde68a; border-left:4px solid #f59e0b; border-radius:12px; padding:1rem 1.1rem; font-size:.85rem; line-height:1.6; color:#78350f; }
    .reo-note i { font-size:1.3rem; color:#d97706; line-height:1; }
    .reo-note strong { display:block; font-size:.75rem; letter-spacing:.12em; color:#92400e; margin-bottom:.2rem; }
    .reo-modal .modal-footer { border:0; padding:.5rem 2rem 1.75rem; display:flex; align-items:center; justify-content:space-between; gap:1rem; }
    .reo-footnote { font-size:.8rem; color:#94a3b8; display:flex; align-items:center; gap:.4rem; }
    .reo-btn { border:0; border-radius:12px; padding:.75rem 1.6rem; font-weight:700; color:#fff; background:linear-gradient(135deg,#0f766e,#0e7490); box-shadow:0 10px 22px rgba(14,116,144,.3); transition:transform .15s, box-shadow .15s; }
    .reo-btn:hover { color:#fff; transform:translateY(-1px); box-shadow:0 14px 28px rgba(14,116,144,.38); }
    .reo-complete .reo-hero { text-align:center; padding:2.25rem 2rem 2rem; background:linear-gradient(135deg,#059669 0%,#0f766e 60%,#0e7490 100%); }
    .reo-complete .reo-hero-icon { width:76px; height:76px; border-radius:50%; font-size:2.4rem; background:#fff; color:#059669; border:6px solid rgba(255,255,255,.3); background-clip:padding-box; animation:reoPop .5s cubic-bezier(.2,1.4,.4,1) both; }
    .reo-complete .modal-body { text-align:center; }
    .reo-cert { display:flex; gap:.85rem; text-align:left; background:#f0fdfa; border:1px solid #ccfbf1; border-radius:14px; padding:1rem 1.1rem; margin:0 0 1rem; font-size:.9rem; color:#134e4a; }
    .reo-cert i { font-size:1.5rem; color:#0f766e; line-height:1; }
    .reo-tagline { font-weight:700; color:#0f766e; }
    .reo-complete .modal-footer { justify-content:center; }
    @keyframes reoPop { from { transform:scale(.4); opacity:0; } to { transform:scale(1); opacity:1; } }
    @media (max-width:576px) {
        .reo-hero, .reo-modal .modal-body { padding-left:1.25rem; padding-right:1.25rem; }
        .reo-modal .modal-footer { padding:.5rem 1.25rem 1.25rem; flex-direction:column-reverse; align-items:stretch; }
        .reo-pillars { grid-template-columns:1fr; }
        .reo-hero h3 { font-size:1.5rem; }
    }
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
            <span class="reo-head-eyebrow"><i class="bi bi-arrow-repeat"></i> CSMC in Sync</span>
            <h4>Re-Orientation</h4>
            <span>Read each module, then answer its post test. Modules open in order after you submit the previous post test.</span>
        </div>
        <div class="reo-progress">
            <div class="reo-progress-label">
                <span id="reo_progress_text">&nbsp;</span>
                <strong id="reo_progress_pct">0%</strong>
            </div>
            <div class="reo-progress-track"><div class="reo-progress-fill" id="reo_progress_fill"></div></div>
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

    <div class="reo-steps" id="reo_module_tabs" style="--reo-steps: {{ count($modules) }}">
        @foreach($modules as $no => $module)
            <button class="reo-step {{ $loop->first ? 'is-active' : '' }}" type="button" data-module="{{ $no }}">
                <span class="reo-step-dot">{{ $no }}</span>
                <span class="reo-step-body">
                    <small>Module {{ $no }}</small>
                    <strong title="{{ $module['title'] }}">{{ $module['title'] }}</strong>
                    <span class="reo-step-status">&nbsp;</span>
                </span>
            </button>
        @endforeach
    </div>

    <div class="reo-module-card">
        <div class="reo-module-icon"><i class="bi bi-book-half"></i></div>
        <div class="reo-module-info">
            <small id="reo_module_no">Module</small>
            <h5 id="reo_module_title">&nbsp;</h5>
            <span><i class="bi bi-eye"></i> Read the module first, then answer its post test below. View only.</span>
        </div>
        <a href="#" target="_blank" rel="noopener" class="reo-read-btn" id="reo_read_link">
            <i class="bi bi-box-arrow-up-right"></i> Read Module
        </a>
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

<div class="modal fade reo-modal" id="reoIntroModal" tabindex="-1" aria-labelledby="reoIntroTitle" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="reo-hero">
                <div class="reo-hero-icon"><i class="bi bi-arrow-repeat"></i></div>
                <div><span class="reo-eyebrow">Employee Re-orientation e-Module</span></div>
                <h3 id="reoIntroTitle">CSMC in Sync</h3>
                <p>A Re-orientation on Institutional Policies and Guidelines</p>
            </div>
            <div class="modal-body">
                <p>Welcome to <strong>CSMC in Sync</strong>, your learning e-module in getting to know the updated policies, programs, and guidelines of Cebu South Medical Center (CSMC).</p>
                <p>This e-module is designed to provide employees with a centralized and accessible guide to the institution’s policies, programs, and administrative procedures, through the use of the official CSMC Employee Handbook. It aims to refresh employees’ knowledge, clarify institutional guidelines, and promote a shared understanding of the standards, responsibilities, and practices that guide our workplace.</p>
                <div class="reo-pillars">
                    <div class="reo-pillar"><i class="bi bi-lightbulb"></i> Refresh your knowledge</div>
                    <div class="reo-pillar"><i class="bi bi-journal-check"></i> Clarify institutional guidelines</div>
                    <div class="reo-pillar"><i class="bi bi-people"></i> Share common standards</div>
                </div>
                <div class="reo-note">
                    <i class="bi bi-shield-lock-fill"></i>
                    <div>
                        <strong>IMPORTANT NOTE</strong>
                        This module is intended exclusively for CSMC employees for re-orientation purposes. Its contents are for internal use only and shall not be reproduced or distributed without proper authorization. In case of any inconsistency, the latest duly approved and officially issued policies and guidelines shall prevail.
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <span class="reo-footnote"><i class="bi bi-book"></i> Based on the official CSMC Employee Handbook</span>
                <button type="button" class="reo-btn" data-bs-dismiss="modal">I understand <i class="bi bi-arrow-right ms-1"></i></button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade reo-modal reo-complete" id="reoCompleteModal" tabindex="-1" aria-labelledby="reoCompleteTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="reo-hero">
                <div class="reo-hero-icon"><i class="bi bi-check-lg"></i></div>
                <h3 id="reoCompleteTitle">Congratulations!</h3>
                <p>You have successfully completed the CSMC Employee Re-orientation Knowledge Check.</p>
            </div>
            <div class="modal-body">
                <div class="reo-cert">
                    <i class="bi bi-envelope-paper-heart"></i>
                    <div>Your <strong>Certificate of Completion</strong> will be sent to your registered email address within <strong>7 working days</strong> from your date of assessment.</div>
                </div>
                <p class="reo-tagline mb-0">Keep learning, stay informed, and continue to be in sync with CSMC!</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="reo-btn" data-bs-dismiss="modal">Close</button>
            </div>
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

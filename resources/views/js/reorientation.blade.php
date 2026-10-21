<script>
    const ROUTE_REO_MY_MODULES    = "{{ route('reorientation.my_modules') }}";
    const ROUTE_REO_MY_POST_TEST  = "{{ route('reorientation.my_post_test') }}";
    const ROUTE_REO_SUBMIT        = "{{ route('reorientation.submit') }}";
    const CAN_MANAGE_REO          = @json(permission_check('reorientation.manage'));
    @if(permission_check('reorientation.manage'))
    const ROUTE_REO_QUESTIONS     = "{{ route('reorientation.questions') }}";
    const ROUTE_REO_SAVE_QUESTION = "{{ route('reorientation.questions.save') }}";
    const ROUTE_REO_DEL_QUESTION  = "{{ route('reorientation.questions.delete', ':id') }}";
    const ROUTE_REO_MOVE_QUESTION = "{{ route('reorientation.questions.move', ':id') }}";
    @endif

    const REO_STATUS = {
        not_started: {label: 'Not started',        cls: 'bg-secondary'},
        submitted:   {label: 'Pending checking',   cls: 'bg-warning text-dark'},
        passed:      {label: 'Passed',             cls: 'bg-success'},
        failed:      {label: 'Failed — resubmit',  cls: 'bg-danger'},
    };

    let reoModules = [];
    let reoCurrentModule = 1;
    let mqEditingId = null;

    function reoEscape(value) {
        return $('<div>').text(value == null ? '' : String(value)).html();
    }

    function reoBadge(status) {
        const s = REO_STATUS[status] || REO_STATUS.not_started;
        return `<span class="badge ${s.cls}">${s.label}</span>`;
    }

    function reoHeaders() {
        return {'Content-Type': 'application/json', 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')};
    }

    $(function () {
        loadModules().then(() => selectModule(reoCurrentModule));

        $('#reo_module_tabs').on('click', '.nav-link', function () {
            $('#reo_module_tabs .nav-link').removeClass('active');
            $(this).addClass('active');
            selectModule(parseInt($(this).data('module')));
        });

        $('#btn_submit_post_test').on('click', submitPostTest);

        if (CAN_MANAGE_REO) {
            $('#btn_manage_questions').on('click', function () {
                $('#mq_module').val(reoCurrentModule);
                resetQuestionForm();
                loadManageQuestions();
                bootstrap.Modal.getOrCreateInstance(document.getElementById('manageQuestionsModal')).show();
            });
            $('#mq_module').on('change', function () { resetQuestionForm(); loadManageQuestions(); });
            $('#btn_mq_save').on('click', saveQuestion);
            $('#btn_mq_cancel_edit').on('click', resetQuestionForm);
            $('#mq_list').on('click', '.mq-edit', function () {
                mqEditingId = $(this).data('id');
                $('#mq_question').val($(this).closest('.reo-manage-item').find('.reo-manage-text').text()).focus();
                $('#mq_form_label').text('Edit question');
                $('#btn_mq_cancel_edit').removeClass('d-none');
            });
            $('#mq_list').on('click', '.mq-delete', function () { deleteQuestion($(this).data('id')); });
            $('#mq_list').on('click', '.mq-move', function () { moveQuestion($(this).data('id'), $(this).data('dir')); });
            // Refresh the employee view when HR closes the manager, since questions may have changed
            $('#manageQuestionsModal').on('hidden.bs.modal', () => selectModule(reoCurrentModule));
        }
    });

    // -----------------------------------------------------------------------
    // Employee view
    // -----------------------------------------------------------------------
    async function loadModules() {
        try {
            reoModules = await (await fetch(ROUTE_REO_MY_MODULES)).json();
            reoModules.forEach(m => {
                const s = REO_STATUS[m.status] || REO_STATUS.not_started;
                $(`.reo-tab-badge[data-module="${m.module_no}"]`)
                    .attr('class', `badge reo-tab-badge ${s.cls}`)
                    .text(s.label);
            });
        } catch (e) {
            toastr.error('Could not load modules, please refresh the page.');
        }
    }

    function selectModule(moduleNo) {
        reoCurrentModule = moduleNo;
        const module = reoModules.find(m => m.module_no === moduleNo);
        if (module) {
            $('#reo_module_title').text(`Module ${module.module_no}: ${module.title}`);
            if ($('#reo_pdf').attr('src') !== module.pdf_url) {
                $('#reo_pdf').attr('src', module.pdf_url);
            }
            $('#reo_pdf_link').attr('href', module.pdf_url);
        }
        loadPostTest(moduleNo);
    }

    async function loadPostTest(moduleNo) {
        $('#reo_post_test').html('<div class="text-muted text-center py-4">Loading post test...</div>');
        $('#reo_remarks').empty();
        $('#reo_submit_wrap').addClass('d-none');

        let data;
        try {
            const res = await fetch(ROUTE_REO_MY_POST_TEST + '?module_no=' + moduleNo);
            data = await res.json();
            if (!res.ok) throw new Error(data.error);
        } catch (e) {
            $('#reo_post_test').html('<div class="text-danger text-center py-4">Could not load the post test.</div>');
            return;
        }
        if (moduleNo !== reoCurrentModule) return;

        $('#reo_status_badge').html(reoBadge(data.status) +
            (data.attempt_no > 1 ? ` <span class="small text-muted ms-1">Attempt ${data.attempt_no}</span>` : ''));

        if (data.remarks && (data.status === 'passed' || data.status === 'failed')) {
            const cls = data.status === 'passed' ? 'alert-success' : 'alert-danger';
            $('#reo_remarks').html(`<div class="alert ${cls} reo-remarks"><strong>HR remarks:</strong>\n${reoEscape(data.remarks)}</div>`);
        }

        if (!data.items.length) {
            $('#reo_post_test').html('<div class="text-muted text-center py-4">No post test questions for this module yet.</div>');
            return;
        }

        let html = '';
        if (data.status === 'submitted') {
            html += '<div class="alert alert-warning small">Your answers have been submitted and are waiting for HR to check them.</div>';
        } else if (data.status === 'failed') {
            html += '<div class="alert alert-info small">Review the remarks, revise your answers, and submit again.</div>';
        }

        data.items.forEach((it, i) => {
            html += `<div class="reo-question">
                <label class="form-label" for="reo_answer_${it.question_id}">${i + 1}. ${reoEscape(it.question)}</label>`;
            if (data.locked) {
                html += `<div class="reo-answer-readonly">${reoEscape(it.answer)}</div>`;
            } else {
                html += `<textarea class="form-control reo-answer" id="reo_answer_${it.question_id}" data-question="${it.question_id}"
                    placeholder="Write your answer in paragraph form...">${reoEscape(it.answer)}</textarea>`;
            }
            html += '</div>';
        });

        $('#reo_post_test').html(html);
        $('#reo_submit_wrap').toggleClass('d-none', data.locked);
        $('#btn_submit_post_test').html(data.status === 'failed'
            ? '<i class="bi bi-send"></i> Resubmit Post Test'
            : '<i class="bi bi-send"></i> Submit Post Test');
    }

    async function submitPostTest() {
        const answers = {};
        let missing = false;
        $('.reo-answer').each(function () {
            const value = $(this).val().trim();
            $(this).toggleClass('is-invalid', value === '');
            if (value === '') missing = true;
            answers[$(this).data('question')] = value;
        });

        if (missing) {
            toastr.warning('Please answer all questions before submitting.');
            return;
        }
        if (!confirm('Submit your post test? You cannot edit your answers while HR is checking them.')) return;

        const $btn = $('#btn_submit_post_test').prop('disabled', true);
        try {
            const res = await fetch(ROUTE_REO_SUBMIT, {
                method: 'POST',
                headers: reoHeaders(),
                body: JSON.stringify({module_no: reoCurrentModule, answers: answers}),
            });
            const data = await res.json();
            if (!res.ok) {
                toastr.error(data.error || 'Something went wrong, please try again');
                return;
            }
            toastr.success(data.message);
            await loadModules();
            loadPostTest(reoCurrentModule);
        } catch (e) {
            toastr.error('Something went wrong, please try again');
        } finally {
            $btn.prop('disabled', false);
        }
    }

    // -----------------------------------------------------------------------
    // Question management (HR)
    // -----------------------------------------------------------------------
    function resetQuestionForm() {
        mqEditingId = null;
        $('#mq_question').val('');
        $('#mq_form_label').text('Add a question');
        $('#btn_mq_cancel_edit').addClass('d-none');
    }

    async function loadManageQuestions() {
        $('#mq_list').html('<div class="text-muted text-center py-3">Loading...</div>');
        try {
            const questions = await (await fetch(ROUTE_REO_QUESTIONS + '?module_no=' + $('#mq_module').val())).json();
            if (!questions.length) {
                $('#mq_list').html('<div class="text-muted text-center py-3">No questions yet for this module.</div>');
                return;
            }
            let html = '';
            questions.forEach((q, i) => {
                html += `<div class="reo-manage-item">
                    <span class="fw-bold">${i + 1}.</span>
                    <div class="reo-manage-text">${reoEscape(q.question)}</div>
                    <div class="btn-group btn-group-sm">
                        <button class="btn btn-outline-secondary mq-move" data-id="${q.id}" data-dir="up" title="Move up" ${i === 0 ? 'disabled' : ''}><i class="bi bi-arrow-up"></i></button>
                        <button class="btn btn-outline-secondary mq-move" data-id="${q.id}" data-dir="down" title="Move down" ${i === questions.length - 1 ? 'disabled' : ''}><i class="bi bi-arrow-down"></i></button>
                        <button class="btn btn-outline-primary mq-edit" data-id="${q.id}" title="Edit"><i class="bi bi-pencil"></i></button>
                        <button class="btn btn-outline-danger mq-delete" data-id="${q.id}" title="Remove"><i class="bi bi-trash"></i></button>
                    </div>
                </div>`;
            });
            $('#mq_list').html(html);
        } catch (e) {
            $('#mq_list').html('<div class="text-danger text-center py-3">Could not load questions.</div>');
        }
    }

    async function reoSend(url, method, body) {
        const res = await fetch(url, {method: method, headers: reoHeaders(), body: body ? JSON.stringify(body) : null});
        const data = await res.json();
        if (!res.ok) throw new Error(data.error || 'Something went wrong, please try again');
        return data;
    }

    async function saveQuestion() {
        const question = $('#mq_question').val().trim();
        if (!question) {
            toastr.warning('Please type the question.');
            return;
        }
        try {
            const data = await reoSend(ROUTE_REO_SAVE_QUESTION, 'POST', {module_no: $('#mq_module').val(), question: question, id: mqEditingId});
            toastr.success(data.message);
            resetQuestionForm();
            loadManageQuestions();
        } catch (e) {
            toastr.error(e.message);
        }
    }

    async function deleteQuestion(id) {
        if (!confirm('Remove this question? Answers already submitted are kept.')) return;
        try {
            const data = await reoSend(ROUTE_REO_DEL_QUESTION.replace(':id', id), 'DELETE');
            toastr.success(data.message);
            if (mqEditingId === id) resetQuestionForm();
            loadManageQuestions();
        } catch (e) {
            toastr.error(e.message);
        }
    }

    async function moveQuestion(id, direction) {
        try {
            await reoSend(ROUTE_REO_MOVE_QUESTION.replace(':id', id), 'POST', {direction: direction});
            loadManageQuestions();
        } catch (e) {
            toastr.error(e.message);
        }
    }
</script>

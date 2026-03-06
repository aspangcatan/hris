@extends('master')

@section('content')
    @php
        $canApproveOvertime = in_array(session('access_rights'), ['admin'], true) || session('is_section_head') || session('is_division_head');
    @endphp

    @if(!$canApproveOvertime)
        <div class="alert alert-danger">You are not authorized to approve/reject overtime filings.</div>
    @else
        <style>
            .ot-approval-page {
                --ot-surface: #ffffff;
                --ot-line: #dce6f2;
                --ot-muted: #5e728d;
                --ot-grad-1: #14532d;
                --ot-grad-2: #16a34a;
                --ot-grad-3: #22c55e;
            }

            .ot-approval-shell {
                border: 0;
                border-radius: 20px;
                box-shadow: 0 24px 44px rgba(15, 23, 42, 0.10);
                background: linear-gradient(180deg, #f8fbff 0%, #ffffff 100%);
                overflow: hidden;
            }

            .ot-approval-header {
                background: linear-gradient(125deg, var(--ot-grad-1), var(--ot-grad-2), var(--ot-grad-3));
                color: #fff;
                padding: 1.1rem 1.2rem;
            }

            .ot-approval-title {
                margin: 0;
                font-weight: 800;
                letter-spacing: 0.2px;
            }

            .ot-approval-subtitle {
                margin: .2rem 0 0;
                opacity: .95;
                font-weight: 500;
                font-size: .9rem;
            }

            .ot-approval-table-wrap {
                border: 1px solid var(--ot-line);
                border-radius: 14px;
                overflow: hidden;
                background: #fff;
            }

            .ot-approval-table {
                margin-bottom: 0;
            }

            .ot-approval-table thead th {
                background: #f5f9ff;
                color: #4f6684;
                text-transform: uppercase;
                letter-spacing: .06em;
                font-size: .72rem;
                font-weight: 800;
            }

            .ot-approval-table tbody tr:hover {
                background: #f8fbff;
            }

            .ot-empty {
                text-align: center;
                color: #6f829a;
                padding: 1rem .5rem !important;
            }

            .ot-pagination .page-link {
                border-radius: 10px;
                margin: 0 2px;
            }

            .ot-approval-search {
                max-width: 340px;
            }
        </style>

        <div class="ot-approval-page">
            <div class="card ot-approval-shell">
                <div class="ot-approval-header d-flex justify-content-between align-items-center gap-2">
                    <div>
                        <h4 class="ot-approval-title">Approve / Reject Overtime Filings</h4>
                        <p class="ot-approval-subtitle">Review pending overtime requests and add optional remarks</p>
                    </div>
                    <a href="{{ route('dtr') }}" class="btn btn-light btn-sm">Back</a>
                </div>

                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-center mb-2 gap-2 flex-wrap">
                        <h6 class="mb-0 fw-bold">Filings for Review</h6>
                        <input type="text" id="reviewSearch" class="form-control ot-approval-search" placeholder="Search employee/reason/status...">
                    </div>

                    <div class="ot-approval-table-wrap">
                        <div class="table-responsive">
                            <table class="table table-bordered table-sm align-middle ot-approval-table">
                                <thead>
                                <tr>
                                    <th>Employee</th>
                                    <th>Date</th>
                                    <th>Time Range</th>
                                    <th>Reason</th>
                                    <th>Status</th>
                                    <th>Remarks</th>
                                    <th>Action</th>
                                </tr>
                                </thead>
                                <tbody id="review_list">
                                <tr><td colspan="7" class="ot-empty">Loading...</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="d-flex justify-content-between align-items-center mt-3 flex-wrap gap-2">
                        <small class="text-muted" id="reviewSummary"></small>
                        <nav>
                            <ul class="pagination pagination-sm mb-0 ot-pagination" id="reviewPagination"></ul>
                        </nav>
                    </div>
                </div>
            </div>
        </div>
    @endif
@stop

@section('js')
<script>
    let allFilingsForReview = [];
    let filteredFilingsForReview = [];
    let currentPage = 1;
    const pageSize = 8;

    $(document).ready(function () {
        getFilingsForReview();

        $('#reviewSearch').on('input', function () {
            currentPage = 1;
            applyReviewFilter();
        });
    });

    function getFilingsForReview() {
        fetch("{{ route('overtime.filings-for-review') }}")
            .then(res => res.json())
            .then(data => {
                allFilingsForReview = data || [];
                currentPage = 1;
                applyReviewFilter();
            })
            .catch(() => {
                $('#review_list').html('<tr><td colspan="7" class="ot-empty text-danger">Failed to load.</td></tr>');
                $('#reviewPagination').empty();
                $('#reviewSummary').text('');
            });
    }

    function applyReviewFilter() {
        const keyword = ($('#reviewSearch').val() || '').trim().toLowerCase();

        filteredFilingsForReview = allFilingsForReview.filter(it => {
            const employee = String((it.employee_name || '').replace(/\s+/g, ' ').trim()).toLowerCase();
            const reason = String(it.reason || '').toLowerCase();
            const status = String(it.status || '').toLowerCase();
            return employee.includes(keyword) || reason.includes(keyword) || status.includes(keyword);
        });

        renderReviewRows();
        renderReviewPagination();
    }

    function renderReviewRows() {
        const $list = $('#review_list');
        $list.empty();

        if (!filteredFilingsForReview.length) {
            $list.html('<tr><td colspan="7" class="ot-empty">No overtime filings for review.</td></tr>');
            $('#reviewSummary').text('0 results');
            return;
        }

        const total = filteredFilingsForReview.length;
        const totalPages = Math.ceil(total / pageSize);
        if (currentPage > totalPages) currentPage = totalPages;

        const startIndex = (currentPage - 1) * pageSize;
        const pageItems = filteredFilingsForReview.slice(startIndex, startIndex + pageSize);

        pageItems.forEach(it => {
            const canReview = String(it.status || '').toLowerCase() === 'pending';
            $list.append(`
                <tr>
                    <td>${escapeHtml((it.employee_name || '').replace(/\s+/g, ' ').trim())}</td>
                    <td>${formatDate(it.ot_date)}</td>
                    <td>${formatTimeRange(it.time_from, it.time_to)}</td>
                    <td>${escapeHtml(it.reason || '-')}</td>
                    <td>${statusBadge(it.status)}</td>
                    <td>${escapeHtml(it.remarks || '-')}</td>
                    <td>
                        ${canReview ? `
                            <button type="button" class="btn btn-success btn-sm me-1 mb-1" onclick="reviewFiling(${it.id}, 'approve')">Approve</button>
                            <button type="button" class="btn btn-danger btn-sm mb-1" onclick="reviewFiling(${it.id}, 'reject')">Reject</button>
                        ` : '-'}
                    </td>
                </tr>
            `);
        });

        const from = startIndex + 1;
        const to = Math.min(startIndex + pageItems.length, total);
        $('#reviewSummary').text(`Showing ${from}-${to} of ${total} result(s)`);
    }

    function renderReviewPagination() {
        const $pagination = $('#reviewPagination');
        $pagination.empty();

        const totalPages = Math.ceil(filteredFilingsForReview.length / pageSize);
        if (totalPages <= 1) return;

        const prevDisabled = currentPage === 1 ? 'disabled' : '';
        $pagination.append(`
            <li class="page-item ${prevDisabled}">
                <a class="page-link" href="#" data-page="prev">Prev</a>
            </li>
        `);

        for (let i = 1; i <= totalPages; i++) {
            const active = i === currentPage ? 'active' : '';
            $pagination.append(`
                <li class="page-item ${active}">
                    <a class="page-link" href="#" data-page="${i}">${i}</a>
                </li>
            `);
        }

        const nextDisabled = currentPage === totalPages ? 'disabled' : '';
        $pagination.append(`
            <li class="page-item ${nextDisabled}">
                <a class="page-link" href="#" data-page="next">Next</a>
            </li>
        `);

        $pagination.find('a').on('click', function (e) {
            e.preventDefault();
            const page = $(this).data('page');

            if (page === 'prev' && currentPage > 1) currentPage--;
            if (page === 'next' && currentPage < totalPages) currentPage++;
            if (Number.isInteger(page)) currentPage = page;

            renderReviewRows();
            renderReviewPagination();
        });
    }

    function reviewFiling(id, action) {
        const endpoint = action === 'approve'
            ? `{{ url('/overtime/filings') }}/${id}/approve`
            : `{{ url('/overtime/filings') }}/${id}/reject`;

        const actionLabel = action === 'approve' ? 'Approve' : 'Reject';
        const actionColor = action === 'approve' ? '#198754' : '#dc3545';

        Swal.fire({
            title: `${actionLabel} Filing`,
            input: 'text',
            inputLabel: 'Remarks (optional)',
            inputPlaceholder: 'Enter remarks',
            showCancelButton: true,
            confirmButtonText: actionLabel,
            confirmButtonColor: actionColor
        }).then((result) => {
            if (!result.isConfirmed) return;

            fetch(endpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({remarks: result.value || ''})
            })
                .then(res => res.json())
                .then(data => {
                    if (data.error || data.errors) throw data;
                    toastr.success(data.message || 'Updated.', 'Success');
                    getFilingsForReview();
                })
                .catch(err => showApiError(err, 'Failed to update overtime status.'));
        });
    }

    function formatDate(v) {
        if (!v) return '-';
        return moment(v, 'YYYY-MM-DD').format('MMM D, YYYY');
    }

    function formatTimeRange(from, to) {
        if (!from || !to) return '-';
        return `${moment(from, 'HH:mm:ss').format('hh:mm A')} - ${moment(to, 'HH:mm:ss').format('hh:mm A')}`;
    }

    function statusBadge(status) {
        const s = (status || 'pending').toLowerCase();
        let cls = 'secondary';
        if (s === 'approved') cls = 'success';
        if (s === 'rejected') cls = 'danger';
        if (s === 'pending') cls = 'warning text-dark';
        return `<span class="badge bg-${cls} text-uppercase">${escapeHtml(s)}</span>`;
    }

    function showApiError(errorPayload, fallbackMessage) {
        if (errorPayload && errorPayload.errors) {
            const firstKey = Object.keys(errorPayload.errors)[0];
            const firstError = firstKey ? errorPayload.errors[firstKey][0] : fallbackMessage;
            toastr.error(firstError, 'Error');
            return;
        }
        if (errorPayload && errorPayload.error) {
            toastr.error(errorPayload.error, 'Error');
            return;
        }
        toastr.error(fallbackMessage, 'Error');
    }

    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }
</script>
@stop

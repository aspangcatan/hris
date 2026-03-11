@extends('master')

@section('content')
    @php
        $canApproveOvertime = in_array(session('access_rights'), ['admin'], true) || session('is_section_head') || session('is_division_head');
    @endphp

    @if(!$canApproveOvertime)
        <div class="alert alert-danger">You are not authorized to approve/reject overtime filings.</div>
    @else
        <style>
            .ot-board {
                background: #ffffff;
                border: 1px solid #e2e8f0;
                border-radius: 20px;
                padding: 1.5rem;
                box-shadow: 0 18px 38px rgba(15, 23, 42, 0.08);
            }

            .ot-head {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 1rem;
                margin-bottom: 1.25rem;
                flex-wrap: wrap;
            }

            .ot-head-title h4 {
                margin: 0;
                font-weight: 700;
                color: #0f172a;
            }

            .ot-head-title span {
                font-size: 0.9rem;
                color: #64748b;
            }

            .ot-panel {
                background: #f8fafc;
                border: 1px solid #e2e8f0;
                border-radius: 16px;
                padding: 1rem;
                margin-bottom: 1.25rem;
            }

            .ot-panel .form-control {
                border-radius: 12px;
                border: 1px solid #d8e1ee;
                background: #fff;
            }

            .ot-table-shell {
                border: 1px solid #e2e8f0;
                border-radius: 16px;
                overflow: hidden;
                background: #fff;
            }

            .ot-table-shell table {
                margin-bottom: 0;
            }

            .ot-table-shell table thead th {
                background: #f8fafc;
                color: #0f172a;
                font-size: 0.72rem;
                text-transform: uppercase;
                letter-spacing: 0.08em;
            }

            .ot-table-shell table tbody td {
                color: #1e293b;
                font-weight: 500;
                vertical-align: middle;
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

            .ot-search {
                max-width: 340px;
            }
        </style>

        <div class="ot-board">
            <div class="ot-head">
                <div class="ot-head-title">
                    <h4>Overtime Filings Review</h4>
                    <span>Review pending overtime requests and add optional remarks</span>
                </div>
            </div>

            <div class="ot-panel d-flex justify-content-between align-items-center gap-2 flex-wrap">
                <div class="fw-bold">Filings for Review</div>
                <input type="text" id="reviewSearch" class="form-control ot-search" placeholder="Search employee/date/status...">
            </div>

            <div class="ot-table-shell">
                <div class="table-responsive">
                    <table class="table table-bordered table-sm align-middle">
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
            const date = String(it.ot_date || '').toLowerCase();
            const status = String(it.status || '').toLowerCase();
            return employee.includes(keyword) || date.includes(keyword) || status.includes(keyword);
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
                            <button type="button" class="btn btn-action btn-edit me-1 mb-1" onclick="reviewFiling(${it.id}, 'approve')" title="Approve"><i class="bi bi-check2"></i></button>
                            <button type="button" class="btn btn-action btn-delete mb-1" onclick="reviewFiling(${it.id}, 'reject')" title="Reject"><i class="bi bi-x"></i></button>
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

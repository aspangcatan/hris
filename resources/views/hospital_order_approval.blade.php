@extends('master')

@section('content')
    @php
        $canApproveHospitalOrder = in_array(session('access_rights'), ['admin'], true) || session('is_section_head') || session('is_division_head');
    @endphp

    @if(!$canApproveHospitalOrder)
        <div class="alert alert-danger">You are not authorized to approve/reject filings.</div>
    @else
        <style>
            .ho-board {
                background: #ffffff;
                border: 1px solid #e2e8f0;
                border-radius: 20px;
                padding: 1.5rem;
                box-shadow: 0 18px 38px rgba(15, 23, 42, 0.08);
            }

            .ho-head {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 1rem;
                margin-bottom: 1.25rem;
                flex-wrap: wrap;
            }

            .ho-head-title h4 {
                margin: 0;
                font-weight: 700;
                color: #0f172a;
            }

            .ho-head-title span {
                font-size: 0.9rem;
                color: #64748b;
            }

            .ho-panel {
                background: #f8fafc;
                border: 1px solid #e2e8f0;
                border-radius: 16px;
                padding: 1rem;
                margin-bottom: 1.25rem;
            }

            .ho-panel .form-control {
                border-radius: 12px;
                border: 1px solid #d8e1ee;
                background: #fff;
            }

            .ho-table-shell {
                border: 1px solid #e2e8f0;
                border-radius: 16px;
                overflow: hidden;
                background: #fff;
            }

            .ho-table-shell table {
                margin-bottom: 0;
            }

            .ho-table-shell table thead th {
                background: #f8fafc;
                color: #0f172a;
                font-size: 0.72rem;
                text-transform: uppercase;
                letter-spacing: 0.08em;
            }

            .ho-table-shell table tbody td {
                color: #1e293b;
                font-weight: 500;
                vertical-align: middle;
            }

            .ho-empty {
                text-align: center;
                color: #6f829a;
                padding: 1rem .5rem !important;
            }

            .ho-pagination .page-link {
                border-radius: 10px;
                margin: 0 2px;
            }

            .ho-chip {
                display: inline-block;
                border-radius: 999px;
                padding: .2rem .55rem;
                background: #edf4ff;
                color: #355379;
                font-weight: 600;
                font-size: .8rem;
            }

            .ho-search {
                max-width: 340px;
            }
        </style>

        <div class="ho-board">
            <div class="ho-head">
                <div class="ho-head-title">
                    <h4>Hospital Order Filings Review</h4>
                    <span>Review submitted filings and provide optional remarks</span>
                </div>
            </div>

            <div class="ho-panel d-flex justify-content-between align-items-center gap-2 flex-wrap">
                <div class="fw-bold">Filings for Review</div>
                <input type="text" id="reviewSearch" class="form-control ho-search" placeholder="Search employee/order #/status...">
            </div>

            <div class="ho-table-shell">
                <div class="table-responsive">
                    <table class="table table-bordered table-sm align-middle">
                        <thead>
                        <tr>
                            <th>Employee</th>
                            <th>Order #</th>
                            <th>Date</th>
                            <th>Time Range</th>
                            <th>Status</th>
                            <th>Filed By</th>
                            <th>Remarks</th>
                            <th>Action</th>
                        </tr>
                        </thead>
                        <tbody id="review_list">
                        <tr><td colspan="8" class="ho-empty">Loading...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="d-flex justify-content-between align-items-center mt-3 flex-wrap gap-2">
                <small class="text-muted" id="reviewSummary"></small>
                <nav>
                    <ul class="pagination pagination-sm mb-0 ho-pagination" id="reviewPagination"></ul>
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
        fetch("{{ route('hospital-orders.filings-for-review') }}")
            .then(res => res.json())
            .then(data => {
                allFilingsForReview = data || [];
                currentPage = 1;
                applyReviewFilter();
            })
            .catch(() => {
                $('#review_list').html('<tr><td colspan="8" class="ho-empty text-danger">Failed to load.</td></tr>');
                $('#reviewPagination').empty();
                $('#reviewSummary').text('');
            });
    }

    function applyReviewFilter() {
        const keyword = ($('#reviewSearch').val() || '').trim().toLowerCase();

        filteredFilingsForReview = allFilingsForReview.filter(it => {
            const employee = String((it.employee_name || '').replace(/\s+/g, ' ').trim()).toLowerCase();
            const orderNo = String(it.order_number || '').toLowerCase();
            const status = String(it.status || '').toLowerCase();
            return employee.includes(keyword) || orderNo.includes(keyword) || status.includes(keyword);
        });

        renderReviewRows();
        renderReviewPagination();
    }

    function renderReviewRows() {
        const $list = $('#review_list');
        $list.empty();

        if (!filteredFilingsForReview.length) {
            $list.html('<tr><td colspan="8" class="ho-empty">No filings for review.</td></tr>');
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
            const filedByLabel = it.filed_by
                ? `<span class="ho-chip">${escapeHtml((it.filed_by_name || '').trim())}</span>`
                : '-';
            $list.append(`
                <tr>
                    <td>${escapeHtml((it.employee_name || '').replace(/\s+/g, ' ').trim())}</td>
                    <td><span class="ho-chip">#${escapeHtml(it.order_number || '')}</span></td>
                    <td>${formatDate(it.filed_date)}</td>
                    <td>${formatTimeRange(it.time_from, it.time_to)}</td>
                    <td>${statusBadge(it.status)}</td>
                    <td>${filedByLabel}</td>
                    <td>${escapeHtml(it.remarks || '-')}</td>
                    <td>
                        ${canReview ? `
                            <button type="button"  class="btn btn-action btn-edit me-1 mb-1" onclick="reviewFiling(${it.id}, 'approve')" title="Approve"><i class="bi bi-check2"></i></button>
                            <button type="button"  class="btn btn-action btn-delete mb-1" onclick="reviewFiling(${it.id}, 'reject')" title="Reject"><i class="bi bi-x"></i></button>
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
            ? `{{ url('/hospital-orders/filings') }}/${id}/approve`
            : `{{ url('/hospital-orders/filings') }}/${id}/reject`;

        const actionLabel = action === 'approve' ? 'Approve' : 'Reject';
        const actionColor = action === 'approve' ? '#198754' : '#dc3545';

        Swal.fire({
            title: `${actionLabel} Filing`,
            input: 'text',
            inputLabel: 'Remarks (optional)',
            inputPlaceholder: 'Enter remarks',
            showCancelButton: true,
            confirmButtonText: actionLabel,
            confirmButtonColor: actionColor,
            showLoaderOnConfirm: true,
            preConfirm: (remarks) => {
                return fetch(endpoint, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({remarks: remarks || ''})
                })
                    .then(res => res.json())
                    .then(data => {
                        if (data.error || data.errors) throw data;
                        return data;
                    })
                    .catch(err => {
                        Swal.showValidationMessage(extractApiError(err, 'Failed to update status.'));
                    });
            },
            allowOutsideClick: () => !Swal.isLoading()
        }).then((result) => {
            if (!result.isConfirmed) return;

            const data = result.value || {};
            toastr.success(data.message || 'Updated.', 'Success');
            getFilingsForReview();
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
        toastr.error(extractApiError(errorPayload, fallbackMessage), 'Error');
    }

    function extractApiError(errorPayload, fallbackMessage) {
        if (errorPayload && errorPayload.errors) {
            const firstKey = Object.keys(errorPayload.errors)[0];
            return firstKey ? errorPayload.errors[firstKey][0] : fallbackMessage;
        }
        if (errorPayload && errorPayload.error) {
            return errorPayload.error;
        }
        return fallbackMessage;
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

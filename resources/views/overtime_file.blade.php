@extends('master')

@section('content')
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

        .ot-panel .form-control,
        .ot-panel .form-select {
            border-radius: 12px;
            border: 1px solid #d8e1ee;
            background: #fff;
        }

        .ot-label {
            font-size: .76rem;
            text-transform: uppercase;
            letter-spacing: .06em;
            font-weight: 700;
            color: #5e728d;
            margin-bottom: .32rem;
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
            max-width: 320px;
        }
    </style>

    <div class="ot-board">
        <div class="ot-head">
            <div class="ot-head-title">
                <h4>Overtime Requests</h4>
                <span>Submit your overtime date and time range for approval</span>
            </div>
        </div>

        <div class="ot-panel">
            <form id="fileOvertimeForm" class="row g-3 align-items-end">
                <div class="col-lg-2">
                    <label class="ot-label">Date</label>
                    <input type="date" class="form-control" name="ot_date" required>
                </div>
                <div class="col-lg-2">
                    <label class="ot-label">Time From</label>
                    <input type="time" class="form-control" name="time_from" required>
                </div>
                <div class="col-lg-2">
                    <label class="ot-label">Time To</label>
                    <input type="time" class="form-control" name="time_to" required>
                </div>
                <div class="col-lg-5">
                    <label class="ot-label">Reason (optional)</label>
                    <input type="text" class="form-control" name="reason" maxlength="500">
                </div>
                <div class="col-lg-1">
                    <button type="submit" class="btn btn-action btn-edit w-100">
                        <i class="bi bi-send"></i>
                        File
                    </button>
                </div>
            </form>
        </div>

        <div class="d-flex justify-content-between align-items-center mb-2 gap-2 flex-wrap">
            <h6 class="mb-0 fw-bold">My Overtime Filings</h6>
            <input type="text" id="filingSearch" class="form-control ot-search" placeholder="Search reason/status...">
        </div>

        <div class="ot-table-shell">
            <div class="table-responsive">
                <table class="table table-bordered table-sm align-middle">
                    <thead>
                    <tr>
                        <th>Date</th>
                        <th>Time Range</th>
                        <th>Reason</th>
                        <th>Status</th>
                        <th>Remarks</th>
                        <th>Action</th>
                    </tr>
                    </thead>
                    <tbody id="my_filing_list">
                    <tr><td colspan="6" class="ot-empty">Loading...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="d-flex justify-content-between align-items-center mt-3 flex-wrap gap-2">
            <small class="text-muted" id="filingSummary"></small>
            <nav>
                <ul class="pagination pagination-sm mb-0 ot-pagination" id="filingPagination"></ul>
            </nav>
        </div>
    </div>
@stop

@section('js')
<script>
    let allFilings = [];
    let filteredFilings = [];
    let currentPage = 1;
    const pageSize = 8;
    const filingDeleteBaseUrl = "{{ url('/overtime/filings') }}";

    $(document).ready(function () {
        getMyFilings();

        $('#filingSearch').on('input', function () {
            currentPage = 1;
            applyFilingFilter();
        });

        $('body').on('click', '.btn-delete-filing', function () {
            const filingId = Number($(this).data('id'));
            if (!filingId) return;
            deleteFiling(filingId);
        });

        $('#fileOvertimeForm').submit(function (e) {
            e.preventDefault();
            const formData = new FormData(this);

            fetch('{{ route("overtime.file") }}', {
                method: 'POST',
                headers: {'X-CSRF-TOKEN': '{{ csrf_token() }}'},
                body: formData
            })
                .then(res => res.json())
                .then(data => {
                    if (data.error || data.errors) throw data;
                    toastr.success(data.message || 'Filed.', 'Success');
                    this.reset();
                    getMyFilings();
                })
                .catch(err => showApiError(err, 'Failed to submit overtime filing.'));
        });
    });

    function getMyFilings() {
        fetch("{{ route('overtime.my-filings') }}")
            .then(res => res.json())
            .then(data => {
                allFilings = data || [];
                currentPage = 1;
                applyFilingFilter();
            })
            .catch(() => {
                $('#my_filing_list').html('<tr><td colspan="6" class="ot-empty text-danger">Failed to load.</td></tr>');
                $('#filingPagination').empty();
                $('#filingSummary').text('');
            });
    }

    function applyFilingFilter() {
        const keyword = ($('#filingSearch').val() || '').trim().toLowerCase();

        filteredFilings = allFilings.filter(it => {
            const reason = String(it.reason || '').toLowerCase();
            const status = String(it.status || '').toLowerCase();
            return reason.includes(keyword) || status.includes(keyword);
        });

        renderFilings();
        renderPagination();
    }

    function renderFilings() {
        const $list = $('#my_filing_list');
        $list.empty();

        if (!filteredFilings.length) {
            $list.html('<tr><td colspan="6" class="ot-empty">No filings found.</td></tr>');
            $('#filingSummary').text('0 results');
            return;
        }

        const total = filteredFilings.length;
        const totalPages = Math.ceil(total / pageSize);
        if (currentPage > totalPages) currentPage = totalPages;

        const startIndex = (currentPage - 1) * pageSize;
        const pageItems = filteredFilings.slice(startIndex, startIndex + pageSize);

        pageItems.forEach(it => {
            $list.append(`
                <tr>
                    <td>${formatDate(it.ot_date)}</td>
                    <td>${formatTimeRange(it.time_from, it.time_to)}</td>
                    <td>${escapeHtml(it.reason || '-')}</td>
                    <td>${statusBadge(it.status)}</td>
                    <td>${escapeHtml(it.remarks || '-')}</td>
                    <td>${deleteAction(it)}</td>
                </tr>
            `);
        });

        const from = startIndex + 1;
        const to = Math.min(startIndex + pageItems.length, total);
        $('#filingSummary').text(`Showing ${from}-${to} of ${total} result(s)`);
    }

    function renderPagination() {
        const $pagination = $('#filingPagination');
        $pagination.empty();

        const totalPages = Math.ceil(filteredFilings.length / pageSize);
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

            renderFilings();
            renderPagination();
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

    function deleteAction(filing) {
        const status = String((filing && filing.status) || '').toLowerCase();
        if (!['pending', 'rejected'].includes(status)) return '-';

        return `
            <button type="button"
                    class="btn btn-action btn-delete btn-delete-filing"
                    data-id="${Number(filing.id)}"
                    title="Delete">
                <i class="bi bi-trash"></i>
            </button>
        `;
    }

    function deleteFiling(id) {
        Swal.fire({
            icon: 'warning',
            title: 'Delete Filing?',
            text: 'This will permanently remove the selected overtime filing.',
            showCancelButton: true,
            confirmButtonText: 'Delete',
            confirmButtonColor: '#dc3545'
        }).then((result) => {
            if (!result.isConfirmed) return;

            fetch(`${filingDeleteBaseUrl}/${id}`, {
                method: 'DELETE',
                headers: {'X-CSRF-TOKEN': '{{ csrf_token() }}'}
            })
                .then(async (res) => {
                    const data = await res.json();
                    if (!res.ok) throw data;
                    return data;
                })
                .then((data) => {
                    toastr.success(data.message || 'Deleted.', 'Success');
                    getMyFilings();
                })
                .catch((err) => showApiError(err, 'Failed to delete filing.'));
        });
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

@extends('master')

@section('content')
    <style>
        .ho-file-page {
            --ho-surface: #ffffff;
            --ho-line: #dce6f2;
            --ho-muted: #5e728d;
            --ho-title: #10233d;
            --ho-grad-1: #0f766e;
            --ho-grad-2: #0ea5a4;
            --ho-grad-3: #0891b2;
        }

        .ho-file-shell {
            border: 0;
            border-radius: 20px;
            box-shadow: 0 24px 44px rgba(15, 23, 42, 0.10);
            background: linear-gradient(180deg, #f8fbff 0%, #ffffff 100%);
            overflow: hidden;
        }

        .ho-file-header {
            background: linear-gradient(125deg, var(--ho-grad-1), var(--ho-grad-2), var(--ho-grad-3));
            color: #fff;
            padding: 1.1rem 1.2rem;
        }

        .ho-file-title {
            margin: 0;
            font-weight: 800;
            letter-spacing: 0.2px;
        }

        .ho-file-subtitle {
            margin: .2rem 0 0;
            opacity: .95;
            font-weight: 500;
            font-size: .9rem;
        }

        .ho-file-card {
            border: 1px solid var(--ho-line);
            border-radius: 14px;
            background: var(--ho-surface);
            padding: .95rem;
        }

        .ho-file-label {
            font-size: .76rem;
            text-transform: uppercase;
            letter-spacing: .06em;
            font-weight: 700;
            color: var(--ho-muted);
            margin-bottom: .32rem;
        }

        .ho-file-search {
            max-width: 320px;
        }

        .ho-file-table-wrap {
            border: 1px solid var(--ho-line);
            border-radius: 14px;
            overflow: hidden;
            background: #fff;
        }

        .ho-file-table {
            margin-bottom: 0;
        }

        .ho-file-table thead th {
            background: #f5f9ff;
            color: #4f6684;
            text-transform: uppercase;
            letter-spacing: .06em;
            font-size: .72rem;
            font-weight: 800;
        }

        .ho-file-table tbody tr:hover {
            background: #f8fbff;
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
    </style>

    <div class="ho-file-page">
        <div class="card ho-file-shell">
            <div class="ho-file-header d-flex justify-content-between align-items-center gap-2">
                <div>
                    <h4 class="ho-file-title">File Hospital Order</h4>
                    <p class="ho-file-subtitle">Select a hospital order and submit using its assigned date/time details</p>
                </div>
                <a href="{{ route('hospital_order') }}" class="btn btn-light btn-sm">Back</a>
            </div>

            <div class="card-body p-3">
                <div class="ho-file-card mb-3">
                    <form id="fileHospitalOrderForm" class="row g-3 align-items-end">
                        <div class="col-lg-4">
                            <label class="ho-file-label">Hospital Order #</label>
                            <select class="form-select" name="ho_id" id="hoSelect" required></select>
                        </div>
                        <div class="col-lg-3">
                            <label class="ho-file-label">Date Range</label>
                            <input type="text" class="form-control" id="filedDateRangeDisplay" readonly>
                        </div>
                        <div class="col-lg-2">
                            <label class="ho-file-label">Time From</label>
                            <input type="time" class="form-control" id="fileTimeFromDisplay" readonly>
                        </div>
                        <div class="col-lg-2">
                            <label class="ho-file-label">Time To</label>
                            <input type="time" class="form-control" id="fileTimeToDisplay" readonly>
                        </div>
                        <div class="col-lg-1">
                            <button type="submit" class="btn btn-primary w-100">File</button>
                        </div>

                        <input type="hidden" name="filed_date" id="filedDateInput" required>
                        <input type="hidden" name="time_from" id="fileTimeFrom" required>
                        <input type="hidden" name="time_to" id="fileTimeTo" required>
                    </form>
                </div>

                <div class="d-flex justify-content-between align-items-center mb-2 gap-2 flex-wrap">
                    <h6 class="mb-0 fw-bold">My Filings</h6>
                    <input type="text" id="filingSearch" class="form-control ho-file-search" placeholder="Search HO #...">
                </div>

                <div class="ho-file-table-wrap">
                    <div class="table-responsive">
                        <table class="table table-bordered table-sm align-middle ho-file-table">
                            <thead>
                            <tr>
                                <th>Order #</th>
                                <th>Date</th>
                                <th>Time Range</th>
                                <th>Status</th>
                                <th>Remarks</th>
                                <th>Action</th>
                            </tr>
                            </thead>
                            <tbody id="my_filing_list">
                            <tr><td colspan="6" class="ho-empty">Loading...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="d-flex justify-content-between align-items-center mt-3 flex-wrap gap-2">
                    <small class="text-muted" id="filingSummary"></small>
                    <nav>
                        <ul class="pagination pagination-sm mb-0 ho-pagination" id="filingPagination"></ul>
                    </nav>
                </div>
            </div>
        </div>
    </div>
@stop

@section('js')
<script>
    let hospitalOrders = [];
    let allFilings = [];
    let filteredFilings = [];
    let currentPage = 1;
    const pageSize = 8;
    const filingDeleteBaseUrl = "{{ url('/hospital-orders/filings') }}";

    $(document).ready(function () {
        getHospitalOrders();
        getMyFilings();

        $('#hoSelect').on('change', function () {
            const selectedId = Number($(this).val());
            const selected = hospitalOrders.find(it => Number(it.id) === selectedId);
            if (!selected) return;

            $('#filedDateInput').val(selected.start_date || '');
            $('#filedDateRangeDisplay').val(`${formatDate(selected.start_date)} - ${formatDate(selected.end_date)}`);
            $('#fileTimeFrom').val(selected.time_from ? selected.time_from.substring(0, 5) : '');
            $('#fileTimeTo').val(selected.time_to ? selected.time_to.substring(0, 5) : '');
            $('#fileTimeFromDisplay').val(selected.time_from ? selected.time_from.substring(0, 5) : '');
            $('#fileTimeToDisplay').val(selected.time_to ? selected.time_to.substring(0, 5) : '');
        });

        $('#filingSearch').on('input', function () {
            currentPage = 1;
            applyFilingFilter();
        });

        $('body').on('click', '.btn-delete-filing', function () {
            const filingId = Number($(this).data('id'));
            if (!filingId) return;
            deleteFiling(filingId);
        });

        $('#fileHospitalOrderForm').submit(function (e) {
            e.preventDefault();

            const selectedId = $('#hoSelect').val();
            if (!selectedId) {
                toastr.warning('Please select a hospital order.', 'Validation');
                return;
            }

            const formData = new FormData(this);

            fetch('{{ route("hospital-orders.file") }}', {
                method: 'POST',
                headers: {'X-CSRF-TOKEN': '{{ csrf_token() }}'},
                body: formData
            })
                .then(res => res.json())
                .then(data => {
                    if (data.error || data.errors) throw data;
                    toastr.success(data.message || 'Filed.', 'Success');
                    this.reset();
                    $('#filedDateRangeDisplay').val('');
                    $('#fileTimeFromDisplay').val('');
                    $('#fileTimeToDisplay').val('');
                    getMyFilings();
                })
                .catch(err => showApiError(err, 'Failed to submit filing.'));
        });
    });

    function getHospitalOrders() {
        fetch("{{ route('hospital-orders.get') }}")
            .then(res => res.json())
            .then(data => {
                hospitalOrders = data || [];
                const $select = $('#hoSelect');
                $select.empty();

                if (!hospitalOrders.length) {
                    $select.append('<option value="">No hospital order available</option>');
                    return;
                }

                $select.append('<option value="" disabled selected>Select hospital order</option>');
                hospitalOrders.forEach(order => {
                    const label = `#${escapeHtml(order.order_number || '')} (${formatDate(order.start_date)} - ${formatDate(order.end_date)})`;
                    $select.append(`<option value="${order.id}">${label}</option>`);
                });
            });
    }

    function getMyFilings() {
        fetch("{{ route('hospital-orders.my-filings') }}")
            .then(res => res.json())
            .then(data => {
                allFilings = data || [];
                currentPage = 1;
                applyFilingFilter();
            })
            .catch(() => {
                $('#my_filing_list').html('<tr><td colspan="6" class="ho-empty text-danger">Failed to load.</td></tr>');
                $('#filingPagination').empty();
                $('#filingSummary').text('');
            });
    }

    function applyFilingFilter() {
        const keyword = ($('#filingSearch').val() || '').trim().toLowerCase();

        filteredFilings = allFilings.filter(it => {
            const orderNo = String(it.order_number || '').toLowerCase();
            return orderNo.includes(keyword);
        });

        renderFilings();
        renderPagination();
    }

    function renderFilings() {
        const $list = $('#my_filing_list');
        $list.empty();

        if (!filteredFilings.length) {
            $list.html('<tr><td colspan="6" class="ho-empty">No filings found.</td></tr>');
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
                    <td><span class="ho-chip">#${escapeHtml(it.order_number || '')}</span></td>
                    <td>${formatDate(it.filed_date)}</td>
                    <td>${formatTimeRange(it.time_from, it.time_to)}</td>
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
            text: 'This will permanently remove the selected filing.',
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

@extends('master')
@section('content')
    <div class="card shadow-sm border-0 rounded-3" style="background-color: #f8f9fa;">
        <div class="card-header bg-white border-bottom py-3">
            <h4 class="mb-0 fw-bold text-dark">✅ Pass Slip Approver Module</h4>
        </div>

        <div class="card-body bg-white">
            <div class="d-flex justify-content-end align-items-center gap-2">
                <input type="text" class="form-control w-auto" id="search_employee" placeholder="Search employee">
                <button class="btn btn-success" id="btn_search">
                    <i class="bi bi-search"></i>
                </button>
            </div>

            <div class="table-responsive mt-3">
                <table class="table table-bordered align-middle text-center">
                    <thead class="table-light">
                    <tr>
                        <th>Employee</th>
                        <th>Date</th>
                        <th>Time Out</th>
                        <th>Time In</th>
                        <th>Type</th>
                        <th>Actions</th>
                    </tr>
                    </thead>
                    <tbody id="pending_slip_list">
                    <tr>
                        <td colspan="6" class="text-muted text-center">Loading pending slips...</td>
                    </tr>
                    </tbody>
                </table>
                <div id="pagination_container" class="d-none">
                    <div class="d-flex flex-column align-items-center justify-content-center gap-2">
                        <div id="page_items_count"></div>
                        <nav aria-label="Pass slip pagination">
                            <ul class="pagination pagination-sm mb-0" id="pagination_links"></ul>
                        </nav>
                        <div id="page_indicator" class="small text-muted"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@stop

@section('js')
    <script src="https://cdn.jsdelivr.net/npm/moment@2.29.4/moment.min.js"></script>
    <script>
        let currentPage = 1;
        let lastPage = 1;

        $(document).ready(function () {
            loadPendingSlips(1);

            $('#btn_search').on('click', function () {
                currentPage = 1;
                loadPendingSlips(1);
            });

            $('#search_employee').on('keypress', function (e) {
                if (e.which === 13) {
                    currentPage = 1;
                    loadPendingSlips(1);
                }
            });

            $(document).on('click', '#pagination_links .page-link[data-page]', function (e) {
                e.preventDefault();
                const page = parseInt($(this).attr('data-page'), 10);
                if (!page || page < 1 || page > lastPage || page === currentPage) return;
                loadPendingSlips(page);
            });

            async function loadPendingSlips(page = 1) {
                const search = ($('#search_employee').val() || '').trim();
                const params = new URLSearchParams({
                    page: page,
                    search: search,
                    per_page: 10
                });
                const response = await fetch(`{{ route('pass-slip.approver.list') }}?${params.toString()}`);
                const payload = await response.json();
                const slips = payload.results || [];
                const pagination = payload.pagination || null;
                const table = $('#pending_slip_list');

                table.empty();
                $('#pagination_links').empty();
                $('#page_items_count').text('');
                $('#page_indicator').text('');
                $('#pagination_container').addClass('d-none');

                if (pagination) {
                    currentPage = pagination.current_page;
                    lastPage = pagination.last_page;
                }

                if (slips.length === 0) {
                    table.html(`<tr><td colspan="6" class="text-center text-muted">No pending pass slips.</td></tr>`);
                    return;
                }

                slips.forEach(slip => {
                    const formattedDate = moment(slip.slip_date, "YYYY-MM-DD").format("MMM D, YYYY");
                    const row = `
                <tr>
                    <td>${formatFullName(slip.user)}</td>
                    <td>${formattedDate}</td>
                    <td>${slip.time_out}</td>
                    <td>${slip.time_in ?? '-'}</td>
                    <td class="text-capitalize">${slip.type}</td>
                    <td>
                        <button class="btn btn-sm btn-success approve-btn" data-id="${slip.id}">Approve</button>
                        <button class="btn btn-sm btn-danger reject-btn" data-id="${slip.id}">Reject</button>
                    </td>
                </tr>
            `;
                    table.append(row);
                });

                if (pagination) {
                    $('#pagination_container').removeClass('d-none');
                    $('#page_items_count').text(`${pagination.from ?? 0} - ${pagination.to ?? 0} of ${pagination.total ?? 0}`);
                    $('#page_indicator').text(`Page ${pagination.current_page} of ${pagination.last_page}`);
                    renderPaginationLinks(pagination);
                }
            }

            // Approve
            $(document).on('click', '.approve-btn', async function () {
                const id = $(this).data('id');
                if (!confirm('Approve this pass slip?')) return;

                const response = await fetch(`{{ url('/pass-slip-approver/approve') }}/${id}`, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' }
                });

                const result = await response.json();
                alert(result.message);
                loadPendingSlips(currentPage);
            });

            // Reject
            $(document).on('click', '.reject-btn', async function () {
                const id = $(this).data('id');
                if (!confirm('Reject this pass slip?')) return;

                const response = await fetch(`{{ url('/pass-slip-approver/reject') }}/${id}`, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' }
                });

                const result = await response.json();
                alert(result.message);
                loadPendingSlips(currentPage);
            });
        });

        function formatFullName(user) {
            const parts = [
                user.fname ?? '',
                user.mname ? user.mname.charAt(0) + '.' : '',
                user.lname ?? '',
                user.suffix ?? ''
            ];
            return parts.filter(Boolean).join(' ').trim() || 'N/A';
        }

        function renderPaginationLinks(pagination) {
            const $links = $('#pagination_links');
            if (!$links.length || !pagination || !pagination.last_page) return;

            const current = pagination.current_page;
            const last = pagination.last_page;
            const start = Math.max(1, current - 2);
            const end = Math.min(last, current + 2);

            $links.empty();
            $links.append(`<li class="page-item${current <= 1 ? ' disabled' : ''}"><a class="page-link" href="#" data-page="${current - 1}">Prev</a></li>`);

            if (start > 1) {
                $links.append(`<li class="page-item"><a class="page-link" href="#" data-page="1">1</a></li>`);
                if (start > 2) {
                    $links.append('<li class="page-item disabled"><span class="page-link">...</span></li>');
                }
            }

            for (let i = start; i <= end; i++) {
                $links.append(`<li class="page-item${i === current ? ' active' : ''}"><a class="page-link" href="#" data-page="${i}">${i}</a></li>`);
            }

            if (end < last) {
                if (end < last - 1) {
                    $links.append('<li class="page-item disabled"><span class="page-link">...</span></li>');
                }
                $links.append(`<li class="page-item"><a class="page-link" href="#" data-page="${last}">${last}</a></li>`);
            }

            $links.append(`<li class="page-item${current >= last ? ' disabled' : ''}"><a class="page-link" href="#" data-page="${current + 1}">Next</a></li>`);
        }

    </script>
@stop

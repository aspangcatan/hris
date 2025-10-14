@extends('master')
@section('content')
    <div class="card shadow-sm border-0 rounded-3" style="background-color: #f8f9fa;">
        <div class="card-header bg-white border-bottom py-3">
            <h4 class="mb-0 fw-bold text-dark">✅ Pass Slip Approver Module</h4>
        </div>

        <div class="card-body bg-white">
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
            </div>
        </div>
    </div>
@stop

@section('js')
    <script src="https://cdn.jsdelivr.net/npm/moment@2.29.4/moment.min.js"></script>
    <script>
        $(document).ready(function () {
            loadPendingSlips();

            async function loadPendingSlips() {
                const response = await fetch('{{ route('pass-slip.approver.list') }}');
                const slips = await response.json();
                const table = $('#pending_slip_list');
                table.empty();

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
                loadPendingSlips();
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
                loadPendingSlips();
            });
        });

        function formatFullName(user) {
            if (!user) return 'N/A';
            const parts = [
                user.fname ?? '',
                user.mname ? user.mname.charAt(0) + '.' : '',
                user.lname ?? '',
                user.suffix ?? ''
            ];
            return parts.filter(Boolean).join(' ').trim();
        }

    </script>
@stop

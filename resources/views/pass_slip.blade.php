@extends('master')
@section('content')
    <div class="card shadow-sm border-0 rounded-3" style="background-color: #f8f9fa;">
        <div class="card-header bg-white border-bottom py-3">
            <h4 class="mb-0 fw-bold text-dark">📝 Pass Slips Management</h4>
        </div>
        <div class="card-body bg-white">
            <div class="row g-4 mt-2">
                <!-- Create Pass Slip -->
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header bg-light fw-bold">Create Pass Slip</div>
                        <div class="card-body mt-3">
                            <div class="form-floating mb-3">
                                <input type="date" id="date" class="form-control form-control-sm">
                                <label for="date">Date</label>
                            </div>

                            <div class="row g-2 mb-3">
                                <div class="col-md-6">
                                    <div class="form-floating">
                                        <input type="time" id="time_out" class="form-control form-control-sm">
                                        <label for="time_out">Time Out</label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-floating">
                                        <input type="time" id="time_in" class="form-control form-control-sm">
                                        <label for="time_in">Time In</label>
                                    </div>
                                </div>
                            </div>

                            <div class="form-floating mb-3">
                                <select id="type" class="form-select form-control-sm">
                                    <option value="" selected disabled>Select Type</option>
                                    <option value="Personal">Personal</option>
                                    <option value="Official">Official</option>
                                </select>
                                <label for="type">Type</label>
                            </div>

                            <div class="d-flex justify-content-between mt-4">
                                <button type="button" class="btn btn-secondary w-50 me-2" id="clearBtn">Clear</button>
                                <button type="button" class="btn btn-primary w-50" id="submitBtn">Submit</button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Pass Slip List -->
                <div class="col-md-8">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header bg-light fw-bold">Pass Slip List</div>
                        <div class="card-body mt-3">
                            <div class="table-responsive">
                                <table class="table table-bordered align-middle text-center">
                                    <thead class="table-light">
                                    <tr>
                                        <th>Date</th>
                                        <th>Time Out</th>
                                        <th>Time In</th>
                                        <th>Type</th>
                                        <th>Status</th>
                                        <th style="width: 4%"></th>
                                    </tr>
                                    </thead>
                                    <tbody id="pass_slip_list">
                                    <tr>
                                        <td colspan="5" class="text-center text-muted">Loading...</td>
                                    </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@stop

@section('js')
    <script>
        $(document).ready(function () {

            const $date = $('#date');
            const $timeOut = $('#time_out');
            const $timeIn = $('#time_in');
            const $type = $('#type');
            const $list = $('#pass_slip_list');

            // Load data when page loads
            loadPassSlips();

            // Clear inputs
            $('#clearBtn').on('click', function () {
                $date.val('');
                $timeOut.val('');
                $timeIn.val('');
                $type.prop('selectedIndex', 0);
            });

            // Submit new pass slip
            $('#submitBtn').on('click', async function () {
                const data = {
                    slip_date: $date.val(),
                    time_out: $timeOut.val(),
                    time_in: $timeIn.val(),
                    type: $type.val()
                };

                if (!data.slip_date || !data.time_out || !data.type) {
                    alert('⚠️ Please fill in required fields.');
                    return;
                }

                try {
                    $(this).prop('disabled', true).text('Submitting...');

                    const response = await fetch('{{ route('pass-slip.store') }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        },
                        body: JSON.stringify(data)
                    });

                    const result = await response.json();
                    alert(result.message);
                    $('#clearBtn').click();
                    await loadPassSlips();

                } catch (error) {
                    console.error(error);
                    alert('❌ Something went wrong while submitting your pass slip.');
                } finally {
                    $(this).prop('disabled', false).text('Submit');
                }
            });

            // Load Pass Slips
            async function loadPassSlips() {
                $list.html(`<tr><td colspan="5" class="text-center text-muted">Loading...</td></tr>`);
                try {
                    const response = await fetch('{{ route('pass-slip.list') }}');
                    const slips = await response.json();
                    renderPassSlipList(slips);
                } catch (error) {
                    console.error(error);
                    $list.html(`<tr><td colspan="5" class="text-center text-danger">Error loading data.</td></tr>`);
                }
            }

            // Render table using jQuery
            function renderPassSlipList(slips) {
                $list.empty();

                if (slips.length === 0) {
                    $list.html(`<tr><td colspan="6" class="text-center text-muted">No pass slips found.</td></tr>`);
                    return;
                }

                $.each(slips, function (_, slip) {
                    const badgeColor = getStatusColor(slip.status);
                    const deleteButton = slip.status === 'pending'
                        ? `<button class="btn btn-action btn-delete delete-btn" data-id="${slip.id}" title="Delete"><i class="bi bi-trash"></i></button>`
                        : '';

                    const tr = `
            <tr>
                <td>${moment(slip.slip_date, "YYYY-MM-DD").format("MMM D, YYYY")}</td>
                <td>${moment(slip.time_out, "HH:mm:ss").format("hh:mm A")}</td>
                <td>${slip.time_in ? moment(slip.time_in, "HH:mm:ss").format("hh:mm A") : '-'}</td>
                <td class="text-capitalize">${slip.type}</td>
                <td><span class="badge bg-${badgeColor} text-uppercase">${slip.status}</span></td>
                <td>${deleteButton}</td>
            </tr>
        `;
                    $list.append(tr);
                });
            }


            $(document).on('click', '.delete-btn', async function () {
                const id = $(this).data('id');
                if (!confirm('Are you sure you want to delete this pass slip?')) return;

                const response = await fetch(`{{ url('/pass-slip') }}/${id}`, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    }
                });

                const result = await response.json();
                alert(result.message);
                loadPassSlips();
            });


            function getStatusColor(status) {
                switch (status) {
                    case 'approved': return 'success';
                    case 'rejected': return 'danger';
                    default: return 'secondary';
                }
            }
        });
    </script>
@stop

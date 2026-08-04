@extends('master')
@section('content')
    @php
        $canFileOnBehalf = (bool) session('is_section_head') || (bool) session('is_division_head');
    @endphp
    <style>
        .ps-board {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 20px;
            padding: 1.5rem;
            box-shadow: 0 18px 38px rgba(15, 23, 42, 0.08);
        }

        .ps-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            margin-bottom: 1.25rem;
            flex-wrap: wrap;
        }

        .ps-head-title h4 {
            margin: 0;
            font-weight: 700;
            color: #0f172a;
        }

        .ps-head-title span {
            font-size: 0.9rem;
            color: #64748b;
        }

        .ps-panel {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 1rem;
        }

        .ps-panel .form-control,
        .ps-panel .form-select {
            border-radius: 12px;
            border: 1px solid #d8e1ee;
            background: #fff;
        }

        .ps-section-title {
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 0.6rem;
        }

        .ps-table-shell {
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            overflow: hidden;
            background: #fff;
        }

        .ps-table-shell table {
            margin-bottom: 0;
        }

        .ps-table-shell table thead th {
            background: #f8fafc;
            color: #0f172a;
            font-size: 0.72rem;
            text-transform: uppercase;
            letter-spacing: 0.08em;
        }

        .ps-table-shell table tbody td {
            color: #1e293b;
            font-weight: 500;
            vertical-align: middle;
        }
    </style>

    <div class="ps-board">
        <div class="ps-head">
            <div class="ps-head-title">
                <h4>Pass Slips</h4>
                <span>File and manage your pass slip requests</span>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-lg-4">
                <div class="ps-panel h-100">
                    <div class="ps-section-title">Create Pass Slip</div>
                    @if($canFileOnBehalf)
                        <div class="form-floating mb-3">
                            <select id="target_user_id" class="form-select">
                                <option value="">Myself</option>
                            </select>
                            <label for="target_user_id">File For</label>
                        </div>
                    @endif
                    <div class="form-floating mb-3">
                        <input type="date" id="date" class="form-control">
                        <label for="date">Date</label>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <div class="form-floating">
                                <input type="time" id="time_out" class="form-control">
                                <label for="time_out">Time Out</label>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="form-floating">
                                <input type="time" id="time_in" class="form-control">
                                <label for="time_in">Time In</label>
                            </div>
                        </div>
                    </div>

                    <div class="form-floating mb-3">
                        <select id="type" class="form-select">
                            <option value="" selected disabled>Select Type</option>
                            <option value="Personal">Personal</option>
                            <option value="Official">Official</option>
                        </select>
                        <label for="type">Type</label>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-action btn-view w-50" id="clearBtn">
                            <i class="bi bi-eraser"></i>
                            Clear
                        </button>
                        <button type="button" class="btn btn-action btn-edit w-50" id="submitBtn">
                            <i class="bi bi-check2"></i>
                            Submit
                        </button>
                    </div>
                </div>
            </div>

            <div class="col-lg-8">
                <div class="ps-panel h-100">
                    <div class="ps-section-title">Pass Slip List</div>
                    <div class="ps-table-shell">
                        <div class="table-responsive">
                            <table class="table table-bordered align-middle text-center">
                                <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Time Out</th>
                                    <th>Time In</th>
                                    <th>Type</th>
                                    <th>Status</th>
                                    <th>Filed By</th>
                                    <th style="width: 4%"></th>
                                </tr>
                                </thead>
                                <tbody id="pass_slip_list">
                                <tr>
                                    <td colspan="7" class="text-center text-muted">Loading...</td>
                                </tr>
                                </tbody>
                            </table>
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
            const $target = $('#target_user_id');

            if ($target.length) {
                $target.select2({
                    placeholder: 'Myself',
                    allowClear: true,
                    width: '100%'
                });

                fetch('{{ route('managed-employees.index') }}')
                    .then(res => res.json())
                    .then(data => {
                        (data || []).forEach(emp => {
                            $target.append(new Option(emp.full_name, emp.id, false, false));
                        });
                    });
            }

            // Load data when page loads
            loadPassSlips();

            // Clear inputs
            $('#clearBtn').on('click', function () {
                $date.val('');
                $timeOut.val('');
                $timeIn.val('');
                $type.prop('selectedIndex', 0);
                if ($target.length) {
                    $target.val('').trigger('change');
                }
            });

            // Submit new pass slip
            $('#submitBtn').on('click', async function () {
                const data = {
                    slip_date: $date.val(),
                    time_out: $timeOut.val(),
                    time_in: $timeIn.val(),
                    type: $type.val()
                };

                if ($target.length && $target.val()) {
                    data.target_user_id = $target.val();
                }

                if (!data.slip_date || !data.time_out || !data.type) {
                    alert('Please fill in required fields.');
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
                    alert('Something went wrong while submitting your pass slip.');
                } finally {
                    $(this).prop('disabled', false).text('Submit');
                }
            });

            // Load Pass Slips
            async function loadPassSlips() {
                $list.html(`<tr><td colspan="7" class="text-center text-muted">Loading...</td></tr>`);
                try {
                    const response = await fetch('{{ route('pass-slip.list') }}');
                    const slips = await response.json();
                    renderPassSlipList(slips);
                } catch (error) {
                    console.error(error);
                    $list.html(`<tr><td colspan="7" class="text-center text-danger">Error loading data.</td></tr>`);
                }
            }

            // Render table using jQuery
            function renderPassSlipList(slips) {
                $list.empty();

                if (slips.length === 0) {
                    $list.html(`<tr><td colspan="7" class="text-center text-muted">No pass slips found.</td></tr>`);
                    return;
                }

                $.each(slips, function (_, slip) {
                    const badgeColor = getStatusColor(slip.status);
                    const deleteButton = slip.status === 'pending'
                        ? `<button class="btn btn-action btn-delete delete-btn" data-id="${slip.id}" title="Delete"><i class="bi bi-trash"></i></button>`
                        : '';
                    const filedByLabel = slip.filedBy
                        ? `<span class="badge bg-info text-dark">Filed by ${escapeHtml(slip.filedBy.fname)} ${escapeHtml(slip.filedBy.lname)}</span>`
                        : '-';

                    const tr = `
            <tr>
                <td>${moment(slip.slip_date, "YYYY-MM-DD").format("MMM D, YYYY")}</td>
                <td>${moment(slip.time_out, "HH:mm:ss").format("hh:mm A")}</td>
                <td>${slip.time_in ? moment(slip.time_in, "HH:mm:ss").format("hh:mm A") : '-'}</td>
                <td class="text-capitalize">${slip.type}</td>
                <td><span class="badge bg-${badgeColor} text-uppercase">${slip.status}</span></td>
                <td>${filedByLabel}</td>
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

            function escapeHtml(value) {
                return String(value)
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#039;');
            }
        });
    </script>
@stop

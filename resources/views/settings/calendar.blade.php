@extends('master')

@section('content')
    <style>
    .cal-board{background:#fff;border:1px solid #e2e8f0;border-radius:20px;padding:1.5rem;box-shadow:0 18px 38px rgba(15,23,42,.08)}
    .cal-head{margin-bottom:1.25rem}
    .cal-head h4{margin:0;font-weight:700;color:#0f172a}
    .cal-head span{font-size:.9rem;color:#64748b}
    </style>
    <div class="cal-board">
        <div class="cal-head">
            <h4>Holiday Calendar</h4>
            <span>Manage regular and special holidays. Click a date to add or edit.</span>
        </div>
        <div id="calendar"></div>

            <!-- Modal for Adding/Updating Holiday -->
            <div class="modal fade premium-modal" id="holidayModal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header">
                            <div class="modal-hero">
                                <i class="bi bi-calendar-heart text-white" style="font-size:2.2rem"></i>
                            </div>
                        </div>
                        <div class="modal-body">
                            <div class="modal-title-text" id="holidayModalTitle">Add Holiday</div>
                            <form id="holidayForm">
                                @csrf
                                <div class="mb-3">
                                    <label for="holidayDate" class="form-label fw-semibold">Date</label>
                                    <input type="date" class="form-control" id="holidayDate" name="date" required readonly>
                                </div>
                                <div class="mb-3">
                                    <label for="type" class="form-label fw-semibold">Type</label>
                                    <select class="form-select" id="type" name="type" required>
                                        <option value="Regular">Regular</option>
                                        <option value="Special">Special</option>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label for="description" class="form-label fw-semibold">Description</label>
                                    <textarea rows="3" class="form-control" id="description" name="description" required></textarea>
                                </div>
                                <div class="d-flex justify-content-end gap-2 mt-3">
                                    {{-- Destructive action sits on the left, away from Cancel / Update. --}}
                                    <button type="button" class="btn btn-danger me-auto" id="deleteBtn" style="display:none">
                                        <i class="bi bi-trash me-1"></i>Delete
                                    </button>
                                    <button type="button" class="btn btn-secondary btn-modal-close" data-bs-dismiss="modal">Cancel</button>
                                    <button type="button" class="btn btn-success btn-modal-save" id="updateBtn" style="display:none">Update</button>
                                    <button type="submit" class="btn btn-success btn-modal-save" id="saveBtn">Save</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

    </div>

    <!-- FullCalendar & Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/locales-all.min.js"></script>
    <!-- Add jQuery CDN -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    <script>
        $(document).ready(function() {
            var calendarEl = $('#calendar')[0];
            var holidayDateInput = $('#holidayDate');
            var holidayModal = new bootstrap.Modal($('#holidayModal')[0]);

            var calendar = new FullCalendar.Calendar(calendarEl, {
                initialView: 'dayGridMonth',
                selectable: true,
                events: '{{ route('calendar.events') }}', // Use route helper for fetching events
                eventClick: function(info) {
                    // Display holiday info in modal for update
                    holidayDateInput.val(info.event.startStr);
                    $('#type').val(info.event.extendedProps.type);
                    $('#description').val(info.event.extendedProps.description);

                    // Toggle buttons for update and delete
                    $('#saveBtn').hide();
                    $('#updateBtn').show();
                    $('#deleteBtn').show();

                    holidayModal.show();
                },
                dateClick: function(info) {
                    // Set the clicked date in the input field for adding holiday
                    holidayDateInput.val(info.dateStr);

                    // Toggle buttons for add
                    $('#saveBtn').show();
                    $('#updateBtn').hide();
                    $('#deleteBtn').hide();

                    holidayModal.show();
                }
            });

            calendar.render();

            // Save holiday
            $('#holidayForm').submit(async function (e) {
                e.preventDefault();
                var formData = new FormData(this);

                var response = await fetch('{{ route('calendar.store') }}', {  // Use route helper for storing holiday
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-CSRF-TOKEN': $('input[name="_token"]').val()
                    }
                });

                if (response.ok) {
                    holidayModal.hide();
                    calendar.refetchEvents();
                } else {
                    alert('Failed to save holiday');
                }
            });

            // Update holiday
            $('#updateBtn').click(async function () {
                var formData = new FormData($('#holidayForm')[0]);

                var response = await fetch('{{ route('calendar.update') }}', {  // Use route helper for updating holiday
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-CSRF-TOKEN': $('input[name="_token"]').val()
                    }
                });

                if (response.ok) {
                    holidayModal.hide();
                    calendar.refetchEvents();
                } else {
                    alert('Failed to update holiday');
                }
            });

            // Delete holiday
            $('#deleteBtn').click(async function () {
                const ok = await Swal.fire({
                    icon: 'warning',
                    title: 'Delete this holiday?',
                    text: ($('#description').val() || 'This holiday') + ' will be removed from the calendar.',
                    showCancelButton: true,
                    confirmButtonText: 'Delete',
                    cancelButtonText: 'Cancel',
                });
                if (!ok.isConfirmed) return;

                var formData = new FormData($('#holidayForm')[0]);

                var response = await fetch('{{ route('calendar.delete') }}', {  // Use route helper for deleting holiday
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-CSRF-TOKEN': $('input[name="_token"]').val()
                    }
                });

                if (response.ok) {
                    holidayModal.hide();
                    calendar.refetchEvents();
                } else {
                    alert('Failed to delete holiday');
                }
            });
        });
    </script>

    <style>
        .fc-tooltip {
            max-width: 150px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            display: inline-block;
            vertical-align: middle;
        }
    </style>
@endsection

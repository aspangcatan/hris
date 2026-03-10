@extends('master')

@section('content')
    <div class="card mt-2">
        <div class="card-body">
            <div class="container mt-4">
                <h2 class="text-center">Holiday Calendar</h2>
                <div id="calendar"></div>
            </div>

            <!-- Modal for Adding/Updating Holiday -->
            <div class="modal fade" id="holidayModal" tabindex="-1" aria-labelledby="holidayModalLabel" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="holidayModalLabel">Add Holiday</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <form id="holidayForm">
                                @csrf
                                <div class="mb-3">
                                    <label for="holidayDate" class="form-label">Date</label>
                                    <input type="date" class="form-control" id="holidayDate" name="date" required readonly>
                                </div>
                                <div class="mb-3">
                                    <label for="type" class="form-label">Type</label>
                                    <select class="form-select" id="type" name="type" required>
                                        <option value="Regular">Regular</option>
                                        <option value="Special">Special</option>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label for="description" class="form-label">Description</label>
                                    <textarea rows="3" class="form-control" id="description" name="description" required></textarea>
                                </div>
                                <div class="d-flex justify-content-end">
                                    <button type="submit" class="btn btn-primary me-1" id="saveBtn">Save</button>
                                    <button type="button" class="btn btn-success ms-1 me-1" id="updateBtn">Update</button>
                                    <button type="button"  class="btn btn-action btn-delete ms-1" id="deleteBtn" title="Delete"><i class="bi bi-trash"></i></button>
                                </div>
                            </form>
                        </div>
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

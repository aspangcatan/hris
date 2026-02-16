@extends('master')
@section('content')
    <div class="d-flex justify-content-end mb-2">
        @if(session('access_rights') == 'admin' || session('access_rights') == 'DH')
            <div class="ms-2 me-1">
                <select class="form-control" id="search_division">
                    <option value="">Search division</option>
                </select>
            </div>
            <div>
                <select class="form-control d-none" id="search_section">
                </select>
            </div>
        @endif
        <div class="ms-2 me-1">
            <input class="form-control" placeholder="Search by firstname" id="search_fname"/>
        </div>
        <div class="ms-2 me-1">
            <input type="text" class="form-control" placeholder="Search by lastname" id="search_lname"/>
        </div>
    </div>

    <div id="loading-message" class="text-center my-4" style="display: none;">
        <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Loading...</span>
        </div>
        <p class="mt-2">Loading, please wait while we fetch the data...</p>
    </div>

    <div class="row mt-5" id="employee-container">
        <!-- DTR cards will be appended here -->
    </div>

    @include('modals.employee_dtr')
@stop
@section('js')
    <script>
        $(document).ready(function () {
            getEmployeeDtr();
            loadDivision();

            $("#search_division").change(function () {
                const division = $("#search_division").val();
                fetch("{{ route('getSectionList') }}?division=" + division, {
                    method: "GET"
                })
                    .then(response => response.json())
                    .then(data => {
                        $("#search_section").empty();
                        if (division == 0) {
                            $("#search_section").addClass("d-none");
                        } else {
                            $("#search_section").removeClass("d-none");
                        }

                        data.forEach(it => {
                            const option = `<option value="` + it.id + `">` + it.description + `</option>`;
                            $("#search_section").append(option);
                        });
                    }).catch(err => {
                    alert("Ooops, something went wrong. Please reload");
                });
            });

            $("#search_section").change(function () {
                getEmployeeDtr();
            });

            $("#search_fname, #search_lname").on("keypress", function (e) {
                if (e.which === 13) { // 13 is the Enter key code
                    getEmployeeDtr(); // Call your fetch function
                }
            });
        });

        function loadDivision() {
            const division = @json(Auth::user()->division);
            const access = @json(session('access_rights'));

            fetch("{{ route('getDivisionList') }}", {
                method: "GET"
            })
                .then(response => response.json())
                .then(data => {
                    data.forEach(it => {
                        if (access === "DH" && division != it.id) {
                            return; // Skip non-matching divisions for DH
                        }

                        const option = `<option value="${it.id}">${it.description}</option>`;
                        $("#search_division").append(option);
                    });
                })
                .catch(err => {
                    alert("Ooops, something went wrong. Please reload");
                });
        }

        async function getEmployeeDtr() {
            const search_division = ($("#search_division").val() === undefined) ? "" : $("#search_division").val();
            let search_section = $("#search_section").val();
            if (search_division == "")
                search_section = "";

            const search_fname = $("#search_fname").val();
            const search_lname = $("#search_lname").val();

            const container = $("#employee-container");
            const loading = $("#loading-message");

            container.empty();         // Clear old results
            loading.show();            // Show loading spinner

            fetch("{{ route('getEmployeeDtr') }}?search_section=" + search_section +
                "&search_division=" + search_division +
                "&search_fname=" + search_fname +
                "&search_lname=" + search_lname, {
                method: "GET"
            })
                .then(response => response.json())
                .then(data => {
                    const path = '{{ url('/avatar/')}}';

                    data.forEach(it => {
                        const image = (it.picture) ? "https://dohcsmc.com/id/storage/crop/"+it.picture : "assets/img/logo.png";
                        const timeIn = it.time_in ? new Date(it.time_in).toLocaleTimeString([], {
                            hour: '2-digit',
                            minute: '2-digit'
                        }) : "N/A";
                        const timeOut = it.time_out ? new Date(it.time_out).toLocaleTimeString([], {
                            hour: '2-digit',
                            minute: '2-digit'
                        }) : "N/A";

                        const employeeCard = `
                <div class="col-md-3">
                    <div class="card custom-card">
                        <div class="card-header custom-card-header d-flex justify-content-between align-items-center">
                            <div class="d-flex align-items-center">
                                <img src="${image}" class="rounded-circle custom-avatar">
                                <div class="ms-3">
                                    <p class="font-weight-bold mb-0 custom-name" style="font-size: 18px;">
                                        ${it.fname} ${it.mname ? it.mname[0] + '.' : ''} ${it.lname}
                                    </p>
                                    <p class="text-muted custom-designation" style="font-size: 14px; font-weight: 300;">
                                        ${it.designation}
                                    </p>
                                </div>
                            </div>
                        </div>
                        <div class="card-body custom-card-body">
                            <div class="d-flex justify-content-end mt-3">
                                <button class="btn btn-sm btn-primary ms-1" onclick="viewDtr(${it.id})">
                                    <i class="fas fa-eye mr-1"></i>
                                    VIEW DTR
                                </button>
                            </div>
                        </div>
                    </div>
                </div>`;
                        container.append(employeeCard);
                    });

                    loading.hide(); // Hide loading spinner after rendering
                })
                .catch(error => {
                    console.log("Error fetching employee DTR:", error);
                    loading.hide(); // Hide even on error
                });
        }
    </script>
@stop

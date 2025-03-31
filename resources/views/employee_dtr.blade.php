@extends('master')
@section('content')

    <div class="d-flex justify-content-end mb-2">
        <div>
            <input class="form-control" placeholder="Search by firstname" id="search_fname"/>
        </div>
        <div class="ms-2 me-1">
            <input type="text" class="form-control" placeholder="Search by lastname" id="search_lname"/>
        </div>
        <div class="ms-1">
            <button class="btn btn-success" id="btn_search">
                <i class="bi bi-search"></i>
            </button>
        </div>
    </div>
    <div class="row">
        <div class="col-md-3">
            <div class="card custom-card">
                <div
                    class="card-header custom-card-header d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center">
                        <img src="https://dohcsmc.com/paperless_prescription/avatar/1509.png" class="rounded-circle custom-avatar">
                        <div class="ms-3">
                            <p class="font-weight-bold mb-0 custom-name" style="font-size: 18px;">
                                ASNAUI O. PANGCATAN
                            </p>
                            <p class="text-muted custom-designation"
                               style="font-size: 14px; font-weight: 300;">
                                CMT II
                            </p>
                        </div>
                    </div>
                </div>
                <div class="card-body custom-card-body">
                    <div class="d-flex align-items-center mt-3">
                        <div class="font-weight-bold custom-label">Time In:</div>
                        <div class="ms-2 custom-value">09:42 AM</div>
                    </div>
                    <div class="d-flex align-items-center">
                        <div class="font-weight-bold custom-label">Time Out:</div>
                        <div class="ms-2 custom-value text-warning"></div>
                    </div>
                    <div class="d-flex justify-content-end mt-3">
                        <button class="btn btn-sm btn-primary">
                            <i class="fas fa-eye mr-1"></i>
                            VIEW DTR
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

@stop

@section('js')
    <script>
        $(document).ready(function () {
            $("#month,#year").change(function () {
                getDtrLogs();
            });
        });

        function printDTR(){
            const month = $("#month").val();
            const year = $("#year").val();

            window.open(
                '{{ route('printDTR') }}?month=' + month + "&year=" + year,
                '_blank' // <- This is what makes it open in a new window.
            );
        }

        async function getDtrLogs() {
            const month = $("#month").val();
            const year = $("#year").val();

            if (month == "" || year == "") return;

            fetch('{{ route('getDTRLogs') }}?month=' + month + "&year=" + year, {
                method: "GET"
            })
                .then(res => res.json())
                .then(data => {
                    $("#dtr_list").empty();
                    $("#raw_list").empty();
                    if (data.code == 500) {
                        alert("Something went wrong, please reload the page");
                        return;
                    }

                    data.results.forEach(it => {
                        const date_timein = (it.date_timein) ? moment(it.date_timein).format("hh:mm A") : "";
                        const date_timeout = (it.date_timeout) ? moment(it.date_timeout).format("hh:mm A") : "";

                        let tr = `<tr>`;
                        tr += `<td>` + it.day + `</td>`;
                        tr += `<td>` + date_timein + `</td>`;
                        tr += `<td>` + date_timeout + `</td>`;
                        tr += "</tr>";
                        $("#dtr_list").append(tr);
                    });

                    if(data.raw_data.length < 1){
                        $("#raw_list").append("<tr><td class='text-center' colspan='3'>No record found</td></tr>");
                        return;
                    }

                    data.raw_data.forEach(it => {
                        const date = moment(it.created_at).format("MM/DD/YYYY");
                        const time = moment(it.created_at).format("hh:mm:ss A");

                        const imageUrl = "/hris/storage/" + it.photo_path; // Assuming 'photo_path' is the column where the image file path is stored

                        let tr = `<tr>`;
                        tr += `<td>` + date + `</td>`;
                        tr += `<td>` + it.status + `</td>`;
                        tr += `<td><a href="${imageUrl}" target="_blank">${time}</a></td>`;
                        tr += "</tr>";
                        $("#raw_list").append(tr);
                    });

                }).catch(err => {
                console.log(err);
            });
        }
    </script>
@stop

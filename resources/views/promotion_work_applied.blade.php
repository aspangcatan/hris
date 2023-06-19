@extends('master')
@section('content')
    <div class="card">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center">
                <h5 class="card-title">Positions Applied</h5>
                <div>
                    <button class="btn btn-sm btn-success" id="btn_add_position">ADD NEW</button>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table text-center">
                    <thead>
                    <tr>
                        <th>Position Applied</th>
                        <th>Date Applied</th>
                        <th style="width: 4%"></th>
                    </tr>
                    </thead>
                    <tbody id="work_list"></tbody>
                </table>
            </div>
        </div>
        <div class="card-footer text-dark" id="pagination_container">
            <div class="d-flex align-items-center justify-content-center">
                <button class="btn btn-sm d-none" style="background: transparent !important;" id="btn_prev">
                    <i class="bi bi-caret-left"></i>
                </button>
                <div id="page_items_count"></div>
                <button class="btn btn-sm" style="background: transparent !important;" id="btn_next">
                    <i class="bi bi-caret-right"></i>
                </button>
            </div>
        </div>
    </div>

    @include('modals.promotion_work_applied')
@stop
@section('js')
    <script>
        const promotion_applied_modal = new bootstrap.Modal(document.getElementById('promotion_applied_modal'));
        const edit_promotion_modal = new bootstrap.Modal(document.getElementById('edit_promotion_modal'));

        let position_applied_id = 0;
        let related_work_id = 0;
        $(document).ready(function () {
            getPromotionWork();

            let $selectize_accomplishment = $("#work_accomplishment").selectize({
                maxItems: 99,
                valueField: "description",
                labelField: "description",
                searchField: "description",
                create: true,
                persist: false,
            });

            let $selectize_duties = $("#work_duties").selectize({
                maxItems: 99,
                valueField: "description",
                labelField: "description",
                searchField: "description",
                create: true,
                persist: false,
            });

            let selectize_accomplishment = $selectize_accomplishment[0].selectize;
            let selectize_duties = $selectize_duties[0].selectize;

            $("#btn_print_work").click(function () {
                window.open(
                    '{{ route('printWorkExperience') }}?work_id=' + position_applied_id,
                    '_blank' // <- This is what makes it open in a new window.
                );
            });

            $("#btn_add_position").click(function () {
                position_applied_id = 0
                $("#position_applied").val("");
                $("#date_applied").val("");
                $("#related_work_list").empty();
                $("#work_experience_container").addClass("d-none");
                $("#btn_print_work").addClass("d-none");
                promotion_applied_modal.toggle();
            });

            $("#btn_confirm_save").click(function () {
                let is_valid = true;
                const position_applied = $("#position_applied").val().trim().toUpperCase();
                const date_applied = $("#date_applied").val();

                if (position_applied == "") {
                    is_valid = false;
                    $("#position_applied").addClass("is-invalid");
                    toastr.error("Please indicate in position applied", "Ooops");
                }

                if (date_applied == "") {
                    is_valid = false;
                    $("#date_applied").addClass("is-invalid");
                    toastr.error("Please indicate in date applied", "Ooops");
                }

                if (!is_valid) return;

                const params = {
                    id: position_applied_id,
                    position_applied: position_applied,
                    date_applied: date_applied
                }

                fetch('{{ route('insertPositionApplied') }}', {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json",
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    body: JSON.stringify(params)
                })
                    .then(res => res.json())
                    .then(data => {
                        promotion_applied_modal.toggle();
                        if (data.code == 500) {
                            toastr.error(data.message, "Ooops");
                            return;
                        }

                        getPromotionWork();
                        toastr.success(data.message, "Information");
                    }).catch(err => {
                    console.log(err);
                });

            });

            $("body").on("click", ".btn-edit", function () {
                $("#work_experience_container").removeClass("d-none");
                edit_promotion_modal.toggle();
                related_work_id = $(this).attr("data-value");

                const date_from = $("#duration_id_" + related_work_id).text().split("-")[0].trim();
                const date_to = $("#duration_id_" + related_work_id).text().split("-")[1].trim();

                const position = $("#position_id_" + related_work_id).text().trim();
                const office = $("#office_id_" + related_work_id).text().trim();
                const supervisor = $("#supervisor_id_" + related_work_id).text().trim();
                const agency = $("#agency_id_" + related_work_id).text().trim();


                const accomplishment_array = [];
                const duties_array = [];
                $("#accomplishment_id_" + related_work_id + " li").each(function () {
                    selectize_accomplishment.addOption({'description': $(this).text()});
                    accomplishment_array.push($(this).text());
                });

                $("#duties_id_" + related_work_id + " li").each(function () {
                    selectize_duties.addOption({'description': $(this).text()});
                    duties_array.push($(this).text());
                });

                selectize_accomplishment.setValue(accomplishment_array);
                selectize_duties.setValue(duties_array);


                $("#work_from").val(moment(date_from, "MMMM D, YYYY").format("YYYY-MM-DD"));
                if (date_to == "Present") {
                    $("#work_to").val("");
                    $("#work_checkbox").prop("checked", true);
                } else {
                    $("#work_to").val(moment(date_to, "MMMM D, YYYY").format("YYYY-MM-DD"));
                    $("#work_checkbox").prop("checked", false);
                }

                $("#work_position").val(position);
                $("#work_office").val(office);
                $("#work_supervisor").val(supervisor);
                $("#work_agency").val(agency);
            });

            $("body").on("click", ".btn-delete", function () {
                if (confirm("Are you sure to remove this?")) {
                    const params = {id: $(this).attr("data-value")};
                    fetch('{{ route('deletePositionApplied') }}', {
                        method: "DELETE",
                        headers: {
                            "Content-Type": "application/json",
                            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                        },
                        body: JSON.stringify(params)
                    })
                        .then(res => res.json())
                        .then(data => {
                            if (data.code == 500) {
                                toastr.error(data.message, "Ooops");
                                return;
                            }

                            toastr.success(data.message, "Information");
                            $("#related_work_id_" + params.id).remove();
                        }).catch(err => {
                        console.log(err);
                    })
                }
            });

            $("#btn_add_related").click(function () {
                $("#work_from").val("");
                $("#work_to").val("");
                $("#work_checkbox").prop("checked", false);
                $("#work_position").val("");
                $("#work_office").val("");
                $("#work_supervisor").val("");
                $("#work_agency").val("");

                selectize_accomplishment.clear();
                selectize_duties.clear();

                edit_promotion_modal.toggle();
            });

            $("#btn_save_related").click(function () {
                const from_date = $("#work_from").val();
                let to_date = $("#work_to").val();
                const position = $("#work_position").val().trim().toUpperCase();
                const office = $("#work_office").val().trim().toUpperCase();
                const supervisor = $("#work_supervisor").val().trim().toUpperCase();
                const agency = $("#work_agency").val().trim().toUpperCase();
                const accomplishments = selectize_accomplishment.getValue();
                const duties = selectize_duties.getValue();

                if ($("#work_checkbox").is(":checked")) {
                    to_date = "Present";
                } else {
                    to_date = moment(to_date).format("MMMM D, YYYY");
                }

                const duration = moment(from_date).format("MMMM D, YYYY") + " - " + to_date;
                const params = {
                    id: related_work_id,
                    work_id: position_applied_id,
                    duration: duration,
                    position: position,
                    office: office,
                    supervisor: supervisor,
                    agency: agency,
                    accomplishments: accomplishments,
                    duties: duties
                }

                fetch('{{ route('insertRelatedWork') }}', {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json",
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    body: JSON.stringify(params)
                })
                    .then(res => res.json())
                    .then(data => {
                        if (data.code == 500) {
                            toastr.error(data.message, "Ooops");
                            return;
                        }

                        $("#work_from").val("");
                        $("#work_to").val("");
                        $("#work_checkbox").prop("checked", false);
                        $("#work_position").val("");
                        $("#work_office").val("");
                        $("#work_supervisor").val("");
                        $("#work_agency").val("");
                        selectize_accomplishment.clear();
                        selectize_duties.clear();
                        toastr.success(data.message, "Information");
                        //IMPROVE NOT TO RELOAD BUT APPEND ONLY
                        getRelatedWork(params.work_id);
                    }).catch(err => {
                    console.log(err);
                });

            });
        });

        function viewWork(id) {
            position_applied_id = id;
            $("#related_work_list").empty();
            $("#work_experience_container").removeClass("d-none");
            $("#btn_print_work").removeClass("d-none");
            promotion_applied_modal.toggle();
            const position_applied = $("#work_id_" + id + " td:eq(0)").text().trim();
            const date_applied = $("#work_id_" + id + " td:eq(1)").text().trim();

            $("#position_applied").val(position_applied);
            $("#date_applied").val(moment(date_applied, "MM/DD/YYYY").format("YYYY-MM-DD"));

            getRelatedWork(position_applied_id)
        }

        function getPromotionWork() {
            fetch('{{ route('getPromotionWork') }}', {
                method: "GET"
            })
                .then(res => res.json())
                .then(data => {
                    $("#work_list").empty();
                    if (data.code == 500) {
                        alert("Something went wrong, please reload the page");
                        return;
                    }

                    data.results.forEach(it => {
                        let tr = `<tr id="work_id_` + it.id + `">`;
                        tr += `<td>` + it.position_applied + `</td>`;
                        tr += `<td>` + moment(it.date_applied).format("MM/DD/YYYY") + `</td>`;
                        tr += `<td><button class="btn btn-sm btn-success" onClick="viewWork(` + it.id + `)"><span class="bi bi-eye-fill"></span></button></td>`;
                        $("#work_list").append(tr);
                    });

                }).catch(err => {
                console.log(err);
            });
        }

        function getRelatedWork(id) {
            fetch('{{ route('getRelatedWork') }}?work_id=' + id, {
                method: "GET"
            })
                .then(res => res.json())
                .then(data => {
                    $("#related_work_list").empty();
                    data.results.forEach(it => {
                        const accomplishment = it.accomplishment;
                        const duties = it.duties;
                        let card = `<div id="related_work_id_` + it.id + `" class="card">
                            <div class="card-header">
                                <div class="dropdown float-end">
                                    <button type="button" class="btn btn-sm" data-bs-toggle="dropdown"
                                            style="background: transparent !important;">
                                        <span class="bi bi-three-dots-vertical"></span>
                                    </button>
                                    <ul class="dropdown-menu">
                                        <li><a class="dropdown-item btn-edit" href="#" data-value="` + it.id + `">Edit</a></li>
                                        <li><a class="dropdown-item btn-delete" href="#" data-value="` + it.id + `">Delete</a></li>
                                    </ul>
                                </div>
                            </div>
                            <div class="card-body p-3">
                                <ul>
                                    <li>Duration: <strong id="duration_id_` + it.id + `">` + it.duration + `</strong></li>
                                    <li>Position: <strong id="position_id_` + it.id + `">` + it.position + `</strong></li>
                                    <li>Name of Office/Unit: <strong id="office_id_` + it.id + `">` + it.office_unit + `</strong></li>
                                    <li>Immediate Supervisor: <strong id="supervisor_id_` + it.id + `">` + it.supervisor + `</strong></li>
                                    <li>Name of Agency/Organization and Location: <strong id="agency_id_` + it.id + `">` + it.agency + `</strong></li>
                                    <li>
                                        <div>List of Accomplishment and Contributions (if any)</div>
                                        <div>
                                            <ul class="fw-bold" id="accomplishment_id_` + it.id + `">`;

                        accomplishment.forEach(ia => {
                            card += `<li>` + ia.description + `</li>`;
                        });
                        card += `</ul>
                                        </div>
                                    </li>
                                    <li>
                                        <div>Summary of Actual Duties</div>
                                        <div>
                                            <ul class="fw-bold" id="duties_id_` + it.id + `">`;
                        duties.forEach(id => {
                            card += `<li>` + id.description + `</li>`;
                        });
                        card += `</ul>
                                        </div>
                                    </li>
                                </ul>
                            </div>
                        </div>`;
                        $("#related_work_list").append(card);
                    });
                }).catch(err => {
                console.log(err);
            });
        }
    </script>
@stop

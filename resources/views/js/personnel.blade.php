<script>
    const CITIZENSHIP = ["Filipino", "Dual Citizenship"];
    const CITIZENSHIP_TYPE = ["by birth", "by naturalization"];

    function getEmployeeDetails(id) {
        if (is_personal_loaded) return;
        fetch('{{route('getEmployeeDetails')}}?id=' + id, {
            method: "GET"
        })
            .then(res => res.json())
            .then(data => {
                if (data.code == 500) {
                    alert(data.message);
                    return;
                }

                is_personal_loaded = true;

                data.results.forEach(it => {
                    $("#fname").val(it.fname);
                    $("#mname").val(it.mname);
                    $("#lname").val(it.lname);
                    $("#suffix").val(it.suffix);
                    $("#birthdate").val(it.birthdate);
                    $("#birthplace").val(it.birthplace);
                    $("#sex").val(it.sex);
                    $("#civil_status").val(it.civil_status);
                    $("#height").val(it.height);
                    $("#weight").val(it.weight);
                    $("#blood_type").val(it.blood_type);
                    $("#gsis_id_no").val(it.gsis_id_no);
                    $("#umid_id_no").val(it.umid_id_no);
                    $("#pagibig_id_no").val(it.pagibig_id_no);
                    $("#philhealth_no").val(it.philhealth_no);
                    $("#sss_no").val(it.sss_no);
                    $("#philsys_no").val(it.philsys_no);
                    $("#tin_no").val(it.tin_no);
                    $("#employee_no").val(it.employee_no);
                    @if(Route::currentRouteName() == "employee")
                    $("#employee_type").val(it.employee_type);
                    $("#date_hired").val(it.date_hired);
                    $("#date_resigned").val(it.date_resigned);
                    @endif
                    $("#telephone_no").val(it.telephone_no);
                    $("#mobile_no").val(it.mobile_no);
                    $("#email_address").val(it.email_address);
                    $("#issued_id").val(it.issued_id);
                    $("#issued_id_no").val(it.issued_id_no);
                    $("#issued_date").val(it.issued_date);

                    const citizen_index = CITIZENSHIP.indexOf(it.citizenship);
                    if (citizen_index < 0) {
                        $("input[name='citizenship']").prop("checked", false);
                    } else {
                        $("input[name='citizenship']:eq(" + citizen_index + ")").prop("checked", true).change();
                        if (it.citizenship == "Dual Citizenship") {
                            const citizen_type_index = CITIZENSHIP_TYPE.indexOf(it.citizenship_type);
                            if (citizen_type_index < 0) {
                                $("input[name='citizenship_type']").prop("checked", false);
                            } else {
                                $("input[name='citizenship_type']:eq(" + citizen_type_index + ")").prop("checked", true);
                            }

                            $("#citizenship_country").val(it.citizenship_country);
                        }
                    }

                });
            }).catch(err => {
            console.log(err);
        });
    }

    function insertUpdateEmployee(params) {
        fetch('{{ route('insertUpdateEmployee') }}', {
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
                    toastr.error(data.message, "Oops");
                    return;
                }

                toastr.success(data.message, "Success");
            }).catch(err => {
            console.log(err);
        });
    }

    function updateEmployeeStatus(params) {
        fetch('{{ route('updateEmployeeStatus') }}', {
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
                    toastr.error(data.message, "Oops");
                    return;
                }

                toastr.success(data.message, "Success");
            }).catch(err => {
            console.log(err);
        });
    }

    function getEmployee(params) {
        $("#btn_next").prop("disabled", true);
        $("#btn_prev").prop("disabled", true);
        $("#employee_list").empty();
        $("#employee_list").append("<tr><td colspan='6'>Loading data, please wait...</td></tr>");
        fetch('{{ route('getEmployee') }}?fname=' + params.fname +
            "&lname=" + params.lname +
            "&page=" + params.page, {
                method: "GET"
            }
        )
            .then(res => res.json())
            .then(data => {
                $("#btn_next").prop("disabled", false);
                $("#btn_prev").prop("disabled", false);
                $("#pagination_container").addClass("d-none")
                $("#employee_list").empty();
                $("#btn_next").addClass("d-none");
                $("#employee_list").empty();
                if (data.results.length < 1) {
                    $("#employee_list").append("<tr><td colspan='6'>No record found</td></tr>");
                    return;
                }

                $("#pagination_container").removeClass("d-none")
                let max_visible = data.results.length;
                if (max_visible == 11) {
                    max_visible = 10;
                    $("#btn_next").removeClass("d-none");
                }

                $("#page_items_count").text(((page * 10) + 1) + " - " + ((page * 10) + max_visible));
                for (let i = 0; i < max_visible; i++) {
                    const it = data.results[i];
                    const path = 'https://dohcsmc.com/id/storage/crop';
                    const suffix = (it.suffix) ? it.suffix : "";
                    const mname = (it.mname) ? it.mname : "";
                    const image = (it.picture) ? (path + "/" + it.picture) : "assets/img/logo.png";
                    const tr = `
                    <tr id='employee_` + it.user_id + `'>
                        <td>
                            <img src="` + image + `" style="width: 50px;height: 50px"/>
                        </td>
                        <td>` + it.fname + `</td>
                        <td>` + mname + `</td>
                        <td>` + it.lname + `</td>
                        <td>` + suffix + `</td>
                        <td>` + it.designation + `</td>
                        <td>` + it.section + `</td>
                        <td>
                            <button type='button' class="btn btn-sm" style="background: transparent" onClick="editEmployee(` + it.user_id + `)">
                                <i class="bi bi-pencil-fill text-success"></i>
                            </button>
                        </td>
                    <tr>`;
                    $("#employee_list").append(tr);
                }
            }).catch(err => {
            console.log(err);
        })
    }
</script>

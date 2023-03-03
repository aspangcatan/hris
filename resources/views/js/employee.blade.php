<script>
    const CITIZENSHIP = ["Filipino", "Dual Citizenship"];
    const CITIZENSHIP_TYPE = ["by birth", "by naturalization"];

    function getEmployeeDetails() {
        if (is_personal_loaded) return;
        fetch('{{route('getEmployeeDetails')}}', {
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
                    $("#pagibig_id_no").val(it.pagibig_id_no);
                    $("#philhealth_no").val(it.philhealth_no);
                    $("#sss_no").val(it.sss_no);
                    $("#tin_no").val(it.tin_no);
                    $("#employee_no").val(it.employee_no);
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
                    console.log(data.message)
                    toastr.error("Something went wrong", "Oops");
                    return;
                }

                toastr.success(data.message, "Success");
            }).catch(err => {
            console.log(err);
        });
    }
</script>

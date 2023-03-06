<script>
    function getEducation(id) {
        if (is_education_loaded) return;
        fetch('{{route('getEducation')}}?id=' + id, {
            method: "GET"
        })
            .then(res => res.json())
            .then(data => {
                if (data.code == 500) {
                    alert(data.message);
                    return;
                }
                is_education_loaded = true;
                data.results.forEach(it => {
                    switch (it.level) {
                        case "Elementary":
                            $("#elementary_school_name").val(it.school_name);
                            $("#elementary_education_degree").val(it.education_degree);
                            $("#elementary_attendance_from").val(it.attendance_from);
                            $("#elementary_attendance_to").val(it.attendance_to);
                            $("#elementary_highest_level").val(it.highest_level);
                            $("#elementary_year_graduated").val(it.year_graduated);
                            $("#elementary_scholarship").val(it.scholarship);
                            break;
                        case "Secondary":
                            $("#secondary_school_name").val(it.school_name);
                            $("#secondary_education_degree").val(it.education_degree);
                            $("#secondary_attendance_from").val(it.attendance_from);
                            $("#secondary_attendance_to").val(it.attendance_to);
                            $("#secondary_highest_level").val(it.highest_level);
                            $("#secondary_year_graduated").val(it.year_graduated);
                            $("#secondary_scholarship").val(it.scholarship);
                            break;
                        case "Vocational/Trade Course":
                            $("#vocational_school_name").val(it.school_name);
                            $("#vocational_education_degree").val(it.education_degree);
                            $("#vocational_attendance_from").val(it.attendance_from);
                            $("#vocational_attendance_to").val(it.attendance_to);
                            $("#vocational_highest_level").val(it.highest_level);
                            $("#vocational_year_graduated").val(it.year_graduated);
                            $("#vocational_scholarship").val(it.scholarship);
                            break;
                        case "College":
                            $("#college_school_name").val(it.school_name);
                            $("#college_education_degree").val(it.education_degree);
                            $("#college_attendance_from").val(it.attendance_from);
                            $("#college_attendance_to").val(it.attendance_to);
                            $("#college_highest_level").val(it.highest_level);
                            $("#college_year_graduated").val(it.year_graduated);
                            $("#college_scholarship").val(it.scholarship);
                            break;
                        case "Graduate Studies":
                            $("#graduate_school_name").val(it.school_name);
                            $("#graduate_education_degree").val(it.education_degree);
                            $("#graduate_attendance_from").val(it.attendance_from);
                            $("#graduate_attendance_to").val(it.attendance_to);
                            $("#graduate_highest_level").val(it.highest_level);
                            $("#graduate_year_graduated").val(it.year_graduated);
                            $("#graduate_scholarship").val(it.scholarship);
                            break;
                    }
                });
            }).catch(err => {
            console.log(err);
        });
    }

    function insertUpdateEducation(params) {
        fetch('{{ route('insertUpdateEducation') }}', {
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

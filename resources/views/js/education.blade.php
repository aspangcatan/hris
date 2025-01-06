<script>
    function getEducation(id) {
        fetch('{{route('getEducation')}}?id=' + id, {
            method: "GET"
        })
            .then(res => res.json())
            .then(data => {
                if (data.code == 500) {
                    alert(data.message);
                    return;
                }
                if (data.results.length < 1) {
                    $("#educational_list").append("<tr><td colspan='8' </td></tr>");
                    return;
                }
                $("#educational_list").empty();
                data.results.forEach(it => {
                    const year_graduated = (it.year_graduated) ? it.year_graduated : "";
                    let tr = "<tr id='education_row_" + it.id + "'>";
                    tr += "<td>" + it.level + "</td>"
                    tr += "<td>" + it.school_name + "</td>"
                    tr += "<td>" + it.education_degree + "</td>"
                    tr += "<td>" + it.attendance_from + "</td>"
                    tr += "<td>" + it.attendance_to + "</td>"
                    tr += "<td>" + it.highest_level + "</td>"
                    tr += "<td>" + year_graduated + "</td>"
                    tr += "<td>" + it.scholarship + "</td>"
                    @if(Route::currentRouteName() != "employee")
                        tr += `<td><button class="btn btn-sm btn-success" onClick="viewEducation(` + it.id + `)"><i class="bi bi-eye-fill"></i></button></td>`;
                        tr += `<td><button class="btn btn-sm btn-danger"  onClick="deleteEducation(` + it.id + `)"><i class="bi bi-trash-fill"></i></button></td>`;
                    @endif
                        tr += "</tr>";
                    $("#educational_list").append(tr);
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
                    toastr.error(data.message, "Oops");
                    return;
                }

                toastr.success(data.message, "Success");
                $("#education_level").val("");
                $("#school_name").val("");
                $("#education_degree").val("");
                $("#attendance_from").val("");
                $("#attendance_to").val("");
                $("#highest_level").val("");
                $("#year_graduated").val("");
                $("#scholarship").val("");
                getEducation(0);
            }).catch(err => {
            toastr.error(err, "Oops");
        });
    }

    function viewEducation(id) {
        education_id = id;
        const level = $("#education_row_" + id + " td:eq(0)").text().trim();
        const school_name = $("#education_row_" + id + " td:eq(1)").text().trim();
        const education_degree = $("#education_row_" + id + " td:eq(2)").text().trim();
        const attendance_from = $("#education_row_" + id + " td:eq(3)").text().trim();
        const attendance_to = $("#education_row_" + id + " td:eq(4)").text().trim();
        const highest_level = $("#education_row_" + id + " td:eq(5)").text().trim();
        const year_graduated = $("#education_row_" + id + " td:eq(6)").text().trim();
        const scholarship = $("#education_row_" + id + " td:eq(7)").text().trim();

        $("#education_level").val(level);
        $("#school_name").val(school_name);
        $("#education_degree").val(education_degree);
        $("#attendance_from").val(attendance_from);
        $("#attendance_to").val(attendance_to);
        $("#highest_level").val(highest_level);
        $("#year_graduated").val(year_graduated);
        $("#scholarship").val(scholarship);

        education_modal.toggle();
    }

    function deleteEducation(id) {
        if (confirm("Are you sure you want to delete this record?")) {
            fetch('{{ route('deleteEducation') }}', {
                method: "DELETE",
                headers: {
                    "Content-Type": "application/json",
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                body: JSON.stringify({id: id})
            })
                .then(res => res.json())
                .then(data => {
                    if (data.code == 500) {
                        toastr.error(data.message, "Ooops");
                        return;
                    }

                    toastr.success(data.message, "Information");
                    getEducation(0);
                }).catch(err => {
                toastr.error(err, "Oops");
            });
        }
    }
</script>

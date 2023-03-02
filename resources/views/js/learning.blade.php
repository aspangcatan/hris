<script>
    function getLearning() {
        fetch('{{route('getLearning')}}', {
            method: "GET"
        })
            .then(res => res.json())
            .then(data => {
                if (data.code == 500) {
                    alert(data.message);
                    return;
                }

                data.results.forEach(it => {
                    const tr = `
                            <tr id='learning_` + it.id + `'>
                                <td>` + it.learning_title + `</td>
                                <td>` + moment(it.attendance_from).format("MM/DD/YYYY") + `</td>
                                <td>` + moment(it.attendance_to).format("MM/DD/YYYY") + `</td>
                                <td>` + it.number_hours + `</td>
                                <td>` + it.learning_type + `</td>
                                <td>` + it.conducted_by + `</td>
                                <td>
                                    <button type='button' class="btn btn-sm" style="background: transparent" onClick="editLearning(` + it.id + `)">
                                        <i class="bi bi-pencil-fill text-success"></i>
                                    </button>
                                </td>
                                <td>
                                    <button type='button' class="btn btn-sm" style="background: transparent" onClick="deleteLearning(` + it.id + `)">
                                        <i class="bi bi-trash text-danger"></i>
                                    </button>
                                </td>
                            </tr>`;

                    $("#learning_list").append(tr);
                });
            }).catch(err => {
            console.log(err);
        });
    }

    function insertUpdateLearning(params) {
        fetch('{{ route('insertUpdateLearning') }}', {
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
                    toastr.error("Something went wrong", "Oops");
                    return;
                }

                if (params.id == 0) {
                    //APPEND NEW ROW
                    const tr = `
                            <tr id='learning_` + data.id + `'>
                                <td>` + params.learning_title + `</td>
                                <td>` + moment(params.attendance_from).format("MM/DD/YYYY") + `</td>
                                <td>` + moment(params.attendance_to).format("MM/DD/YYYY") + `</td>
                                <td>` + params.number_hours + `</td>
                                <td>` + params.learning_type + `</td>
                                <td>` + params.conducted_by + `</td>
                                <td>
                                    <button type='button' class="btn btn-sm" style="background: transparent" onClick="editLearning(` + data.id + `)">
                                        <i class="bi bi-pencil-fill text-success"></i>
                                    </button>
                                </td>
                                <td>
                                    <button type='button' class="btn btn-sm" style="background: transparent" onClick="deleteLearning(` + data.id + `)">
                                        <i class="bi bi-trash text-danger"></i>
                                    </button>
                                </td>
                            </tr>`;

                    $("#learning_list").append(tr);
                } else {
                    $("#learning_" + params.id + " td:eq(0)").text(params.learning_title);
                    $("#learning_" + params.id + " td:eq(1)").text(moment(params.attendance_from).format("MM/DD/YYYY"));
                    $("#learning_" + params.id + " td:eq(2)").text(moment(params.attendance_to).format("MM/DD/YYYY"));
                    $("#learning_" + params.id + " td:eq(3)").text(params.number_hours);
                    $("#learning_" + params.id + " td:eq(4)").text(params.learning_type);
                    $("#learning_" + params.id + " td:eq(5)").text(params.conducted_by);
                }

                learning_id = 0;
                $("#learning_title").val("");
                $("#learning_attendance_from").val("");
                $("#learning_attendance_to").val("");
                $("#learning_number_hours").val("");
                $("#learning_type").val("");
                $("#learning_conducted_by").val("");

                toastr.success(data.message, "Success");
            }).catch(err => {
            console.log(err);
        });
    }

    function deleteLearning(id) {
        if(!confirm("Are you sure you want to delete this learning & development?")) return;

        fetch('{{ route('deleteLearning') }}', {
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
                    toastr.error("Something went wrong", "Oops");
                    return;
                }

                $("#learning_"+id).remove();
                toastr.success(data.message, "Success");
            }).catch(err => {
            console.log(err);
        });
    }
</script>

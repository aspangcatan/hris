<script>
    function getWork(id) {
        if (is_work_loaded) return;
        fetch('{{route('getWork')}}?id=' + id, {
            method: "GET"
        })
            .then(res => res.json())
            .then(data => {
                if (data.code == 500) {
                    alert(data.message);
                    return;
                }
                is_work_loaded = true;
                data.results.forEach(it => {
                    const working_to = (it.is_current_work == "1") ? "Present" : moment(it.working_to).format("MM/DD/YYYY");
                    const is_government = (it.is_government == "1") ? "Y" : "N";
                    let tr = `<tr id='work_id_` + it.id + `'>`;
                    tr += `<td>` + it.position + `</td>`;
                    tr += `<td>` + it.agency + `</td>`;
                    tr += `<td>` + moment(it.working_from).format("MM/DD/YYYY") + `</td>`;
                    tr += `<td>` + working_to + `</td>`;
                    tr += `<td>` + it.monthly_salary + `</td>`;
                    tr += `<td>` + it.salary_grade + `</td>`;
                    tr += `<td>` + it.appointment_status + `</td>`;
                    tr += `<td>` + is_government + `</td>`;
                    tr += `<td class="d-none">` + it.is_current_work + `</td>`;
                    @if(Route::currentRouteName() == "profile")
                        tr += `<td>
                                <button type='button' class="btn btn-sm" style="background: transparent"
                                        onClick="editWork(` + it.id + `)">
                                    <i class="bi bi-pencil-fill text-success"></i>
                                </button>
                           </td>
                           <td>
                                <button type='button' class="btn btn-sm" style="background: transparent"
                                        onClick="deleteWork(` + it.id + `)">
                                    <i class="bi bi-trash text-danger"></i>
                                </button>
                           </td>`
                    @else
                        tr += `<td colspan="2" </td>`
                    @endif
                        tr += `</tr>`;
                    $("#work_list").append(tr);
                });
            }).catch(err => {
            console.log(err);
        });
    }

    function insertUpdateWork(params) {
        fetch('{{ route('insertUpdateWork') }}', {
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

                const working_to = (params.is_current_work == "1") ? "Present" : moment(params.working_to).format("MM/DD/YYYY");
                const is_government = (params.is_government == "1") ? "Y" : "N";
                if (params.id == 0) {
                    const tr = `
                <tr id='work_id_` + data.id + `'>
                <td>` + params.position + `</td>
                <td>` + params.agency + `</td>
                <td>` + moment(params.working_from).format("MM/DD/YYYY") + `</td>
                <td>` + working_to + `</td>
                <td>` + params.monthly_salary + `</td>
                <td>` + params.salary_grade + `</td>
                <td>` + params.appointment_status + `</td>
                <td>` + is_government + `</td>
                <td class="d-none">` + params.is_current_work + `</td>
                <td>
                <button type='button' class="btn btn-sm" style="background: transparent" onClick="editWork(` + data.id + `)">
                <i class="bi bi-pencil-fill text-success"></i>
                </button>
                </td>
                <td>
                <button type='button' class="btn btn-sm" style="background: transparent" onClick="deleteWork(` + params.id + `)">
                <i class="bi bi-trash text-danger"></i>
                </button>
                </td>
                </tr>`;
                    $("#work_list").append(tr);
                } else {
                    $("#work_id_" + params.id + " td:eq(0)").text(params.position);
                    $("#work_id_" + params.id + " td:eq(1)").text(params.agency);
                    $("#work_id_" + params.id + " td:eq(2)").text(moment(params.working_from).format("MM/DD/YYYY"));
                    $("#work_id_" + params.id + " td:eq(3)").text(working_to);
                    $("#work_id_" + params.id + " td:eq(4)").text(params.monthly_salary);
                    $("#work_id_" + params.id + " td:eq(5)").text(params.salary_grade);
                    $("#work_id_" + params.id + " td:eq(6)").text(params.appointment_status);
                    $("#work_id_" + params.id + " td:eq(7)").text(is_government);
                    $("#work_id_" + params.id + " td:eq(8)").text(params.is_current_work);
                }

                work_id = 0;
                $("#work_position").val("");
                $("#work_agency").val("");
                $("#work_from").val("");
                $("#work_to").val("");
                $("#work_salary").val("");
                $("#work_salary_grade").val("");
                $("#work_appointment_status").val("");
                $("#work_government").val("");
                $("#work_current_work").val("");

                toastr.success(data.message, "Success");
            }).catch(err => {
            console.log(err);
        });
    }

    function deleteWork(id) {
        if (!confirm("Are you sure you want to delete this work experience?")) return;

        fetch('{{ route('deleteWork') }}', {
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
                    console.log(data.message);
                    toastr.error("Something went wrong", "Oops");
                    return;
                }

                $("#work_id_" + id).remove();
                toastr.success(data.message, "Success");
            }).catch(err => {
            console.log(err);
        });
    }
</script>

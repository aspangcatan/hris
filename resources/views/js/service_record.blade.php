<script>
    function getServiceRecords(id) {
        if (is_service_loaded) return;
        fetch('{{route('getServiceRecords')}}?id=' + id, {
            method: "GET"
        })
            .then(res => res.json())
            .then(data => {
                console.log(data);
                if (data.code == 500) {
                    alert(data.message);
                    return;
                }
                is_service_loaded = true;
                data.results.forEach(it => {
                    const tr = `
                <tr id='service_` + it.id + `'>
                <td>` + moment(it.date_from).format("MM/DD/YYYY") + `</td>
                <td>` + moment(it.date_to).format("MM/DD/YYYY") + `</td>
                <td>` + it.designation + `</td>
                <td>` + it.appointment_status + `</td>
                <td>` + it.monthly_salary + `</td>
                <td>` + it.office_assignment + `</td>
                <td>` + it.leave_absence + `</td>
                <td>` + it.separation + `</td>
                <td>
                    <div class="d-flex justify-content-center gap-2 flex-wrap">
                        <button type='button'  class="btn btn-action btn-edit" onClick="editService(` + it.id + `)" title="Edit"><i class="bi bi-pencil"></i></button>
                        <button type='button' class="btn btn-action btn-delete" title="Delete" onClick="deleteService(` + it.id + `)"><i class="bi bi-trash"></i></button>
                    </div>
                </td>
                </tr>`;
                    $("#service_records_list").append(tr);
                });
            }).catch(err => {
            console.log(err);
        });
    }

    function insertUpdateServiceRecords(params) {
        fetch('{{ route('insertUpdateServiceRecords') }}', {
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
                    const tr = `
                <tr id='service_` + data.id + `'>
                <td>` + moment(params.date_from).format("MM/DD/YYYY") + `</td>
                <td>` + moment(params.date_to).format("MM/DD/YYYY") + `</td>
                <td>` + params.designation + `</td>
                <td>` + params.appointment_status + `</td>
                <td>` + params.monthly_salary + `</td>
                <td>` + params.office_assignment + `</td>
                <td>` + params.leave_absence + `</td>
                <td>` + params.separation + `</td>
                <td>
                    <div class="d-flex justify-content-center gap-2 flex-wrap">
                        <button type='button'  class="btn btn-action btn-edit" onClick="editService(` + data.id + `)" title="Edit"><i class="bi bi-pencil"></i></button>
                        <button type='button' class="btn btn-action btn-delete" title="Delete" onClick="deleteService(` + data.id + `)"><i class="bi bi-trash"></i></button>
                    </div>
                </td>
                </tr>`;
                    $("#service_records_list").append(tr);
                } else {
                    $("#service_" + params.id + " td:eq(0)").text(moment(params.date_from).format("MM/DD/YYYY"));
                    $("#service_" + params.id + " td:eq(1)").text(moment(params.date_to).format("MM/DD/YYYY"));
                    $("#service_" + params.id + " td:eq(2)").text(params.designation);
                    $("#service_" + params.id + " td:eq(3)").text(params.appointment_status);
                    $("#service_" + params.id + " td:eq(4)").text(params.monthly_salary);
                    $("#service_" + params.id + " td:eq(5)").text(params.office_assignment);
                    $("#service_" + params.id + " td:eq(6)").text(params.leave_absence);
                    $("#service_" + params.id + " td:eq(7)").text(params.separation);
                }

                service_id = 0;
                $("#service_from").val("");
                $("#service_to").val("");
                $("#service_designation").val("");
                $("#service_status").val("");
                $("#service_salary").val("");
                $("#service_office").val("");
                $("#service_leave").val("");
                $("#service_separation").val("");
                toastr.success(data.message, "Success");
            }).catch(err => {
            console.log(err);
        });
    }

    function deleteService(id) {
        if (!confirm("Are you sure you want to delete this service record?")) return;

        fetch('{{ route('deleteServiceRecords') }}', {
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

                $("#service_" + id).remove();
                toastr.success(data.message, "Success");
            }).catch(err => {
            console.log(err);
        });
    }
</script>

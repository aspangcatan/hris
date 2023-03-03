<script>
    function getVoluntary() {
        if (is_voluntary_loaded) return;
        fetch('{{route('getVoluntary')}}', {
            method: "GET"
        })
            .then(res => res.json())
            .then(data => {
                if (data.code == 500) {
                    alert(data.message);
                    return;
                }
                is_voluntary_loaded = true;
                data.results.forEach(it => {
                    const tr = `
                            <tr id='voluntary_` + it.id + `'>
                                <td>` + it.organization_address + `</td>
                                <td>` + it.position + `</td>
                                <td>` + moment(it.working_from).format("MM/DD/YYYY") + `</td>
                                <td>` + moment(it.working_to).format("MM/DD/YYYY") + `</td>
                                <td>` + it.number_hours + `</td>
                                <td>
                                    <button type='button' class="btn btn-sm" style="background: transparent" onClick="editVoluntary(` + it.id + `)">
                                        <i class="bi bi-pencil-fill text-success"></i>
                                    </button>
                                </td>
                                <td>
                                    <button type='button' class="btn btn-sm" style="background: transparent" onClick="deleteVoluntary(` + it.id + `)">
                                        <i class="bi bi-trash text-danger"></i>
                                    </button>
                                </td>
                            </tr>`;

                    $("#voluntary_list").append(tr);
                });
            }).catch(err => {
            console.log(err);
        });
    }

    function insertUpdateVoluntary(params) {
        fetch('{{ route('insertUpdateVoluntary') }}', {
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
                            <tr id='voluntary_` + data.id + `'>
                                <td>` + params.organization_address + `</td>
                                <td>` + params.position + `</td>
                                <td>` + moment(params.working_from).format("MM/DD/YYYY") + `</td>
                                <td>` + moment(params.working_to).format("MM/DD/YYYY") + `</td>
                                <td>` + params.number_hours + `</td>
                                <td>
                                    <button type='button' class="btn btn-sm" style="background: transparent" onClick="editVoluntary(` + data.id + `)">
                                        <i class="bi bi-pencil-fill text-success"></i>
                                    </button>
                                </td>
                                <td>
                                    <button type='button' class="btn btn-sm" style="background: transparent" onClick="deleteVoluntary(` + data.id + `)">
                                        <i class="bi bi-trash text-danger"></i>
                                    </button>
                                </td>
                            </tr>`;

                    $("#voluntary_list").append(tr);
                } else {
                    $("#voluntary_" + params.id + " td:eq(0)").text(params.organization_address);
                    $("#voluntary_" + params.id + " td:eq(1)").text(params.position);
                    $("#voluntary_" + params.id + " td:eq(2)").text(moment(params.working_from).format("MM/DD/YYYY"));
                    $("#voluntary_" + params.id + " td:eq(3)").text(moment(params.working_to).format("MM/DD/YYYY"));
                    $("#voluntary_" + params.id + " td:eq(4)").text(params.number_hours);
                }

                voluntary_id = 0;
                $("#voluntary_organization_address").val("");
                $("#voluntary_position").val("");
                $("#voluntary_working_from").val("");
                $("#voluntary_working_to").val("");
                $("#voluntary_number_hours").val("");

                toastr.success(data.message, "Success");
            }).catch(err => {
            console.log(err);
        });
    }

    function deleteVoluntary(id) {
        if (!confirm("Are you sure you want to delete this voluntary work?")) return;

        fetch('{{ route('deleteVoluntary') }}', {
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

                $("#voluntary_" + id).remove();
                toastr.success(data.message, "Success");
            }).catch(err => {
            console.log(err);
        });
    }
</script>

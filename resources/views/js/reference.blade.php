<script>
    function getReference() {
        fetch('{{route('getReference')}}', {
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
                            <tr id='reference_` + it.id + `'>
                                <td>` + it.name + `</td>
                                <td>` + it.address + `</td>
                                <td>` + it.contact_no + `</td>
                                <td>
                                    <button type='button' class="btn btn-sm" style="background: transparent" onClick="editReference(` + it.id + `)">
                                        <i class="bi bi-pencil-fill text-success"></i>
                                    </button>
                                </td>
                                <td>
                                    <button type='button' class="btn btn-sm" style="background: transparent" onClick="deleteReference(` + it.id + `)">
                                        <i class="bi bi-trash text-danger"></i>
                                    </button>
                                </td>
                            </tr>`;

                    $("#reference_list").append(tr);
                });
            }).catch(err => {
            console.log(err);
        });
    }

    function insertUpdateReference(params) {
        fetch('{{ route('insertUpdateReference') }}', {
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
                            <tr id='reference_` + data.id + `'>
                                <td>` + params.name + `</td>
                                <td>` + params.address + `</td>
                                <td>` + params.contact_no + `</td>
                                <td>
                                    <button type='button' class="btn btn-sm" style="background: transparent" onClick="editReference(` + data.id + `)">
                                        <i class="bi bi-pencil-fill text-success"></i>
                                    </button>
                                </td>
                                <td>
                                    <button type='button' class="btn btn-sm" style="background: transparent" onClick="deleteReference(` + data.id + `)">
                                        <i class="bi bi-trash text-danger"></i>
                                    </button>
                                </td>
                            </tr>`;

                    $("#reference_list").append(tr);
                } else {
                    $("#reference_" + params.id + " td:eq(0)").text(params.name);
                    $("#reference_" + params.id + " td:eq(1)").text(params.address);
                    $("#reference_" + params.id + " td:eq(2)").text(params.contact_no);
                }

                reference_id = 0;
                $("#reference_name").val("");
                $("#reference_address").val("");
                $("#reference_contact_no").val("");
                toastr.success(data.message, "Success");
            }).catch(err => {
            console.log(err);
        });
    }

    function deleteReference(id) {
        if (!confirm("Are you sure you want to delete this reference?")) return;

        fetch('{{ route('deleteReference') }}', {
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

                $("#reference_" + id).remove();
                toastr.success(data.message, "Success");
            }).catch(err => {
            console.log(err);
        });
    }
</script>

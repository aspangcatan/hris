<script>
    function getCivilService() {
        fetch('{{route('getCivilService')}}', {
            method: "GET"
        })
            .then(res => res.json())
            .then(data => {
                if (data.code == 500) {
                    alert(data.message);
                    return;
                }

                data.results.forEach(it => {
                    const license_no = (it.license_no) ? it.license_no : "";
                    const license_expiry = (it.license_expiry) ? moment(it.license_expiry).format("MM/DD/YYYY") : "";
                    const tr = `
                            <tr id='civil_service_` + it.id + `'>
                                <td>` + it.career_service + `</td>
                                <td>` + it.rating + `</td>
                                <td>` + moment(it.examination_date).format("MM/DD/YYYY") + `</td>
                                <td>` + it.examination_place + `</td>
                                <td>` + license_no + `</td>
                                <td>` + license_expiry + `</td>
                                <td>
                                    <button type='button' class="btn btn-sm" style="background: transparent" onClick="editCivilService(` + it.id + `)">
                                        <i class="bi bi-pencil-fill text-success"></i>
                                    </button>
                                </td>
                                <td>
                                    <button type='button' class="btn btn-sm" style="background: transparent" onClick="deleteCivilService(` + it.id + `)">
                                        <i class="bi bi-trash text-danger"></i>
                                    </button>
                                </td>
                            </tr>`;

                    $("#civil_service_list").append(tr);
                });
            }).catch(err => {
            console.log(err);
        });
    }

    function insertUpdateCivilService(params) {
        fetch('{{ route('insertUpdateCivilService') }}', {
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

                const license_no = (params.license_no) ? params.license_no : "";
                const license_expiry = (params.license_expiry) ? moment(params.license_expiry).format("MM/DD/YYYY") : "";

                if (params.id == 0) {
                    //APPEND NEW ROW
                    const tr = `
                            <tr id='civil_service_` + data.id + `'>
                                <td>` + params.career_service + `</td>
                                <td>` + params.rating + `</td>
                                <td>` + moment(params.examination_date).format("MM/DD/YYYY") + `</td>
                                <td>` + params.examination_place + `</td>
                                <td>` + license_no + `</td>
                                <td>` + license_expiry + `</td>
                                <td>
                                    <button type='button' class="btn btn-sm" style="background: transparent" onClick="editCivilService(` + data.id + `)">
                                        <i class="bi bi-pencil-fill text-success"></i>
                                    </button>
                                </td>
                                <td>
                                    <button type='button' class="btn btn-sm" style="background: transparent" onClick="deleteCivilService(` + data.id + `)">
                                        <i class="bi bi-trash text-danger"></i>
                                    </button>
                                </td>
                            </tr>`;

                    $("#civil_service_list").append(tr);
                } else {
                    $("#civil_service_" + params.id + " td:eq(0)").text(params.career_service);
                    $("#civil_service_" + params.id + " td:eq(1)").text(params.rating);
                    $("#civil_service_" + params.id + " td:eq(2)").text(moment(params.examination_date).format("MM/DD/YYYY"));
                    $("#civil_service_" + params.id + " td:eq(3)").text(params.examination_place);
                    $("#civil_service_" + params.id + " td:eq(4)").text(license_no);
                    $("#civil_service_" + params.id + " td:eq(5)").text(license_expiry);
                }

                toastr.success(data.message, "Success");
            }).catch(err => {
            console.log(err);
        });
    }

    function deleteCivilService(id) {
        if(!confirm("Are you sure you want to delete this career service?")) return;

        fetch('{{ route('deleteCivilService') }}', {
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

                $("#civil_service_"+id).remove();
                toastr.success(data.message, "Success");
            }).catch(err => {
            console.log(err);
        });
    }
</script>

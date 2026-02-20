<script>
    function getFamilyBackground(id) {
        if (is_family_loaded) return;
        fetch('{{route('getFamilyBackground')}}?id=' + id, {
            method: "GET"
        })
            .then(res => res.json())
            .then(data => {
                if (data.code == 500) {
                    alert(data.message);
                    return;
                }
                is_family_loaded = true;
                data.family_background.forEach(it => {
                    $("#spouse_fname").val(it.spouse_fname);
                    $("#spouse_mname").val(it.spouse_mname);
                    $("#spouse_lname").val(it.spouse_lname);
                    $("#spouse_ext_name").val(it.spouse_ext_name);
                    $("#spouse_occupation").val(it.spouse_occupation);
                    $("#spouse_employer").val(it.spouse_employer);
                    $("#spouse_business_address").val(it.spouse_business_address);
                    $("#spouse_contact_no").val(it.spouse_contact_no);
                    $("#father_fname").val(it.father_fname);
                    $("#father_mname").val(it.father_mname);
                    $("#father_lname").val(it.father_lname);
                    $("#father_ext_name").val(it.father_ext_name);
                    $("#mother_fname").val(it.mother_fname);
                    $("#mother_mname").val(it.mother_mname);
                    $("#mother_lname").val(it.mother_lname);
                    $("#mother_ext_name").val(it.mother_ext_name);
                });

                data.children.forEach(it => {
                    let tr = `<tr id='child_id_` + it.id + `'>`;
                    tr += `<td>` + it.name + `</td>`;
                    tr += `<td>` + moment(it.birthdate).format("MM/DD/YYYY") + `</td>`;

                    @if(Route::currentRouteName() == "profile")
                        tr += `<td>
                                    <div class="d-flex justify-content-center gap-2 flex-wrap">
                                        <button type='button' class="btn btn-sm btn-outline-secondary" onClick="editChildren(` + it.id + `)">Edit</button>
                                        <button type='button' class="btn btn-sm btn-outline-danger" onClick="deleteChildren(` + it.id + `)">Delete</button>
                                    </div>
                                </td>`;
                    @else
                        tr += `<td></td>`;
                    @endif
                        tr += `</tr>`;
                    $("#children_list").append(tr);
                });
            }).catch(err => {
            console.log(err);
        });
    }

    function insertUpdateFamilyBackground(params) {
        fetch('{{ route('insertUpdateFamilyBackground') }}', {
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

    function insertUpdateChildren(params) {
        fetch('{{ route('insertUpdateChildren') }}', {
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

                if (params.id == 0) {
                    //APPEND NEW ROW
                    const tr = `
                <tr id='child_id_` + data.id + `'>
                <td>` + params.name + `</td>
                <td>` + moment(params.birthdate).format("MM/DD/YYYY") + `</td>
                <td>
                    <div class="d-flex justify-content-center gap-2 flex-wrap">
                        <button type='button' class="btn btn-sm btn-outline-secondary" onClick="editChildren(` + data.id + `)">Edit</button>
                        <button type='button' class="btn btn-sm btn-outline-danger" onClick="deleteChildren(` + data.id + `)">Delete</button>
                    </div>
                </td>
                </tr>`;

                    $("#children_list").append(tr);
                } else {
                    $("#child_id_" + params.id + " td:eq(0)").text(params.name);
                    $("#child_id_" + params.id + " td:eq(1)").text(moment(params.birthdate).format("MM/DD/YYYY"));
                }

                toastr.success(data.message, "Success");
            }).catch(err => {
            toastr.error(err, "Oops");
        });
    }

    function deleteChildren(id) {
        if (!confirm("Are you sure you want to delete this child?")) return;

        fetch('{{ route('deleteChildren') }}', {
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
                    toastr.error(data.message, "Oops");
                    return;
                }

                $("#child_id_" + id).remove();
                toastr.success(data.message, "Success");
            }).catch(err => {
            console.log(err);
        });
    }
</script>

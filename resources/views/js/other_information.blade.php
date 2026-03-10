<script>
    function getOtherInformation(id) {
        if (is_other_loaded) return;
        fetch('{{route('getOtherInformation')}}?id=' + id, {
            method: "GET"
        })
            .then(res => res.json())
            .then(data => {
                if (data.code == 500) {
                    alert(data.message);
                    return;
                }

                is_other_loaded = true;
                const skill = data.results.skill;
                const recognition = data.results.recognition;
                const membership = data.results.membership;

                skill.forEach(it => {
                    let tr = `<tr id='skill_` + it.id + `'>`;
                    tr += `<td>` + it.skills + `</td>`;
                    @if(Route::currentRouteName() == "profile")
                        tr += `<td style="width: 16%">
                                <div class="d-flex justify-content-center gap-2 flex-wrap">
                                    <button type='button'  class="btn btn-action btn-edit" onClick="editSkill(` + it.id + `)" title="Edit"><i class="bi bi-pencil"></i></button>
                                    <button type='button' class="btn btn-action btn-delete" title="Delete" onClick="deleteSkill(` + it.id + `)"><i class="bi bi-trash"></i></button>
                                </div>
                            </td>`
                    @else
                        tr += `<td></td>`
                    @endif
                        tr += `</tr>`;

                    $("#skill_list").append(tr);
                });

                recognition.forEach(it => {
                    let tr = `<tr id='recognition_` + it.id + `'>`;
                    tr += `<td>` + it.recognition + `</td>`;
                    @if(Route::currentRouteName() == "profile")
                        tr += `<td style="width: 16%">
                                <div class="d-flex justify-content-center gap-2 flex-wrap">
                                    <button type='button'  class="btn btn-action btn-edit" onClick="editRecognition(` + it.id + `)" title="Edit"><i class="bi bi-pencil"></i></button>
                                    <button type='button' class="btn btn-action btn-delete" title="Delete" onClick="deleteRecognition(` + it.id + `)"><i class="bi bi-trash"></i></button>
                                </div>
                            </td>`
                    @else
                        tr += `<td></td>`
                    @endif
                        tr += `</tr>`;

                    $("#recognition_list").append(tr);
                });

                membership.forEach(it => {
                    let tr = `<tr id='membership_` + it.id + `'>`;
                    tr += `<td>` + it.association + `</td>`;
                    @if(Route::currentRouteName() == "profile")
                        tr += `<td style="width: 16%">
                                <div class="d-flex justify-content-center gap-2 flex-wrap">
                                    <button type='button'  class="btn btn-action btn-edit" onClick="editMembership(` + it.id + `)" title="Edit"><i class="bi bi-pencil"></i></button>
                                    <button type='button' class="btn btn-action btn-delete" title="Delete" onClick="deleteMembership(` + it.id + `)"><i class="bi bi-trash"></i></button>
                                </div>
                            </td>`
                    @else
                        tr += `<td></td>`
                    @endif
                        tr += `</tr>`;
                    $("#membership_list").append(tr);
                });

            }).catch(err => {
            console.log(err);
        });
    }

    function insertUpdateSkill(params) {
        fetch('{{ route('insertUpdateSkill') }}', {
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
                <tr id='skill_` + data.id + `'>
                <td>` + params.skills + `</td>
                <td style="width: 16%">
                    <div class="d-flex justify-content-center gap-2 flex-wrap">
                        <button type='button'  class="btn btn-action btn-edit" onClick="editSkill(` + data.id + `)" title="Edit"><i class="bi bi-pencil"></i></button>
                        <button type='button' class="btn btn-action btn-delete" title="Delete" onClick="deleteSkill(` + data.id + `)"><i class="bi bi-trash"></i></button>
                    </div>
                </td>
                </tr>`;

                    $("#skill_list").append(tr);
                } else {
                    $("#skill_" + params.id + " td:eq(0)").text(params.skills);
                }

                skill_id = 0;
                $("#other_information_description").val("");
                toastr.success(data.message, "Success");
            }).catch(err => {
            toastr.error(err, "Oops");
        });
    }

    function insertUpdateRecognition(params) {
        fetch('{{ route('insertUpdateRecognition') }}', {
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
                <tr id='recognition_` + data.id + `'>
                <td>` + params.recognition + `</td>
                <td style="width: 16%">
                    <div class="d-flex justify-content-center gap-2 flex-wrap">
                        <button type='button'  class="btn btn-action btn-edit" onClick="editRecognition(` + data.id + `)" title="Edit"><i class="bi bi-pencil"></i></button>
                        <button type='button' class="btn btn-action btn-delete" title="Delete" onClick="deleteRecognition(` + data.id + `)"><i class="bi bi-trash"></i></button>
                    </div>
                </td>
                </tr>`;

                    $("#recognition_list").append(tr);
                } else {
                    $("#recognition_" + params.id + " td:eq(0)").text(params.recognition);
                }

                recognition_id = 0;
                $("#other_information_description").val("");
                toastr.success(data.message, "Success");
            }).catch(err => {
            toastr.error(err, "Oops");
        });
    }

    function insertUpdateMembership(params) {
        fetch('{{ route('insertUpdateMembership') }}', {
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
                <tr id='membership_` + data.id + `'>
                <td>` + params.association + `</td>
                <td style="width: 16%">
                    <div class="d-flex justify-content-center gap-2 flex-wrap">
                        <button type='button'  class="btn btn-action btn-edit" onClick="editMembership(` + data.id + `)" title="Edit"><i class="bi bi-pencil"></i></button>
                        <button type='button' class="btn btn-action btn-delete" title="Delete" onClick="deleteMembership(` + data.id + `)"><i class="bi bi-trash"></i></button>
                    </div>
                </td>
                </tr>`;

                    $("#membership_list").append(tr);
                } else {
                    $("#membership_" + params.id + " td:eq(0)").text(params.association);
                }

                membership_id = 0;
                $("#other_information_description").val("");
                toastr.success(data.message, "Success");
            }).catch(err => {
            toastr.error(err, "Oops");
        });
    }

    function deleteSkill(id) {
        if (!confirm("Are you sure you want to delete this?")) return;

        fetch('{{ route('deleteSkill') }}', {
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

                $("#skill_" + id).remove();
                toastr.success(data.message, "Success");
            }).catch(err => {
            toastr.error(err, "Oops");
        });
    }

    function deleteRecognition(id) {
        if (!confirm("Are you sure you want to delete this?")) return;

        fetch('{{ route('deleteRecognition') }}', {
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

                $("#recognition_" + id).remove();
                toastr.success(data.message, "Success");
            }).catch(err => {
            toastr.error(err, "Oops");
        });
    }

    function deleteMembership(id) {
        if (!confirm("Are you sure you want to delete this?")) return;

        fetch('{{ route('deleteMembership') }}', {
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

                $("#membership_" + id).remove();
                toastr.success(data.message, "Success");
            }).catch(err => {
            toastr.error(err, "Oops");
        });
    }
</script>

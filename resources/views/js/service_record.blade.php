<script>
    function serviceDateToText(record) {
        return record.current_work == 1 ? "Present" : moment(record.date_to).format("MM/DD/YYYY");
    }

    function serviceText(value) {
        return (value === null || value === undefined || value === "null") ? "" : value;
    }

    function getServiceRecords(id) {
        if (is_service_loaded) return;

        const loadingRow = `<tr id="service_loading_row"><td colspan="9" class="text-center py-3">
            <span class="spinner-border spinner-border-sm me-2"></span>Loading service records...
        </td></tr>`;
        $("#service_records_list").append(loadingRow);

        fetch('{{route('getServiceRecords')}}?id=' + id, {
            method: "GET"
        })
            .then(res => res.json())
            .then(data => {
                // Ignore a late response if another employee was opened in the meantime
                if (id != employee_id) return;
                $("#service_loading_row").remove();
                if (data.code == 500) {
                    alert(data.message);
                    return;
                }
                is_service_loaded = true;
                data.results.forEach(it => {
                    const tr = `
                <tr id='service_` + it.id + `'>
                <td>` + moment(it.date_from).format("MM/DD/YYYY") + `</td>
                <td>` + serviceDateToText(it) + `</td>
                <td>` + serviceText(it.designation) + `</td>
                <td>` + serviceText(it.appointment_status) + `</td>
                <td>` + serviceText(it.monthly_salary) + `</td>
                <td>` + serviceText(it.office_assignment) + `</td>
                <td style="white-space: pre-line">` + serviceText(it.leave_absence) + `</td>
                <td>` + serviceText(it.separation) + `</td>
                <td>
                    <div class="d-flex justify-content-center gap-2 flex-wrap">
                        <button type='button'  class="btn btn-action btn-edit" onClick="editService(` + it.id + `)" title="Edit"><i class="bi bi-pencil"></i></button>
                        <button type='button' class="btn btn-action btn-delete" title="Delete" onClick="deleteService(` + it.id + `, this)"><i class="bi bi-trash"></i></button>
                    </div>
                </td>
                </tr>`;
                    $("#service_records_list").append(tr);
                });
            }).catch(err => {
            console.log(err);
            $("#service_loading_row").remove();
        });
    }

    function insertUpdateServiceRecords(params) {
        const $btn = $("#btn_save_service");
        const originalBtnHtml = $btn.html();
        $btn.prop("disabled", true).html('<span class="spinner-border spinner-border-sm me-2"></span>Saving...');

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
                <td>` + serviceDateToText(params) + `</td>
                <td>` + serviceText(params.designation) + `</td>
                <td>` + serviceText(params.appointment_status) + `</td>
                <td>` + serviceText(params.monthly_salary) + `</td>
                <td>` + serviceText(params.office_assignment) + `</td>
                <td style="white-space: pre-line">` + serviceText(params.leave_absence) + `</td>
                <td>` + serviceText(params.separation) + `</td>
                <td>
                    <div class="d-flex justify-content-center gap-2 flex-wrap">
                        <button type='button'  class="btn btn-action btn-edit" onClick="editService(` + data.id + `)" title="Edit"><i class="bi bi-pencil"></i></button>
                        <button type='button' class="btn btn-action btn-delete" title="Delete" onClick="deleteService(` + data.id + `, this)"><i class="bi bi-trash"></i></button>
                    </div>
                </td>
                </tr>`;
                    $("#service_records_list").append(tr);
                } else {
                    $("#service_" + params.id + " td:eq(0)").text(moment(params.date_from).format("MM/DD/YYYY"));
                    $("#service_" + params.id + " td:eq(1)").text(serviceDateToText(params));
                    $("#service_" + params.id + " td:eq(2)").text(serviceText(params.designation));
                    $("#service_" + params.id + " td:eq(3)").text(serviceText(params.appointment_status));
                    $("#service_" + params.id + " td:eq(4)").text(serviceText(params.monthly_salary));
                    $("#service_" + params.id + " td:eq(5)").text(serviceText(params.office_assignment));
                    $("#service_" + params.id + " td:eq(6)").text(serviceText(params.leave_absence));
                    $("#service_" + params.id + " td:eq(7)").text(serviceText(params.separation));
                }

                service_id = 0;
                $("#service_from").val("");
                $("#service_to").val("");
                $("#service_present").prop("checked", false).trigger("change");
                $("#service_designation").val("");
                $("#service_status").val("");
                $("#service_salary").val("");
                $("#service_office").val("");
                $("#service_leave").val("");
                $("#service_separation").val("");
                toastr.success(data.message, "Success");
            }).catch(err => {
            console.log(err);
            toastr.error("Something went wrong", "Oops");
        }).finally(() => {
            $btn.prop("disabled", false).html(originalBtnHtml);
        });
    }

    function deleteService(id, btn) {
        if (!confirm("Are you sure you want to delete this service record?")) return;

        const $btn = $(btn);
        const originalBtnHtml = $btn.html();
        $btn.prop("disabled", true).html('<span class="spinner-border spinner-border-sm"></span>');

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
                    $btn.prop("disabled", false).html(originalBtnHtml);
                    return;
                }

                $("#service_" + id).remove();
                toastr.success(data.message, "Success");
            }).catch(err => {
            console.log(err);
            toastr.error("Something went wrong", "Oops");
            $btn.prop("disabled", false).html(originalBtnHtml);
        });
    }
</script>

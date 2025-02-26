<script>
    let page = 0;
    let leave_id = 0;
    $(document).ready(function () {
        $("#btn_next").click(function () {
            page++;
            getLeaveTypes();
            $("#btn_prev").removeClass("d-none");
        });

        $("#btn_prev").click(function () {
            if (page == 0) return;
            if (page == 1) {
                $("#btn_prev").addClass("d-none");
            }
            page--;
            getLeaveTypes();
        });

        $("#btn_search_employee").click(function () {
            page = 0;
            getLeaveTypes();
        });

        $("#btn_add_leave").click(function () {
            $("#description").val("");
            $("#code").val("");
            $("#daily_points_earned").val("");
            $("#credit_deduction").val("");
            $("#leave_type_modal").modal("show");
        });

        getLeaveTypes();
    });

    async function store() {
        const description = $("#description").val();
        const code = $("#code").val();
        const daily_points_earned = $("#daily_points_earned").val();

        const params = {
            description: description,
            code: code,
            daily_points_earned: daily_points_earned
        }

        const response = await fetch('{{ route('insertUpdateLeaveType') }}', {
            method: "PUT",
            headers: {
                "Content-Type": "application/json",
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            body: JSON.stringify(params)
        });

        try {
            const data = await response.json();
            if (response.ok) {
                $("#leave_type_modal").modal("hide");
                toastr.success(data.message, "Information");
                getLeaveTypes();
            } else {
                toastr.error(data.error || 'Something went wrong, please try again');
            }
        } catch (error) {
            toastr.error(error, "Ooops");
        }
    }

    function editLeave(id) {
        const description = $("#leave_" + id + " td:eq(0)").text();
        const code = $("#leave_" + id + " td:eq(1)").text();
        const daily_points_earned = $("#leave_" + id + " td:eq(2)").text();

        $("#description").val(description);
        $("#code").val(code);
        $("#daily_points_earned").val(daily_points_earned);
        $("#leave_type_modal").modal("show");
    }

    async function deleteLeave(id) {
        if (confirm("Are you sure you want to delete this record?")) {
            const response = await fetch("{{ route('deleteLeaveType') }}", {
                method: "DELETE",
                headers: {
                    "Content-Type": "application/json",
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                body: JSON.stringify({id: id})
            });

            try {
                const data = await response.json();
                if (response.ok) {
                    toastr.success(data.message, "Information");
                    getLeaveTypes();
                } else {
                    toastr.error(data.error || 'Something went wrong, please try again');
                }
            } catch (error) {
                toastr.error(error, "Ooops");
            }
        }
    }

    async function getLeaveTypes() {
        const response = await fetch('{{ route('getLeaveTypes') }}', {
            method: "GET"
        });

        try {
            const data = await response.json();
            if (response.ok) {
                $("#leave_list").empty();
                data.forEach(it => {
                    const tr = `
                    <tr id="leave_` + it.id + `">
                        <td>` + it.description + `</td>
                        <td>` + it.code + `</td>
                        <td>` + it.daily_points_earned + `</td>
                        <td>
                            <button class="btn btn-sm btn-transparent" onClick='editLeave(` + it.id + `)'>
                                <i class="bi bi-pencil-fill text-success"></i>
                            </button>
                        </td>
                        <td>
                            <button class="btn btn-sm btn-transparent" onClick='deleteLeave(` + it.id + `)'>
                                <i class="bi bi-trash-fill text-danger"></i>
                            </button>
                        </td>
                    </tr>`;

                    $("#leave_list").append(tr);
                });
            } else {
                toastr.error(data.error || "Something went wrong, please try again");
            }

        } catch (error) {
            toastr.error(error, "Ooops");
        }
    }

    function saveBalances(id) {
        let is_valid = true;
        const vl_bal = $("#balance_id_" + id + " td:eq(1) input").val();
        const sl_bal = $("#balance_id_" + id + " td:eq(2) input").val();
        const cto_bal = $("#balance_id_" + id + " td:eq(3) input").val();
        const fl_bal = $("#balance_id_" + id + " td:eq(4) input").val();
        const spl_bal = $("#balance_id_" + id + " td:eq(5) input").val();

        if (vl_bal == "") {
            toastr.error("Please fill in vl balance", "Ooops");
            is_valid = false;
        }

        if (sl_bal == "") {
            toastr.error("Please fill in sl balance", "Ooops");
            is_valid = false;
        }

        if (cto_bal == "") {
            toastr.error("Please fill in cto balance", "Ooops");
            is_valid = false;
        }

        if (fl_bal == "") {
            toastr.error("Please fill in fl balance", "Ooops");
            is_valid = false;
        }

        if (spl_bal == "") {
            toastr.error("Please fill in spl balance", "Ooops");
            is_valid = false;
        }

        if (!is_valid) return;

        const params = {
            user_id: id,
            vl_bal: vl_bal,
            sl_bal: sl_bal,
            cto_bal: cto_bal,
            fl_bal: fl_bal,
            spl_bal: spl_bal
        }

        console.log(params);
        fetch('{{ route('insertUpdateCredits') }}', {
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
                    toastr.error(data.message, "Ooops");
                    return;
                }

                toastr.success(data.message, "Information");
            }).catch(err => {
            toastr.error("Something went wrong, please try again", "Ooops");
            console.log(err);
        });
    }

    function exportToCsv() {
        const header = ["EMPLOYEE", "VL BALANCE", "SL BALANCE", "CTO BALANCE", "FL BALANCE", "SPL BALANCE"];
        fetch('{{ route('getAllBalances') }}', {
            method: "GET"
        })
            .then(res => res.json())
            .then(data => {
                if (data.code == 500) {
                    toastr.error(data.message, "Ooops");
                    return;
                }

                const Results = [header];
                data.results.forEach(it => {
                    const my_array = [];
                    const vl_bal = (it.vl_bal) ? it.vl_bal : "0.00";
                    const sl_bal = (it.sl_bal) ? it.sl_bal : "0.00";
                    const cto_bal = (it.cto_bal) ? it.cto_bal : "0.00";
                    const fl_bal = (it.fl_bal) ? it.fl_bal : "0.00";
                    const spl_bal = (it.spl_bal) ? it.spl_bal : "0.00";

                    my_array.push(it.fname + " " + it.mname + " " + it.lname);
                    my_array.push(vl_bal);
                    my_array.push(sl_bal);
                    my_array.push(cto_bal);
                    my_array.push(fl_bal);
                    my_array.push(spl_bal);
                    Results.push(my_array);
                });


                var CsvString = "";
                Results.forEach(function (RowItem, RowIndex) {
                    RowItem.forEach(function (ColItem, ColIndex) {
                        CsvString += ColItem + ",";
                    });
                    CsvString += "\r\n";
                });
                CsvString = "data:application/csv," + encodeURIComponent(CsvString);
                var x = document.createElement("A");
                x.setAttribute("href", CsvString);
                x.setAttribute("download", "Credit balances.csv");
                document.body.appendChild(x);
                x.click();
            }).catch(err => {
            console.log(err);
        });
    }
</script>

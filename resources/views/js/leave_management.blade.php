<script>
    const leave_credits_modal = new bootstrap.Modal(document.getElementById('leave_credits_modal'));
    let page = 0;
    $(document).ready(function () {
        $("body").on('keyup', ".vl_bal_input", function (e) {
            if (e.key === 'Enter' || e.keyCode === 13) {
                toastr.success("VL Balance updated", "Information");
            }
        });

        $("#btn_bulk_update").click(function () {
            $("#vl_bal").val("0.00");
            $("#sl_bal").val("0.00");
            $("#cto_bal").val("0.00");
            $("#fl_bal").val("0.00");
            $("#spl_bal").val("0.00");
            leave_credits_modal.toggle();
        });

        $("#btn_generate_report").click(function () {
            exportToCsv();
        });

        $("#btn_save_bulk").click(function () {
            let is_valid = true;
            $(".is-invalid").removeClass("is-invalid");
            const vl_bal = $("#vl_bal").val();
            const sl_bal = $("#sl_bal").val();
            const cto_bal = $("#cto_bal").val();
            const fl_bal = $("#fl_bal").val();
            const spl_bal = $("#spl_bal").val();

            if (vl_bal == "") {
                $("#vl_bal").addClass("is-invalid");
                is_valid = false;
                toastr.error("Please fill in vl bal", "Ooops");
            }

            if (sl_bal == "") {
                $("#sl_bal").addClass("is-invalid");
                is_valid = false;
                toastr.error("Please fill in sl_bal bal", "Ooops");
            }

            if (cto_bal == "") {
                $("#cto_bal").addClass("is-invalid");
                is_valid = false;
                toastr.error("Please fill in cto_bal bal", "Ooops");
            }

            if (fl_bal == "") {
                $("#fl_bal").addClass("is-invalid");
                is_valid = false;
                toastr.error("Please fill in fl_bal bal", "Ooops");
            }

            if (spl_bal == "") {
                $("#vl_bal").addClass("is-invalid");
                is_valid = false;
                toastr.error("Please fill in spl_bal bal", "Ooops");
            }

            if (!is_valid) return;

            const params = {
                vl_bal: vl_bal,
                sl_bal: sl_bal,
                cto_bal: cto_bal,
                fl_bal: fl_bal,
                spl_bal: spl_bal
            }

            $(this).prop("disabled", true);
            $(this).html(`<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>Loading...`)
            fetch('{{ route('bulkUpdateCredits') }}', {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                body: JSON.stringify(params)
            })
                .then(res => res.json())
                .then(data => {
                    $("#btn_save_bulk").prop("disabled", false);
                    $("#btn_save_bulk").html("UPDATE");
                    if (data.code == 500) {
                        toastr.error(data.message, "Ooops");
                        return;
                    }

                    toastr.success(data.message, "Information");
                    getEmployeeCredits();
                }).catch(err => {
                console.log(err);
            })
        });

        $("#btn_next").click(function () {
            page++;
            getEmployeeCredits();
            $("#btn_prev").removeClass("d-none");
        });

        $("#btn_prev").click(function () {
            if (page == 0) return;
            if (page == 1) {
                $("#btn_prev").addClass("d-none");
            }
            page--;
            getEmployeeCredits();
        });

        $("#btn_search_employee").click(function () {
            page = 0;
            getEmployeeCredits();
        });

        getEmployeeCredits();
    });

    function getEmployeeCredits() {
        const search_fname = $("#search_fname").val().trim();
        const search_lname = $("#search_fname").val().trim();

        $("#btn_next").prop("disabled", true);
        $("#btn_prev").prop("disabled", true);
        $("#employee_list").empty();
        $("#employee_list").append("<tr><td colspan='7'>Loading data, please wait...</td></tr>");

        fetch('{{ route('getEmployeeCredits') }}?search_fname=' + search_fname +
            "&seach_lname=" + search_lname +
            "&page=" + page, {
            method: "GET"
        })
            .then(res => res.json())
            .then(data => {
                $("#btn_next").prop("disabled", false);
                $("#btn_prev").prop("disabled", false);
                $("#pagination_container").addClass("d-none")
                $("#employee_list").empty();
                $("#btn_next").addClass("d-none");
                $("#employee_list").empty();
                if (data.results.length < 1) {
                    $("#employee_list").append("<tr><td colspan='6'>No record found</td></tr>");
                    return;
                }

                $("#pagination_container").removeClass("d-none")
                let max_visible = data.results.length;
                if (max_visible == 11) {
                    max_visible = 10;
                    $("#btn_next").removeClass("d-none");
                }

                $("#page_items_count").text(((page * 10) + 1) + " - " + ((page * 10) + max_visible));
                for (let i = 0; i < max_visible; i++) {
                    const it = data.results[i];
                    const vl_bal = (it.vl_bal) ? it.vl_bal : "0.00";
                    const sl_bal = (it.sl_bal) ? it.sl_bal : "0.00";
                    const cto_bal = (it.cto_bal) ? it.cto_bal : "0.00";
                    const fl_bal = (it.fl_bal) ? it.fl_bal : "0.00";
                    const spl_bal = (it.spl_bal) ? it.spl_bal : "0.00";

                    let tr = `<tr id="balance_id_` + it.id + `">`;
                    tr += `<td>` + it.fname + " " + it.mname + " " + it.lname + `</td>`;
                    tr += `<td><input class="form-control text-center vl_bal_input" type='number' value="` + vl_bal + `"/></td>`;
                    tr += `<td><input class="form-control text-center sl_bal_input" type='number' value="` + sl_bal + `"/></td>`;
                    tr += `<td><input class="form-control text-center cto_bal_input" type='number' value="` + cto_bal + `"/></td>`;
                    tr += `<td><input class="form-control text-center fl_bal_input" type='number' value="` + fl_bal + `"/></td>`;
                    tr += `<td><input class="form-control text-center spl_bal_input" type='number' value="` + spl_bal + `"/></td>`;
                    tr += `<td><button class="btn btn-sm btn-success" onClick="saveBalances(` + it.id + `)">SAVE</button></td>`;
                    $("#employee_list").append(tr);
                }
            }).catch(err => {
            console.log(err);
        });
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

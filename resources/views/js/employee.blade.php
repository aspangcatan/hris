@include('js.personnel')
@include('js.address')
@include('js.family_background')
@include('js.education')
@include('js.civil_service')
@include('js.work')
@include('js.voluntary')
@include('js.learning')
@include('js.reference')
@include('js.other_information')
@include('js.survey')
@include('js.service_record')
@include('js.service_record_upload')
@include('js.leave_credits')
<script>
    const CAN_MANAGE_LEAVE_CREDITS = @json(permission_check('leave.manage_credits'));

    const SURVEYS = [
        {question: "within the third degree?", id: "survey_1"},
        {question: "within the fourth degree (for Local Government Unit - Career Employees)?", id: "survey_2"},
        {question: "Have you ever been found guilty of any administrative offense?", id: "survey_3"},
        {question: "Have you been criminally charged before any court?", id: "survey_4"},
        {
            question: "Have you ever been convicted of any crime or violation of any law, decree, ordinance or regulation by any court or tribunal?",
            id: "survey_5"
        },
        {
            question: "Have you ever been separated from the service in any of the following modes: resignation, retirement, dropped from the rolls, dismissal, termination, end of term, finished contract or phased out (abolition) in the public or private sector?",
            id: "survey_6"
        },
        {
            question: "Have you ever been a candidate in a national or local election held within the last year (except Barangay election)?",
            id: "survey_7"
        },
        {
            question: "Have you resigned from the government service during the three (3)-month period before the last election to promote/actively campaign for a national or local candidate?",
            id: "survey_8"
        },
        {
            question: "Have you acquired the status of an immigrant or permanent resident of another country?",
            id: "survey_9"
        },
        {question: "Are you a member of any indigenous group?", id: "survey_10"},
        {question: "Are you a person with disability?", id: "survey_11"},
        {question: "Are you a solo parent?", id: "survey_12"},
    ];

    const employee_modal = new bootstrap.Modal(document.getElementById('employee_modal'));
    const service_records_modal = new bootstrap.Modal(document.getElementById('service_records_modal'));
    const print_service_modal = new bootstrap.Modal(document.getElementById('print_service_modal'));

    let employee_id = 0;
    let service_id = 0;
    let page = 1;
    let employee_last_page = 1;
    //FLAGS
    let is_personal_loaded = false;
    let is_address_loaded = false;
    let is_family_loaded = false;
    let is_education_loaded = false;
    let is_civil_loaded = false;
    let is_work_loaded = false;
    let is_voluntary_loaded = false;
    let is_learning_loaded = false;
    let is_other_loaded = false;
    let is_reference_loaded = false;
    let is_survey_loaded = false;
    let is_service_loaded = false;
    let payroll_catalog_loaded = false;
    let catalogEarnings = [];
    let catalogDeductions = [];

    $(document).ready(function () {
        $("input[name='citizenship']").change(function () {
            if ($(this).next().text().trim() == "Filipino") {
                $(".dual-citizenship-container").addClass("d-none");
            } else {
                $(".dual-citizenship-container").removeClass("d-none");
            }
        });

        $("body").on("click", ".credit-edit-btn", function () {
            const row = $(this).closest(".leave-credit-row");
            row.addClass("is-editing");
            row.find(".leave-credit-input").trigger("focus").select();
        });

        $("body").on("input", ".leave-credit-input", function () {
            const input = $(this);
            const row = input.closest(".leave-credit-row");
            const saveButton = row.find(".leave-credit-save-btn");
            const currentValue = parseFloat(input.val() || 0);
            const originalValue = parseFloat(input.data("original") || 0);

            if (currentValue < 0) {
                input.addClass("is-invalid");
                saveButton.prop("disabled", true);
                return;
            }
            input.removeClass("is-invalid");
            saveButton.prop("disabled", currentValue === originalValue);
        });

        $("body").on("click", ".leave-credit-cancel-btn", function () {
            const row = $(this).closest(".leave-credit-row");
            const input = row.find(".leave-credit-input");
            const originalValue = parseFloat(input.data("original") || 0);

            input.val(originalValue.toFixed(3));
            row.find(".leave-credit-save-btn").prop("disabled", true);
            row.removeClass("is-editing");
        });

        $("body").on("click", ".leave-credit-save-btn", async function () {
            const button = $(this);
            const row = button.closest(".leave-credit-row");
            const input = row.find(".leave-credit-input");
            const leaveId = row.data("leaveId");
            const value = parseFloat(input.val() || 0);

            if (value < 0) {
                toastr.error("Credit value cannot be negative.", "Invalid");
                return;
            }

            button.prop("disabled", true);

            const response = await fetch('{{ route('insertUpdateBaseCredit') }}', {
                method: "PUT",
                headers: {
                    "Content-Type": "application/json",
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                body: JSON.stringify({
                    employee_id: employee_id,
                    leave_id: leaveId,
                    based_credit: value
                })
            });

            try {
                const data = await response.json();
                if (response.ok) {
                    const formattedValue = formatLeaveCreditValue(value);

                    input.val(formattedValue);
                    input.data("original", value);
                    row.find(".leave-credit-value").text(formattedValue);
                    row.find(".leave-credit-status").text("Saved");
                    row.removeClass("is-editing");
                    toastr.success(data.message, "Information");
                } else {
                    button.prop("disabled", false);
                    toastr.error(data.error || data.message || 'Something went wrong, please try again');
                }
            } catch (error) {
                button.prop("disabled", false);
                console.error('Error parsing JSON response', error);
                toastr.error('Something went wrong, please try again');
            }
        });

        $("#btn_add_service_record").click(function () {
            service_id = 0;
            $("#service_error").addClass("d-none");
            $(".is-invalid").removeClass("is-invalid");
            $(".error_list").empty();
            $("#service_from").val("");
            $("#service_to").val("");
            $("#service_present").prop("checked", false).trigger("change");
            $("#service_designation").val("");
            $("#service_status").val("");
            $("#service_salary").val("");
            $("#service_office").val("");
            $("#service_leave").val("");
            $("#service_separation").val("");
            service_records_modal.toggle();
        });

        $("#service_present").change(function () {
            const is_present = $(this).is(":checked");
            $("#service_to").prop("disabled", is_present);
            if (is_present) {
                $("#service_to").val("").removeClass("is-invalid");
            }
        });

        $("#btn_search").click(function () {
            page = 1;
            employee_id = 0;
            const fname = $("#search_fname").val().trim();
            const lname = $("#search_lname").val().trim();
            const no_employee_no = $("#filter_no_employee_no").is(":checked") ? 1 : 0;
            getEmployee({fname: fname, lname: lname, page: page, no_employee_no: no_employee_no});
        });

        $("#search_fname, #search_lname").on("keypress", function (e) {
            if (e.which === 13) {
                e.preventDefault();
                $("#btn_search").click();
            }
        });

        $("body").on("click", "#pagination_links .page-link[data-page]", function (e) {
            e.preventDefault();
            const targetPage = parseInt($(this).attr("data-page"), 10);
            if (!targetPage || targetPage === page) return;
            if (targetPage < 1 || targetPage > employee_last_page) return;

            page = targetPage;
            const fname = $("#search_fname").val().trim();
            const lname = $("#search_lname").val().trim();
            const no_employee_no = $("#filter_no_employee_no").is(":checked") ? 1 : 0;
            getEmployee({fname: fname, lname: lname, page: page, no_employee_no: no_employee_no});
        });

        $("#filter_no_employee_no, #filter_reo_pending").change(function () {
            page = 1;
            employee_id = 0;
            const fname = $("#search_fname").val().trim();
            const lname = $("#search_lname").val().trim();
            const no_employee_no = $("#filter_no_employee_no").is(":checked") ? 1 : 0;
            getEmployee({fname: fname, lname: lname, page: page, no_employee_no: no_employee_no});
        });

        $("#btn_save_payroll").click(function () {
            let isValid = true;
            let params = {};

            params["userid"] = employee_id;

            const numericFields = ["salary_grade", "step", "basic_salary"];
            numericFields.forEach(field => {
                const input = $("#" + field);
                if (!input.length) {
                    return;
                }
                const value = parseFloat(input.val());
                if (isNaN(value) || value < 0 || ((field === "salary_grade" || field === "step") && value < 1)) {
                    input.addClass("is-invalid");
                    isValid = false;
                } else {
                    input.removeClass("is-invalid");
                    params[field] = (field === "salary_grade" || field === "step")
                        ? parseInt(value)
                        : parseFloat(value).toFixed(2);
                }
            });

            if (!isValid) {
                alert("All input fields must have a valid number (0.00 or higher).");
                return;
            }

            params["effective_date"] = $("#salary_effective_date").val() || null;
            params["components"] = collectDynamicComponents();

            fetch('{{ route('savePayrollSettings') }}', {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json",
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    body: JSON.stringify(params)
                }
            )
                .then(res => res.json().then(data => ({ok: res.ok, data})))
                .then(({ok, data}) => {
                    if (!ok) {
                        toastr.error(data.message || "Failed to save payroll settings.", "Error");
                        return;
                    }
                    toastr.success(data.message, 'Information');
                }).catch(err => {
                console.log(err);
            });
        });

        $("#btn_save_personal").click(function () {
            let is_valid = true;
            $("#personal_information_error").addClass("d-none");
            $(".is-invalid").removeClass("is-invalid");
            $(".error_list").empty();

            const fname = $("#fname").val().trim();
            const mname = $("#mname").val().trim();
            const lname = $("#lname").val().trim();
            const suffix = $("#suffix").val().trim();
            const birthdate = $("#birthdate").val();
            const birthplace = $("#birthplace").val().trim();
            const sex = $("#sex").val();
            const civil_status = $("#civil_status").val();
            const height = $("#height").val().trim();
            const weight = $("#weight").val().trim();
            const blood_type = $("#blood_type").val();
            const gsis_id_no = $("#gsis_id_no").val().trim();
            const umid_id_no = $("#umid_id_no").val().trim();
            const pagibig_id_no = $("#pagibig_id_no").val().trim();
            const philhealth_no = $("#philhealth_no").val().trim();
            const sss_no = $("#sss_no").val().trim();
            const philsys_no = $("#philsys_no").val().trim();
            const tin_no = $("#tin_no").val().trim();
            const employee_no = $("#employee_no").val().trim();
            const employee_type = $("#employee_type").val();
            const date_hired = $("#date_hired").val();
            const date_resigned = $("#date_resigned").val();
            const citizenship = $("input[name='citizenship']:checked").next().text().trim();
            let citizenship_type = null;
            let citizenship_country = null;
            if (citizenship == "Dual Citizenship") {
                citizenship_type = $("input[name='citizenship_type']:checked").next().text().trim();
                citizenship_country = $("#citizenship_country").val().trim();
            }
            const telephone_no = $("#telephone_no").val().trim();
            const mobile_no = $("#mobile_no").val().trim();
            const email_address = $("#email_address").val().trim();
            const issued_id = $("#issued_id").val().trim();
            const issued_id_no = $("#issued_id_no").val().trim();
            const issued_date = $("#issued_date").val().trim();

            if (fname == "") {
                is_valid = false;
                $(".error_list").append("<li>Firstname is required</li>");
                $("#fname").addClass("is-invalid");
            }

            if (lname == "") {
                is_valid = false;
                $(".error_list").append("<li>Lastname is required</li>");
                $("#lname").addClass("is-invalid");
            }

            if (!is_valid) {
                $("#personal_information_error").removeClass("d-none");
                $("#employee_modal").animate({scrollTop: $('#employee_modal #personal_information_error').offset().top}, 200);
                return;
            }

            const params = {
                user_id: employee_id,
                fname: fname,
                mname: mname,
                lname: lname,
                suffix: suffix,
                birthdate: birthdate,
                birthplace: birthplace,
                sex: sex,
                civil_status: civil_status,
                height: height,
                weight: weight,
                blood_type: blood_type,
                gsis_id_no: gsis_id_no,
                umid_id_no: umid_id_no,
                pagibig_id_no: pagibig_id_no,
                philhealth_no: philhealth_no,
                sss_no: sss_no,
                philsys_no: philsys_no,
                tin_no: tin_no,
                employee_no: employee_no,
                employee_type: employee_type,
                date_hired: date_hired,
                date_resigned: date_resigned,
                citizenship: citizenship,
                citizenship_type: citizenship_type,
                citizenship_country: citizenship_country,
                telephone_no: telephone_no,
                mobile_no: mobile_no,
                email_address: email_address,
                issued_id: issued_id,
                issued_id_no: issued_id_no,
                issued_date: issued_date
            };

            insertUpdateEmployee(params).then(data => {
                if (!data || data.code != 200) {
                    return;
                }

                const suffix_text = suffix ? (" " + suffix) : "";
                $("#employee_name").text((fname + " " + lname + suffix_text).trim());

                const current_row = $("#employee_" + employee_id);
                if (current_row.length) {
                    current_row.find("td:eq(1)").text(fname);
                    current_row.find("td:eq(2)").text(mname);
                    current_row.find("td:eq(3)").text(lname);
                    current_row.find("td:eq(4)").text(suffix);
                } else {
                    const current_fname = $("#search_fname").val().trim();
                    const current_lname = $("#search_lname").val().trim();
                    const no_employee_no = $("#filter_no_employee_no").is(":checked") ? 1 : 0;
                    getEmployee({fname: current_fname, lname: current_lname, page: page, no_employee_no: no_employee_no});
                }
            });
        });

        $("#btn_save_service").click(function () {
            let is_valid = true;
            $("#service_error").addClass("d-none");
            $(".is-invalid").removeClass("is-invalid");
            $(".error_list").empty();

            const date_from = $("#service_from").val();
            const date_to = $("#service_to").val();
            const current_work = $("#service_present").is(":checked") ? 1 : 0;
            const designation = $("#service_designation").val().trim();
            const appointment_status = $("#service_status").val();
            const monthly_salary = $("#service_salary").val();
            const office_assignment = $("#service_office").val().trim();
            const leave_absence = $("#service_leave").val();
            const separation = $("#service_separation").val().trim();

            if (date_from == "") {
                is_valid = false;
                $(".error_list").append("<li>Date from is required</li>");
                $("#service_from").addClass("is-invalid");
            }

            if (date_to == "" && current_work != 1) {
                is_valid = false;
                $(".error_list").append("<li>Date to is required (or mark as Present)</li>");
                $("#service_to").addClass("is-invalid");
            }

            if (designation == "") {
                is_valid = false;
                $(".error_list").append("<li>Designation is required</li>");
                $("#service_designation").addClass("is-invalid");
            }

            if (appointment_status == "") {
                is_valid = false;
                $(".error_list").append("<li>Appointment status is required</li>");
                $("#service_status").addClass("is-invalid");
            }

            if (monthly_salary == "") {
                is_valid = false;
                $(".error_list").append("<li>Monthly salary is required</li>");
                $("#service_salary").addClass("is-invalid");
            }

            if (office_assignment == "") {
                is_valid = false;
                $(".error_list").append("<li>Office of assignment is required</li>");
                $("#service_office").addClass("is-invalid");
            }

            if (!is_valid) {
                $("#service_error").removeClass("d-none");
                $("#service_records_modal").animate({scrollTop: $('#service_records_modal #service_error').offset().top}, 200);
                return;
            }

            const params = {
                id: service_id,
                user_id: employee_id,
                date_from: date_from,
                date_to: date_to,
                current_work: current_work,
                designation: designation,
                appointment_status: appointment_status,
                monthly_salary: monthly_salary,
                office_assignment: office_assignment,
                leave_absence: leave_absence,
                separation: separation
            }

            insertUpdateServiceRecords(params);
        });


        $("#btn_print_service_records").click(function () {
            $("#records_first_rows").val("15");
            $("#records_other_rows").val("15");
            $("#page_override_list").empty();
            $("#service_date_issued").val(moment().format("YYYY-MM-DD"));
            print_service_modal.toggle();
        });

        $("#btn_add_page_override").click(function () {
            const nextPage = Math.max(1, ...$("#page_override_list .override-page").map(function () {
                return parseInt($(this).val(), 10) || 1;
            }).get()) + 1;

            $("#page_override_list").append(`
                <div class="page-override-row flex items-center gap-2">
                    <span class="text-sm text-slate-600">Page</span>
                    <input type="number" min="2" class="form-control premium-input override-page" style="width: 90px" value="${nextPage}">
                    <span class="text-sm text-slate-600">rows</span>
                    <input type="number" min="1" class="form-control premium-input override-rows" style="width: 90px" value="15">
                    <button type="button" class="btn btn-action btn-delete btn-remove-override" title="Remove"><i class="bi bi-trash"></i></button>
                </div>`);
        });

        $("#page_override_list").on("click", ".btn-remove-override", function () {
            $(this).closest(".page-override-row").remove();
        });

        $("#btn_confirm_print_records").click(function () {
            const firstRows = parseInt($("#records_first_rows").val(), 10);
            const otherRows = parseInt($("#records_other_rows").val(), 10);
            if (!(firstRows >= 1) || !(otherRows >= 1)) {
                toastr.error("Rows on page 1 and other pages must be at least 1", "Oops");
                return;
            }

            let pageRowsQuery = "";
            let invalidOverride = false;
            $("#page_override_list .page-override-row").each(function () {
                const pageNo = parseInt($(this).find(".override-page").val(), 10);
                const pageRows = parseInt($(this).find(".override-rows").val(), 10);
                if (!(pageNo >= 2) || !(pageRows >= 1)) {
                    invalidOverride = true;
                    return false;
                }
                pageRowsQuery += "&page_rows[" + pageNo + "]=" + pageRows;
            });
            if (invalidOverride) {
                toastr.error("Custom pages need a page number of 2 or higher and at least 1 row", "Oops");
                return;
            }

            const dateIssued = $("#service_date_issued").val();
            if (dateIssued == "") {
                toastr.error("Please select a date issued", "Oops");
                return;
            }

            const paperSize = $("#service_paper_size").val();

            window.open(
                '{{ route('printServiceRecord') }}?id=' + employee_id + "&first_rows=" + firstRows + "&other_rows=" + otherRows + pageRowsQuery + "&date_issued=" + dateIssued + "&paper_size=" + paperSize,
                '_blank' // <- This is what makes it open in a new window.
            );
        });

        $("#btn_print_pds").click(function () {
            window.open(
                '{{ route('printPds') }}?id=' + employee_id,
                '_blank' // <- This is what makes it open in a new window.
            );
        });

        $("#btn_print_pds_2025").click(function () {
            window.open(
                '{{ route('printPds') }}?id=' + employee_id + '&pds_version=2025',
                '_blank' // <- This is what makes it open in a new window.
            );
        });

        $("button.nav-link").click(function () {
            switch ($(this).attr("data-bs-target")) {
                case "#address":
                    getAddress(employee_id);
                    break;
                case "#family_background":
                    getFamilyBackground(employee_id)
                    break;
                case "#educational_background":
                    getEducation(employee_id)
                    break;
                case "#civil_service":
                    getCivilService(employee_id)
                    break;
                case "#work_experience":
                    getWork(employee_id)
                    break;
                case "#voluntary_work":
                    getVoluntary(employee_id)
                    break;
                case "#learning_development":
                    getLearning(employee_id)
                    break;
                case "#other_information":
                    getOtherInformation(employee_id)
                    break;
                case "#reference":
                    getReference(employee_id)
                    break;
                case "#survey":
                    getSurvey(employee_id);
                    break;
                case "#service_records":
                    getServiceRecords(employee_id);
                    break;
                case "#leave_credits":
                    getCredits(employee_id);
                    break;
                case "#payroll":
                    getPayrollSettings(employee_id);
                    break;
            }
        });

        const no_employee_no = $("#filter_no_employee_no").is(":checked") ? 1 : 0;
        getEmployee({fname: "", lname: "", page: page, no_employee_no: no_employee_no});
    });

     function getPayrollSettings(id) {
        employee_id = id;
        $("#payroll input").val("0.00"); // Reset all inputs
        // Fetch existing employee payroll data
        fetch('{{ route('getPayrollSettings') }}?userid=' + id)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Fill inputs with the retrieved data
                    for (const [key, value] of Object.entries(data.data)) {
                        const input = $(`#${key}`); // Select input using ID
                        if (input.length) {
                            input.val(key === 'salary_grade' || key === 'step' ? parseInt(value) : parseFloat(value).toFixed(2));
                        }
                    }
                }
            })
            .catch(error => {
                console.error("Error fetching data:", error);
                alert("An error occurred while fetching employee data.");
            });
    }

    function getCredits(id) {
        employee_id = id;
        $("#payroll input").val("0.00"); // Reset all inputs
        // Fetch leave credits and render table
        fetch('{{ route('getCreditBalances') }}?id=' + id, {method: "GET"})
            .then(response => response.json().then(data => ({ok: response.ok, data})))
            .then(({ok, data}) => {
                if (!ok) {
                    toastr.error(data.error || "Something went wrong, please try again");
                    return;
                }

                $("#leave_credits_list").empty();
                data.forEach(it => {
                    const formattedValue = formatLeaveCreditValue(it.based_credit);
                    let tr = "<tr class='leave-credit-row' data-leave-id='" + it.id + "'>";
                    tr += "<td><span class='leave-credit-title'>" + it.description + "</span></td>";
                    tr += "<td><span class='leave-credit-code'>" + (it.code || "leave") + "</span></td>";
                    tr += "<td>";
                    tr += "<div class='leave-credit-display'><span class='leave-credit-value'>" + formattedValue + "</span></div>";
                    if (CAN_MANAGE_LEAVE_CREDITS) {
                        tr += "<div class='input-group input-group-sm leave-credit-input-group d-none'>";
                        tr += "<input type='number' step='0.001' min='0' class='form-control text-center leave-credit-input' value='" + formattedValue + "' data-original='" + parseFloat(it.based_credit || 0) + "' />";
                        tr += "<button type='button' class='btn btn-success leave-credit-save-btn' disabled><i class='bi bi-check-lg'></i></button>";
                        tr += "<button type='button' class='btn btn-outline-secondary leave-credit-cancel-btn'><i class='bi bi-x-lg'></i></button>";
                        tr += "</div>";
                    }
                    tr += "</td>";
                    tr += "<td><div class='leave-credit-actions'><span class='leave-credit-status'>Current value</span>";
                    if (CAN_MANAGE_LEAVE_CREDITS) {
                        tr += "<button type='button' class='btn btn-sm btn-outline-primary credit-edit-btn'><i class='bi bi-pencil-square'></i> Edit</button>";
                    }
                    tr += "</div></td>";
                    tr += "</tr>";
                    $("#leave_credits_list").append(tr);
                });

                if (data.length === 0) {
                    $("#leave_credits_list").append("<tr><td colspan='4' class='leave-credit-empty py-4'>No leave credit records found.</td></tr>");
                }
            })
            .catch(error => {
                console.error("Error fetching leave credits:", error);
                toastr.error("Something went wrong, please try again");
            });
    }

    function formatLeaveCreditValue(value) {
        const numericValue = parseFloat(value || 0);
        return numericValue.toFixed(3);
    }

    function editService(id) {
        service_id = id;

        const date_from = $("#service_" + id + " td:eq(0)").text();
        const date_to = $("#service_" + id + " td:eq(1)").text();
        const designation = $("#service_" + id + " td:eq(2)").text();
        const status = $("#service_" + id + " td:eq(3)").text();
        const monthly_salary = $("#service_" + id + " td:eq(4)").text();
        const office_assignment = $("#service_" + id + " td:eq(5)").text();
        const leave_absence = $("#service_" + id + " td:eq(6)").text();
        const separation = $("#service_" + id + " td:eq(7)").text();

        $("#service_from").val(moment(date_from, "MM/DD/YYYY").format("YYYY-MM-DD"));
        const is_present = date_to.trim() == "Present";
        $("#service_present").prop("checked", is_present).trigger("change");
        $("#service_to").val(is_present ? "" : moment(date_to, "MM/DD/YYYY").format("YYYY-MM-DD"));
        $("#service_designation").val(designation);
        $("#service_status").val(status);
        $("#service_salary").val(monthly_salary);
        $("#service_office").val(office_assignment);
        $("#service_leave").val(leave_absence);
        $("#service_separation").val(separation);

        service_records_modal.toggle();
    }

    function editEmployee(id) {
        employee_id = id;
        is_personal_loaded = false;
        is_address_loaded = false;
        is_family_loaded = false;
        is_education_loaded = false;
        is_civil_loaded = false;
        is_work_loaded = false;
        is_voluntary_loaded = false;
        is_learning_loaded = false;
        is_other_loaded = false;
        is_reference_loaded = false;
        is_survey_loaded = false;
        is_service_loaded = false;
        employee_modal.toggle();

        $(".tab-pane").removeClass("active");
        $(".nav-link.active").removeClass("active");
        $("#employee_modal .nav-link:eq(0)").addClass("active");
        $("#personal_information").addClass("active");
        $("#personal_information").removeClass("fade");

        $("#employee_modal input").val("");
        $("#employee_modal input[type='checkbox']").prop("checked", false);
        $("#employee_modal input[type='radio']").prop("checked", false);
        $("#employee_modal select").val("");
        $("#employee_modal table>tbody").empty();

        getEmployeeDetails(employee_id);
        loadPayrollSettingsForEmployee(employee_id);
        const image = $("#employee_" + id + " td:eq(0) img").attr("src");
        const fname = $("#employee_" + id + " td:eq(1)").text().trim();
        const lname = $("#employee_" + id + " td:eq(3)").text().trim();
        $("#employee_img").attr("src", image);
        $("#employee_name").text(fname + " " + lname);
    }

    function loadPayrollSettingsForEmployee(id) {
        $("#salary_grade, #step, #basic_salary").val("0.00");
        renderDynamicComponents([]);
        fetch('{{ route('getPayrollSettings') }}?userid=' + id)
            .then(response => response.json())
            .then(data => {
                if (!data.success) {
                    return;
                }
                for (const [key, value] of Object.entries(data.data || {})) {
                    const input = $(`#${key}`);
                    if (input.length) {
                        input.val(key === 'salary_grade' || key === 'step' ? parseInt(value) : parseFloat(value).toFixed(2));
                    }
                }
                ensureComponentCatalog().then(() => {
                    renderDynamicComponents(data.components || []);
                });
            })
            .catch(error => {
                console.error("Error fetching payroll settings:", error);
            });
    }

    function loadComponentCatalog() {
        return fetch('{{ route('getPayrollComponents') }}')
            .then(res => res.json())
            .then(data => {
                if (!data.success) {
                    return;
                }
                const items = data.data || [];
                catalogEarnings = items.filter(item => item.type === 'earning');
                catalogDeductions = items.filter(item => item.type === 'deduction');
                payroll_catalog_loaded = true;
            })
            .catch(err => console.error(err));
    }

    function ensureComponentCatalog() {
        if (payroll_catalog_loaded) {
            return Promise.resolve();
        }
        return loadComponentCatalog();
    }

    function renderDynamicComponents(components) {
        const $earningsBody = $("#dynamic_components_earnings_body");
        const $deductionsBody = $("#dynamic_components_deductions_body");
        $earningsBody.empty();
        $deductionsBody.empty();

        const employeeComponentMap = {};
        (components || []).forEach(component => {
            employeeComponentMap[component.code] = component;
        });

        if (!catalogEarnings.length) {
            $earningsBody.append("<tr><td colspan='8' class='text-muted text-center py-3'>No earnings configured.</td></tr>");
        } else {
            catalogEarnings.forEach(component => {
                const override = employeeComponentMap[component.code] || {};
                addDynamicComponentRow(component, override, $earningsBody);
            });
        }

        if (!catalogDeductions.length) {
            $deductionsBody.append("<tr><td colspan='8' class='text-muted text-center py-3'>No deductions configured.</td></tr>");
            return;
        }

        catalogDeductions.forEach(component => {
            const override = employeeComponentMap[component.code] || {};
            addDynamicComponentRow(component, override, $deductionsBody);
        });
    }

    function addDynamicComponentRow(component = {}, override = {}, $body) {
        const code = component.code || "";
        const name = component.name || "";
        const isGlobal = !!component.is_global;
        const isPercentage = component.calculation_type === "percentage";
        const defaultAmount = component.default_amount !== undefined && component.default_amount !== null
            ? parseFloat(component.default_amount).toFixed(2)
            : "0.00";
        const defaultDisplay = !isGlobal
            ? "&mdash;"
            : (isPercentage
                ? `${parseFloat(component.rate_percent || 0)}% of ${component.base_type === "basic_salary" ? "Basic Salary" : (component.base_component_code || "?")}`
                : defaultAmount);
        const overrideAmount = override.amount_override !== undefined && override.amount_override !== null
            ? parseFloat(override.amount_override).toFixed(2)
            : "";
        const rateOverride = override.rate_percent_override !== undefined && override.rate_percent_override !== null
            ? parseFloat(override.rate_percent_override)
            : "";
        const excluded = override.is_excluded ? "checked" : "";
        const effectiveFrom = override.effective_from || "";
        const effectiveTo = override.effective_to || "";
        const rateOverrideCell = isPercentage
            ? `<input type="number" class="form-control form-control-sm component-rate-override" step="0.001" value="${rateOverride}">`
            : "&mdash;";

        const row = `
            <tr data-code="${code}" data-default="${defaultAmount}">
                <td>
                    <div class="fw-semibold">${name}</div>
                    <div class="small text-muted">${code}</div>
                </td>
                <td>${isGlobal ? "Yes" : "No"}</td>
                <td>${defaultDisplay}</td>
                <td><input type="number" class="form-control form-control-sm component-amount" value="${overrideAmount}"></td>
                <td>${rateOverrideCell}</td>
                <td class="text-center">
                    <input type="checkbox" class="form-check-input component-exclude" ${excluded}>
                </td>
                <td><input type="date" class="form-control form-control-sm component-effective-from" value="${effectiveFrom}"></td>
                <td><input type="date" class="form-control form-control-sm component-effective-to" value="${effectiveTo}"></td>
            </tr>`;

        $body.append(row);
    }

    function collectDynamicComponents() {
        const components = [];
        components.push(...collectDynamicOverrides($("#dynamic_components_earnings_body tr"), catalogEarnings));
        components.push(...collectDynamicOverrides($("#dynamic_components_deductions_body tr"), catalogDeductions));
        return components;
    }

    function collectDynamicOverrides($rows, sourceComponents) {
        const components = [];

        $rows.each(function () {
            const $row = $(this);
            const code = $row.data("code");
            if (!code) {
                return;
            }
            const defaultAmount = parseFloat($row.data("default") || "0");
            const overrideRaw = $row.find(".component-amount").val();
            const overrideAmount = overrideRaw === "" ? null : parseFloat(overrideRaw);
            const rateOverrideRaw = $row.find(".component-rate-override").val();
            const rateOverride = (rateOverrideRaw === undefined || rateOverrideRaw === "") ? null : parseFloat(rateOverrideRaw);
            const excluded = $row.find(".component-exclude").is(":checked");
            const effectiveFrom = $row.find(".component-effective-from").val();
            const effectiveTo = $row.find(".component-effective-to").val();

            const hasOverride = overrideAmount !== null && !isNaN(overrideAmount) && overrideAmount !== defaultAmount;
            const hasRateOverride = rateOverride !== null && !isNaN(rateOverride);
            if (!excluded && !hasOverride && !hasRateOverride && !effectiveFrom && !effectiveTo) {
                return;
            }

            const component = sourceComponents.find(item => item.code === code);
            if (!component) {
                return;
            }

            components.push({
                code: component.code,
                name: component.name,
                type: component.type,
                taxable: component.taxable,
                is_global: !!component.is_global,
                default_amount: parseFloat(component.default_amount || 0),
                amount_override: hasOverride ? overrideAmount : null,
                rate_percent_override: hasRateOverride ? rateOverride : null,
                is_excluded: excluded,
                effective_from: effectiveFrom || null,
                effective_to: effectiveTo || null
            });
        });

        return components;
    }
</script>

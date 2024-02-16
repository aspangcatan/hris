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
<script>
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
    let page = 0;
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

    $(document).ready(function () {

        $("input[name='citizenship']").change(function () {
            if ($(this).next().text().trim() == "Filipino") {
                $(".dual-citizenship-container").addClass("d-none");
            } else {
                $(".dual-citizenship-container").removeClass("d-none");
            }
        });

        $("#btn_add_service_record").click(function () {
            service_records_modal.toggle();
        });

        $("#btn_search").click(function () {
            employee_id = 0;
            const fname = $("#search_fname").val().trim();
            const lname = $("#search_lname").val().trim();
            getEmployee({fname: fname, lname: lname, page: page});
        });

        $("#btn_next").click(function () {
            page++;
            const fname = $("#search_fname").val().trim();
            const lname = $("#search_lname").val().trim();
            getEmployee({fname: fname, lname: lname, page: page});
            $("#btn_prev").removeClass("d-none");
        });

        $("#btn_prev").click(function () {
            if (page == 0) return;
            if (page == 1) {
                $("#btn_prev").addClass("d-none");
            }
            page--;
            const fname = $("#search_fname").val().trim();
            const lname = $("#search_lname").val().trim();
            getEmployee({fname: fname, lname: lname, page: page});
        });

        $("#btn_save_personal").click(function () {
            const employee_type = $("#employee_type").val();
            const date_hired = $("#date_hired").val();
            const date_resigned = $("#date_resigned").val();
            if (employee_type == "") {
                alert("Please specify Employee status");
                return;
            }

            updateEmployeeStatus({
                user_id: employee_id,
                employee_type: employee_type,
                date_hired: date_hired,
                date_resigned: date_resigned
            })
        });

        $("#btn_save_service").click(function () {
            let is_valid = true;
            $("#service_error").addClass("d-none");
            $(".is-invalid").removeClass("is-invalid");
            $(".error_list").empty();

            const date_from = $("#service_from").val();
            const date_to = $("#service_to").val();
            const current_work = 0;
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

            if (date_to == "") {
                is_valid = false;
                $(".error_list").append("<li>Date to is required</li>");
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
            $("#records_row").val("15");
            print_service_modal.toggle();
        });

        $("#btn_confirm_print_records").click(function () {
            const rows = $("#records_row").val();
            if (rows == "") {
                return "If blank kindly set back to 15";
            }

            window.open(
                '{{ route('printServiceRecord') }}?id=' + employee_id + "&rows=" + rows,
                '_blank' // <- This is what makes it open in a new window.
            );
        });

        $("#btn_print_pds").click(function () {
            window.open(
                '{{ route('printPds') }}?id=' + employee_id,
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
            }
        });

        getEmployee({fname: "", lname: "", page: page});
    });

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
        $("#service_to").val(moment(date_to, "MM/DD/YYYY").format("YYYY-MM-DD"));
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
        const image = $("#employee_" + id + " td:eq(0) img").attr("src");
        const fname = $("#employee_" + id + " td:eq(1)").text().trim();
        const lname = $("#employee_" + id + " td:eq(3)").text().trim();
        $("#employee_img").attr("src", image);
        $("#employee_name").text(fname + " " + lname);
    }
</script>

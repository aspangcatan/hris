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
<script>
    const employee_modal = new bootstrap.Modal(document.getElementById('employee_modal'));
    let employee_id = 0;
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

    $(document).ready(function () {
        $("#personal_information").addClass("active");
        $("#personal_information").removeClass("fade");

        $("#btn_search").click(function (){
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
            if (employee_type == "") {
                alert("Please specify Employee status");
                return;
            }

            updateEmployeeStatus({user_id: employee_id, employee_type: employee_type})
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
                    getAddress();
                    break;
                case "#family_background":
                    getFamilyBackground()
                    break;
                case "#educational_background":
                    getEducation()
                    break;
                case "#civil_service":
                    getCivilService()
                    break;
                case "#work_experience":
                    getWork()
                    break;
                case "#voluntary_work":
                    getVoluntary()
                    break;
                case "#learning_development":
                    getLearning()
                    break;
                case "#other_information":
                    getOtherInformation()
                    break;
                case "#reference":
                    getReference()
                    break;
                case "#survey":
                    getSurvey();
                    break;
            }
        });

        getEmployee({fname: "", lname: "", page: page});

    });

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
        getEmployeeDetails(employee_id);
        const image = $("#employee_" + id + " td:eq(0) img").attr("src");
        const fname = $("#employee_" + id + " td:eq(1)").text().trim();
        const lname = $("#employee_" + id + " td:eq(3)").text().trim();
        $("#employee_img").attr("src", image);
        $("#employee_name").text(fname + " " + lname);
    }
</script>

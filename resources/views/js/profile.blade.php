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
    toastr.options = {
        "positionClass": "toast-bottom-right",
    }

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

    const children_modal = new bootstrap.Modal(document.getElementById('children_modal'));
    const education_modal = new bootstrap.Modal(document.getElementById('education_modal'));
    const civil_service_modal = new bootstrap.Modal(document.getElementById('civil_service_modal'));
    const work_experience_modal = new bootstrap.Modal(document.getElementById('work_experience_modal'));
    const voluntary_modal = new bootstrap.Modal(document.getElementById('voluntary_modal'));
    const learning_modal = new bootstrap.Modal(document.getElementById('learning_modal'));
    const reference_modal = new bootstrap.Modal(document.getElementById('reference_modal'));
    const other_information_modal = new bootstrap.Modal(document.getElementById('other_information_modal'));
    const print_modal = new bootstrap.Modal(document.getElementById('print_modal'));

    let children_id = 0;
    let education_id = 0;
    let civil_service_id = 0;
    let work_id = 0;
    let voluntary_id = 0;
    let learning_id = 0;
    let reference_id = 0;
    let skill_id = 0;
    let recognition_id = 0;
    let membership_id = 0;
    let other_type = "";

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
        //INITIALIZE PDS NAV TABS
        $("#personal_information").addClass("active");
        $("#personal_information").removeClass("fade");

        $("input[name='citizenship']").change(function () {
            if ($(this).next().text().trim() == "Filipino") {
                $(".dual-citizenship-container").addClass("d-none");
            } else {
                $(".dual-citizenship-container").removeClass("d-none");
            }
        });

        $("#work_current_work").change(function () {
            $("#to_container").removeClass("d-none");
            if ($(this).val() == "1") {
                $("#to_container").addClass("d-none");
            }
        });

        $("#btn_print_pds").click(function () {
            $("#pds_date").val(moment().format("YYYY-MM-DD"));
            print_modal.toggle();
        });

        $("#btn_confirm_print").click(function () {
            let date = $("#pds_date").val();
            let sworn_date = $("#sworn_date").val();
            const experience_rows = $("#experience_rows").val();
            const learning_rows = $("#learning_rows").val();

            if (date == "") {
                alert("Indicate date");
                return;
            }

            if (sworn_date == "") {
                alert("Indicate sworn date");
                return;
            }

            date = moment(date).format("DD/MM/YYYY");
            sworn_date = moment(sworn_date).format("DD/MM/YYYY");
            window.open(
                '{{ route('printPds') }}?id=0&pds_date=' + date + "&experience_rows=" + experience_rows + "&learning_rows=" + learning_rows + "&sworn_date=" + sworn_date,
                '_blank' // <- This is what makes it open in a new window.
            );
        });

        $("#btn_add_children").click(function () {
            children_id = 0;
            $("#children_name").val("");
            $("#children_birthdate").val("");
            children_modal.toggle();
        });

        $("#btn_add_education").click(function () {
            education_id = 0;
            $("#education_level").val("");
            $("#school_name").val("");
            $("#education_degree").val("");
            $("#attendance_from").val("");
            $("#attendance_to").val("");
            $("#highest_level").val("");
            $("#year_graduated").val("");
            $("#scholarship").val("");
            education_modal.toggle();
        });

        $("#btn_add_civil_service").click(function () {
            civil_service_id = 0;
            $("#civil_service_career_service").val("");
            $("#civil_service_rating").val("");
            $("#civil_service_examination_date").val("");
            $("#civil_service_examination_end_date").val("");
            $("#civil_service_examination_place").val("");
            $("#civil_service_license_no").val("");
            $("#civil_service_license_expiry").val("");
            civil_service_modal.toggle();
        });

        $("#btn_add_work").click(function () {
            work_id = 0;
            $("#work_position").val("");
            $("#work_agency").val("");
            $("#work_from").val("");
            $("#work_to").val("");
            $("#work_salary").val("");
            $("#work_salary_grade").val("");
            $("#work_appointment_status").val("");
            $("#work_government").val("");
            $("#work_current_work").val("");
            work_experience_modal.toggle();
        });

        $("#btn_add_voluntary").click(function () {
            voluntary_id = 0;
            $("#voluntary_organization_address").val("");
            $("#voluntary_position").val("");
            $("#voluntary_working_from").val("");
            $("#voluntary_working_to").val("");
            $("#voluntary_number_hours").val("");
            voluntary_modal.toggle();
        });

        $("#btn_add_learning").click(function () {
            learning_id = 0;
            $("#learning_title").val("");
            $("#learning_attendance_from").val("");
            $("#learning_attendance_to").val("");
            $("#learning_number_hours").val("");
            $("#learning_type").val("");
            $("#learning_conducted_by").val("");
            learning_modal.toggle();
        });

        $("#btn_add_reference").click(function () {
            reference_id = 0;
            $("#reference_name").val("");
            $("#reference_address").val("");
            $("#reference_contact_no").val("");
            reference_modal.toggle();
        });

        $(".btn-add-other-information").click(function () {
            skill_id = 0;
            recognition_id = 0;
            membership_id = 0
            other_type = $(this).val();
            $("#other_information_description").val("");
            switch (other_type) {
                case "skill":
                    $("#other_information_title").text("Skills/Hobbies");
                    break;
                case "recognition":
                    $("#other_information_title").text("Non-Academic Distinctions");
                    break;
                case "membership":
                    $("#other_information_title").text("Association Membership");
                    break;
            }

            other_information_modal.toggle();
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
            const employee_status = $("#employee_status").val();
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
                $("#form_fname").addClass("is-invalid");
            }

            if (lname == "") {
                is_valid = false;
                $(".error_list").append("<li>Lastname is required</li>");
                $("#form_lname").addClass("is-invalid");
            }

            if (!is_valid) {
                $("#personal_information_error").removeClass("d-none");
                $("#edit_leave_modal").animate({scrollTop: $('#edit_leave_modal #personal_information_error').offset().top}, 200);
                return;
            }

            const params = {
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
                employee_status: employee_status,
                citizenship: citizenship,
                citizenship_type: citizenship_type,
                citizenship_country: citizenship_country,
                telephone_no: telephone_no,
                mobile_no: mobile_no,
                email_address: email_address,
                issued_id: issued_id,
                issued_id_no: issued_id_no,
                issued_date: issued_date
            }

            insertUpdateEmployee(params);
        });

        $("#btn_save_address").click(function () {
            let is_valid = true;
            $("#address_error").addClass("d-none");
            $(".is-invalid").removeClass("is-invalid");
            $(".error_list").empty();

            const residential_house = $("#residential_house").val().trim();
            const residential_street = $("#residential_street").val().trim();
            const residential_subdivision_village = $("#residential_subdivision_village").val().trim();
            const residential_barangay = $("#residential_barangay").val().trim();
            const residential_city = $("#residential_city").val().trim();
            const residential_province = $("#residential_province").val().trim();
            const residential_zipcode = $("#residential_zipcode").val().trim();

            const permanent_house = $("#permanent_house").val().trim();
            const permanent_street = $("#permanent_street").val().trim();
            const permanent_subdivision_village = $("#permanent_subdivision_village").val().trim();
            const permanent_barangay = $("#permanent_barangay").val().trim();
            const permanent_city = $("#permanent_city").val().trim();
            const permanent_province = $("#permanent_province").val().trim();
            const permanent_zipcode = $("#permanent_zipcode").val().trim();

            if (residential_city == "") {
                is_valid = false;
                $(".error_list").append("<li>Residential city is required</li>");
                $("#form_fname").addClass("is-invalid");
            }

            if (residential_province == "") {
                is_valid = false;
                $(".error_list").append("<li>Residential province is required</li>");
                $("#form_fname").addClass("is-invalid");
            }

            if (permanent_city == "") {
                is_valid = false;
                $(".error_list").append("<li>Permanent city is required</li>");
                $("#form_fname").addClass("is-invalid");
            }

            if (permanent_province == "") {
                is_valid = false;
                $(".error_list").append("<li>Permanent province is required</li>");
                $("#form_fname").addClass("is-invalid");
            }

            if (!is_valid) {
                $("#address_error").removeClass("d-none");
                $("body").animate({scrollTop: $('#address_error').offset().top}, 200);
                return;
            }

            const params = [{
                house_no: residential_house,
                street: residential_street,
                subdivision_village: residential_subdivision_village,
                barangay: residential_barangay,
                city: residential_city,
                province: residential_province,
                zipcode: residential_zipcode,
                address_type: "Residential"
            }, {
                house_no: permanent_house,
                street: permanent_street,
                subdivision_village: permanent_subdivision_village,
                barangay: permanent_barangay,
                city: permanent_city,
                province: permanent_province,
                zipcode: permanent_zipcode,
                address_type: "Permanent"
            }];


            insertUpdateAddress(params);
        });

        $("#btn_save_family").click(function () {
            let is_valid = true;
            $("#family_background_error").addClass("d-none");
            $(".is-invalid").removeClass("is-invalid");
            $(".error_list").empty();

            const spouse_fname = $("#spouse_fname").val().trim();
            const spouse_mname = $("#spouse_mname").val().trim();
            const spouse_lname = $("#spouse_lname").val().trim();
            const spouse_ext_name = $("#spouse_ext_name").val().trim();
            const spouse_occupation = $("#spouse_occupation").val().trim();
            const spouse_employer = $("#spouse_employer").val().trim();
            const spouse_business_address = $("#spouse_business_address").val().trim();
            const spouse_contact_no = $("#spouse_contact_no").val().trim();
            const father_fname = $("#father_fname").val().trim();
            const father_mname = $("#father_mname").val().trim();
            const father_lname = $("#father_lname").val().trim();
            const father_ext_name = $("#father_ext_name").val().trim();
            const mother_fname = $("#mother_fname").val().trim();
            const mother_mname = $("#mother_mname").val().trim();
            const mother_lname = $("#mother_lname").val().trim();
            const mother_ext_name = $("#mother_ext_name").val().trim();

            if (!is_valid) {
                $("#family_background_error").removeClass("d-none");
                $("body").animate({scrollTop: $('#family_background_error').offset().top}, 200);
                return;
            }

            const params = {
                spouse_fname: spouse_fname,
                spouse_mname: spouse_mname,
                spouse_lname: spouse_lname,
                spouse_ext_name: spouse_ext_name,
                spouse_occupation: spouse_occupation,
                spouse_employer: spouse_employer,
                spouse_business_address: spouse_business_address,
                spouse_contact_no: spouse_contact_no,
                father_fname: father_fname,
                father_mname: father_mname,
                father_lname: father_lname,
                father_ext_name: father_ext_name,
                mother_fname: mother_fname,
                mother_mname: mother_mname,
                mother_lname: mother_lname,
                mother_ext_name: mother_ext_name
            }

            insertUpdateFamilyBackground(params);
        });

        $("#btn_save_children").click(function () {
            let is_valid = true;
            $("#children_error").addClass("d-none");
            $(".is-invalid").removeClass("is-invalid");
            $(".error_list").empty();

            const name = $("#children_name").val().trim();
            const birthdate = $("#children_birthdate").val();

            if (name == "") {
                is_valid = false;
                $(".error_list").append("<li>Name is required</li>");
            }

            if (birthdate == "") {
                is_valid = false;
                $(".error_list").append("<li>Birthdate is required</li>");
            }

            if (!is_valid) {
                $("#children_error").removeClass("d-none");
                $("body").animate({scrollTop: $('#children_error').offset().top}, 200);
                return;
            }

            children_modal.toggle();
            insertUpdateChildren({id: children_id, name: name, birthdate: birthdate});
        });

        $("#btn_save_education").click(function () {
            let is_valid = true;
            $("#education_error").addClass("d-none");
            $(".is-invalid").removeClass("is-invalid");
            $(".error_list").empty();
            const level = $("#education_level").val();
            const school_name = $("#school_name").val();
            const education_degree = $("#education_degree").val();
            const attendance_from = $("#attendance_from").val();
            const attendance_to = $("#attendance_to").val();
            const highest_level = $("#highest_level").val();
            const year_graduated = $("#year_graduated").val();
            const scholarship = $("#scholarship").val();

            if (level == "") {
                is_valid = false;
                $(".error_list").append("<li>Level is required</li>");
            }

            if (school_name == "") {
                is_valid = false;
                $(".error_list").append("<li>School name is required</li>");
            }

            if (education_degree == "") {
                is_valid = false;
                $(".error_list").append("<li>Education degree is required</li>");
            }

            if (attendance_from == "") {
                is_valid = false;
                $(".error_list").append("<li>Attendance from is required</li>");
            }

            if (attendance_to == "") {
                is_valid = false;
                $(".error_list").append("<li>Attendance to is required</li>");
            }


            if (!is_valid) {
                $("#education_error").removeClass("d-none");
                return;
            }

            const params = {
                id: education_id,
                level: level,
                school_name: school_name,
                education_degree: education_degree,
                attendance_from: attendance_from,
                attendance_to: attendance_to,
                highest_level: highest_level,
                year_graduated: (year_graduated) ? year_graduated : null,
                scholarship: scholarship
            }

            insertUpdateEducation(params);
        });

        $(".btn-save-education").click(function () {
            let is_valid = true;
            $("#education_error").addClass("d-none");
            $(".is-invalid").removeClass("is-invalid");
            $(".error_list").empty();

            let level = $(this).val();
            let school_name = "";
            let education_degree = "";
            let attendance_from = "";
            let attendance_to = "";
            let highest_level = "";
            let year_graduated = "";
            let scholarship = "";

            switch (level) {
                case "Elementary":
                    school_name = $("#elementary_school_name").val().trim();
                    education_degree = $("#elementary_education_degree").val();
                    attendance_from = $("#elementary_attendance_from").val();
                    attendance_to = $("#elementary_attendance_to").val();
                    highest_level = $("#elementary_highest_level").val();
                    year_graduated = $("#elementary_year_graduated").val();
                    scholarship = $("#elementary_scholarship").val().trim();
                    break;
                case "Secondary":
                    school_name = $("#secondary_school_name").val().trim();
                    education_degree = $("#secondary_education_degree").val();
                    attendance_from = $("#secondary_attendance_from").val();
                    attendance_to = $("#secondary_attendance_to").val();
                    highest_level = $("#secondary_highest_level").val();
                    year_graduated = $("#secondary_year_graduated").val();
                    scholarship = $("#secondary_scholarship").val().trim();
                    break;
                case "Vocational/Trade Course":
                    school_name = $("#vocational_school_name").val().trim();
                    education_degree = $("#vocational_education_degree").val();
                    attendance_from = $("#vocational_attendance_from").val();
                    attendance_to = $("#vocational_attendance_to").val();
                    highest_level = $("#vocational_highest_level").val();
                    year_graduated = $("#vocational_year_graduated").val();
                    scholarship = $("#vocational_scholarship").val().trim();
                    break;
                case "College":
                    school_name = $("#college_school_name").val().trim();
                    education_degree = $("#college_education_degree").val();
                    attendance_from = $("#college_attendance_from").val();
                    attendance_to = $("#college_attendance_to").val();
                    highest_level = $("#college_highest_level").val();
                    year_graduated = $("#college_year_graduated").val();
                    scholarship = $("#college_scholarship").val().trim();
                    break;
                case "Graduate Studies":
                    school_name = $("#graduate_school_name").val().trim();
                    education_degree = $("#graduate_education_degree").val();
                    attendance_from = $("#graduate_attendance_from").val();
                    attendance_to = $("#graduate_attendance_to").val();
                    highest_level = $("#graduate_highest_level").val();
                    year_graduated = $("#graduate_year_graduated").val();
                    scholarship = $("#graduate_scholarship").val().trim();
                    break;
            }

            if (school_name == "") {
                is_valid = false;
                $(".error_list").append("<li>School name is required</li>");
            }

            if (education_degree == "") {
                is_valid = false;
                $(".error_list").append("<li>Education degree is required</li>");
            }

            if (attendance_from == "") {
                is_valid = false;
                $(".error_list").append("<li>Attendance from is required</li>");
            }

            if (attendance_to == "") {
                is_valid = false;
                $(".error_list").append("<li>Attendance to is required</li>");
            }


            if (year_graduated == "") {
                is_valid = false;
                $(".error_list").append("<li>Year graduated is required</li>");
            }

            if (!is_valid) {
                $("#education_error").removeClass("d-none");
                return;
            }

            const params = {
                level: level,
                school_name: school_name,
                education_degree: education_degree,
                attendance_from: attendance_from,
                attendance_to: attendance_to,
                highest_level: highest_level,
                year_graduated: year_graduated,
                scholarship: scholarship
            }

            insertUpdateEducation(params);
        });

        $("#btn_save_civil_service").click(function () {
            let is_valid = true;
            $("#civil_service_error").addClass("d-none");
            $(".is-invalid").removeClass("is-invalid");
            $(".error_list").empty();

            const career_service = $("#civil_service_career_service").val().trim();
            const rating = $("#civil_service_rating").val();
            const examination_date = $("#civil_service_examination_date").val();
            const examination_end_date = $("#civil_service_examination_end_date").val();
            const examination_place = $("#civil_service_examination_place").val();
            const license_no = $("#civil_service_license_no").val();
            const license_expiry = $("#civil_service_license_expiry").val();


            if (career_service == "") {
                is_valid = false;
                $(".error_list").append("<li>Career service is required</li>");
            }

            // if (rating == "") {
            //     is_valid = false;
            //     $(".error_list").append("<li>Rating is required</li>");
            // }
            //
            if (examination_date != "" && examination_end_date == "")  {
                is_valid = false;
                $(".error_list").append("<li>Date of examination (To) is required</li>");
            }

            // if (examination_place == "") {
            //     is_valid = false;
            //     $(".error_list").append("<li>Place of examination is required</li>");
            // }

            if (!is_valid) {
                $("#civil_service_error").removeClass("d-none");
                $("body").animate({scrollTop: $('#civil_service_error').offset().top}, 200);
                return;
            }

            const params = {
                id: civil_service_id,
                career_service: career_service,
                rating: rating,
                examination_date: examination_date,
                examination_end_date: examination_end_date,
                examination_place: examination_place,
                license_no: license_no,
                license_expiry: license_expiry
            }

            civil_service_modal.toggle();
            insertUpdateCivilService(params);
        });

        $("#btn_save_work").click(function () {
            let is_valid = true;
            $("#work_error").addClass("d-none");
            $(".is-invalid").removeClass("is-invalid");
            $(".error_list").empty();

            const position = $("#work_position").val().trim();
            const agency = $("#work_agency").val();
            const working_from = $("#work_from").val();
            const working_to = $("#work_to").val();
            const monthly_salary = $("#work_salary").val();
            const salary_grade = $("#work_salary_grade").val();
            const appointment_status = $("#work_appointment_status").val();
            const is_government = $("#work_government").val();
            const is_current_work = $("#work_current_work").val();

            if (position == "") {
                is_valid = false;
                $(".error_list").append("<li>Job Title is required</li>");
            }

            if (agency == "") {
                is_valid = false;
                $(".error_list").append("<li>Company is required</li>");
            }

            if (working_from == "") {
                is_valid = false;
                $(".error_list").append("<li>Working From is required</li>");
            }

            if (working_to == "" && is_current_work != "1") {
                is_valid = false;
                $(".error_list").append("<li>Working To is required</li>");
            }

            // if (monthly_salary == "") {
            //     is_valid = false;
            //     $(".error_list").append("<li>Monthly salary is required</li>");
            // }

            if (appointment_status == "") {
                is_valid = false;
                $(".error_list").append("<li>Appointment status is required</li>");
            }

            if (is_government == "") {
                is_valid = false;
                $(".error_list").append("<li>Government status is required</li>");
            }

            if (is_current_work == "") {
                is_valid = false;
                $(".error_list").append("<li>Current work status is required</li>");
            }

            if (!is_valid) {
                $("#work_error").removeClass("d-none");
                $("body").animate({scrollTop: $('#work_error').offset().top}, 200);
                return;
            }

            const params = {
                id: work_id,
                position: position,
                agency: agency,
                working_from: working_from,
                working_to: (is_current_work == "1") ? working_from : working_to,
                monthly_salary: monthly_salary,
                salary_grade: salary_grade,
                appointment_status: appointment_status,
                is_government: is_government,
                is_current_work: is_current_work
            }

            work_experience_modal.toggle();
            insertUpdateWork(params);
        });

        $("#btn_save_voluntary").click(function () {
            let is_valid = true;
            $("#voluntary_error").addClass("d-none");
            $(".is-invalid").removeClass("is-invalid");
            $(".error_list").empty();

            const organization_address = $("#voluntary_organization_address").val().trim();
            const working_from = $("#voluntary_working_from").val().trim();
            const working_to = $("#voluntary_working_to").val().trim();
            const number_hours = $("#voluntary_number_hours").val().trim();
            const position = $("#voluntary_position").val().trim();

            if (organization_address == "") {
                is_valid = false;
                $(".error_list").append("<li>Name & Organization address is required</li>");
            }

            if (working_from == "") {
                is_valid = false;
                $(".error_list").append("<li>Working from is required</li>");
            }

            if (working_to == "") {
                is_valid = false;
                $(".error_list").append("<li>Working to is required</li>");
            }

            if (number_hours == "") {
                is_valid = false;
                $(".error_list").append("<li>Number hours to is required</li>");
            }

            if (position == "") {
                is_valid = false;
                $(".error_list").append("<li>Position/Nature of work to is required</li>");
            }

            if (!is_valid) {
                $("#voluntary_error").removeClass("d-none");
                $("body").animate({scrollTop: $('#voluntary_error').offset().top}, 200);
                return;
            }

            const params = {
                id: voluntary_id,
                organization_address: organization_address,
                working_from: working_from,
                working_to: working_to,
                number_hours: number_hours,
                position: position
            };

            insertUpdateVoluntary(params);
        });

        $("#btn_save_learning").click(function () {
            let is_valid = true;
            $("#learning_error").addClass("d-none");
            $(".is-invalid").removeClass("is-invalid");
            $(".error_list").empty();

            const learning_title = $("#learning_title").val().trim();
            const attendance_from = $("#learning_attendance_from").val().trim();
            const attendance_to = $("#learning_attendance_to").val().trim();
            const number_hours = $("#learning_number_hours").val().trim();
            const learning_type = $("#learning_type").val().trim();
            const conducted_by = $("#learning_conducted_by").val().trim();

            if (learning_title == "") {
                is_valid = false;
                $(".error_list").append("<li>Learning title is required</li>");
            }

            if (attendance_from == "") {
                is_valid = false;
                $(".error_list").append("<li>Attendance from is required</li>");
            }

            if (attendance_to == "") {
                is_valid = false;
                $(".error_list").append("<li>Attendance to is required</li>");
            }

            if (number_hours == "") {
                is_valid = false;
                $(".error_list").append("<li>Number hours is required</li>");
            }

            if (learning_type == "") {
                is_valid = false;
                $(".error_list").append("<li>Learning type is required</li>");
            }

            if (conducted_by == "") {
                is_valid = false;
                $(".error_list").append("<li>Conducted/Sponsored by is required</li>");
            }

            if (!is_valid) {
                $("#learning_error").removeClass("d-none");
                $("body").animate({scrollTop: $('#learning_error').offset().top}, 200);
                return;
            }

            const params = {
                id: learning_id,
                learning_title: learning_title,
                attendance_from: attendance_from,
                attendance_to: attendance_to,
                number_hours: number_hours,
                learning_type: learning_type,
                conducted_by: conducted_by
            };

            insertUpdateLearning(params);
        });

        $("#btn_save_reference").click(function () {
            let is_valid = true;
            $("#reference_error").addClass("d-none");
            $(".is-invalid").removeClass("is-invalid");
            $(".error_list").empty();

            const name = $("#reference_name").val().trim();
            const address = $("#reference_address").val().trim();
            const contact_no = $("#reference_contact_no").val().trim();

            if (name == "") {
                is_valid = false;
                $(".error_list").append("<li>Name is required</li>");
            }

            if (address == "") {
                is_valid = false;
                $(".error_list").append("<li>Address is required</li>");
            }

            if (contact_no == "") {
                is_valid = false;
                $(".error_list").append("<li>Contact by is required</li>");
            }

            if (!is_valid) {
                $("#reference_error").removeClass("d-none");
                $("body").animate({scrollTop: $('#reference_error').offset().top}, 200);
                return;
            }

            const params = {
                id: reference_id,
                name: name,
                address: address,
                contact_no: contact_no
            }

            insertUpdateReference(params);
        });

        $("#btn_save_other_information").click(function () {
            let is_valid = true;
            $("#other_information_error").addClass("d-none");
            $(".is-invalid").removeClass("is-invalid");
            $(".error_list").empty();

            const description = $("#other_information_description").val().trim();
            if (description == "") {
                is_valid = false;
                $(".error_list").append("<li>Description is required</li>");
            }

            if (!is_valid) {
                $("#reference_error").removeClass("d-none");
                $("body").animate({scrollTop: $('#other_information_error').offset().top}, 200);
                return;
            }

            switch (other_type) {
                case "skill":
                    insertUpdateSkill({id: skill_id, skills: description});
                    break;
                case "recognition":
                    insertUpdateRecognition({id: recognition_id, recognition: description})
                    break;
                case "membership":
                    insertUpdateMembership({id: membership_id, association: description})
                    break;
            }
        });

        $("#btn_save_survey").click(function () {
            let is_valid = true;
            $("#survey_error").addClass("d-none");
            $(".is-invalid").removeClass("is-invalid");
            $(".error_list").empty();

            const survey = [];
            for (let i = 1; i <= 12; i++) {
                const question = SURVEYS[(i - 1)].question;
                const answer = $("input[name='survey_" + i + "']:checked").next().text().trim();
                let details = null;
                let extra = null;

                if (answer == "") {
                    is_valid = false;
                    $(".error_list").append("<li>Please specify " + question + " if YES or NO</li>");
                    continue;
                }

                if (answer == "YES") {
                    details = $("#survey_" + i + "_details").val();
                    extra = $("#survey_" + i + "_extra").val();

                    if (details === undefined) {
                        details = null;
                    } else if (details == "") {
                        is_valid = false;
                        $(".error_list").append("<li>" + question + " " + $("#survey_" + i + "_details").prev().text() + " required</li>");
                        $("#survey_" + i + "_details").addClass("is-invalid");
                    }

                    if (extra === undefined) {
                        extra = null;
                    } else if (extra == "") {
                        is_valid = false;
                        $(".error_list").append("<li>" + question + " " + $("#survey_" + i + "_extra").prev().text() + " required</li>");
                        $("#survey_" + i + "_extra").addClass("is-invalid");
                    }
                }

                survey.push({
                    question: question,
                    answer: answer,
                    details: details,
                    extra: extra,
                });
            }

            if (!is_valid) {
                $("#survey_error").removeClass("d-none");
                $("body").animate({scrollTop: $('#survey_error').offset().top}, 200);
                return;
            }

            insertUpdateSurvey({surveys: survey});
        });

        $("button.nav-link").click(function () {
            switch ($(this).attr("data-bs-target")) {
                case "#address":
                    getAddress(0);
                    break;
                case "#family_background":
                    getFamilyBackground(0)
                    break;
                case "#educational_background":
                    getEducation(0)
                    break;
                case "#civil_service":
                    getCivilService(0)
                    break;
                case "#work_experience":
                    getWork(0)
                    break;
                case "#voluntary_work":
                    getVoluntary(0)
                    break;
                case "#learning_development":
                    getLearning(0)
                    break;
                case "#other_information":
                    getOtherInformation(0)
                    break;
                case "#reference":
                    getReference(0)
                    break;
                case "#survey":
                    getSurvey(0);
                    break;
            }
        });

        getEmployeeDetails(0);
    });

    function editChildren(id) {
        children_id = id;
        const name = $("#child_id_" + id + " td:eq(0)").text().trim();
        const birthdate = moment($("#child_id_" + id + " td:eq(1)").text().trim(), "MM/DD/YYYY").format("YYYY-MM-DD");
        $("#children_name").val(name);
        $("#children_birthdate").val(birthdate);
        children_modal.toggle();
    }

    function editCivilService(id) {
        civil_service_id = id;
        const career_service = $("#civil_service_" + id + " td:eq(0)").text().trim();
        const rating = $("#civil_service_" + id + " td:eq(1)").text().trim();
        let examination_date = $("#civil_service_" + id + " td:eq(2)").text().trim();
        let examination_end_date = "";
        const examination_place = $("#civil_service_" + id + " td:eq(3)").text().trim();
        const license_no = $("#civil_service_" + id + " td:eq(4)").text().trim();
        let license_expiry = $("#civil_service_" + id + " td:eq(5)").text().trim();

        // examination_date = (examination_date) ? moment(examination_date, "MM/DD/YYYY").format("YYYY-MM-DD") : "";
        if (examination_date) {
            examination_end_date = moment(examination_date.split("-")[1], "MM/DD/YYYY").format("YYYY-MM-DD");
            examination_date = moment(examination_date.split("-")[0], "MM/DD/YYYY").format("YYYY-MM-DD")
        } else {
            examination_date = "";
        }

        license_expiry = (license_expiry) ? moment(license_expiry, "MM/DD/YYYY").format("YYYY-MM-DD") : "";

        $("#civil_service_career_service").val(career_service);
        $("#civil_service_rating").val(rating);
        $("#civil_service_examination_date").val(examination_date);
        $("#civil_service_examination_end_date").val(examination_end_date);
        $("#civil_service_examination_place").val(examination_place);
        $("#civil_service_license_no").val(license_no);
        $("#civil_service_license_expiry").val(license_expiry);

        civil_service_modal.toggle();
    }

    function editWork(id) {
        work_id = id;
        const work_position = $("#work_id_" + id + " td:eq(0)").text();
        const work_agency = $("#work_id_" + id + " td:eq(1)").text();
        const work_from = moment($("#work_id_" + id + " td:eq(2)").text(), "MM/DD/YYYY").format("YYYY-MM-DD");
        let work_to = $("#work_id_" + id + " td:eq(3)").text();
        if (work_to == "Present") {
            work_to = moment().format("YYYY-MM-DD");
        } else {
            work_to = moment(work_to, "MM/DD/YYYY").format("YYYY-MM-DD");
        }
        const work_salary = $("#work_id_" + id + " td:eq(4)").text();
        const work_salary_grade = $("#work_id_" + id + " td:eq(5)").text();
        const work_appointment_status = $("#work_id_" + id + " td:eq(6)").text();
        const work_government = $("#work_id_" + id + " td:eq(7)").text();
        const work_current_work = $("#work_id_" + id + " td:eq(8)").text();

        $("#work_position").val(work_position);
        $("#work_agency").val(work_agency);
        $("#work_from").val(work_from);
        $("#work_to").val(work_to);
        $("#work_salary").val(work_salary);
        $("#work_salary_grade").val(work_salary_grade);
        $("#work_appointment_status").val(work_appointment_status);
        $("#work_government").val((work_government == "Y") ? "1" : "0");
        $("#work_current_work").val(work_current_work).change();
        work_experience_modal.toggle()
    }

    function editVoluntary(id) {
        voluntary_id = id;
        const organization_address = $("#voluntary_" + id + " td:eq(0)").text();
        const position = $("#voluntary_" + id + " td:eq(1)").text();
        const working_from = moment($("#voluntary_" + id + " td:eq(2)").text(), "MM/DD/YYYY").format("YYYY-MM-DD")
        const working_to = moment($("#voluntary_" + id + " td:eq(3)").text(), "MM/DD/YYYY").format("YYYY-MM-DD");
        const number_hours = $("#voluntary_" + id + " td:eq(4)").text();

        $("#voluntary_organization_address").val(organization_address);
        $("#voluntary_position").val(position);
        $("#voluntary_working_from").val(working_from);
        $("#voluntary_working_to").val(working_to);
        $("#voluntary_number_hours").val(number_hours);
        voluntary_modal.toggle()
    }

    function editLearning(id) {
        learning_id = id;
        const learning_title = $("#learning_" + id + " td:eq(0)").text();
        const attendance_from = moment($("#learning_" + id + " td:eq(1)").text(), "MM/DD/YYYY").format("YYYY-MM-DD")
        const attendance_to = moment($("#learning_" + id + " td:eq(2)").text(), "MM/DD/YYYY").format("YYYY-MM-DD")
        const number_hours = $("#learning_" + id + " td:eq(3)").text();
        const learning_type = $("#learning_" + id + " td:eq(4)").text();
        const conducted_by = $("#learning_" + id + " td:eq(5)").text();

        $("#learning_title").val(learning_title);
        $("#learning_attendance_from").val(attendance_from);
        $("#learning_attendance_to").val(attendance_to);
        $("#learning_number_hours").val(number_hours);
        $("#learning_type").val(learning_type);
        $("#learning_conducted_by").val(conducted_by);
        learning_modal.toggle()
    }

    function editReference(id) {
        reference_id = id;
        const name = $("#reference_" + id + " td:eq(0)").text();
        const address = $("#reference_" + id + " td:eq(1)").text();
        const contact_no = $("#reference_" + id + " td:eq(2)").text();

        $("#reference_name").val(name);
        $("#reference_address").val(address);
        $("#reference_contact_no").val(contact_no);
        reference_modal.toggle()
    }

    function editSkill(id) {
        skill_id = id;
        const description = $("#skill_" + id + " td:eq(0)").text().trim();
        $("#other_information_description").val(description);
        $("#other_information_title").text("Skills/Hobbies");
        other_type = "skill";
        other_information_modal.toggle();
    }

    function editRecognition(id) {
        recognition_id = id;
        const description = $("#recognition_" + id + " td:eq(0)").text().trim();
        $("#other_information_description").val(description);
        $("#other_information_title").text("Non-Academic Distinctions");
        other_type = "recognition";
        other_information_modal.toggle();
    }

    function editMembership(id) {
        membership_id = id;
        const description = $("#membership_" + id + " td:eq(0)").text().trim();
        $("#other_information_description").val(description);
        $("#other_information_title").text("Association Membership");
        other_type = "membership";
        other_information_modal.toggle();
    }
</script>

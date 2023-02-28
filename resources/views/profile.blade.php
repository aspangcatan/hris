@extends('master')
@section('content')
    <div class="pagetitle">
        <h1>Profile</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index.html">Home</a></li>
                <li class="breadcrumb-item">Users</li>
                <li class="breadcrumb-item active">Profile</li>
            </ol>
        </nav>
    </div>
    <section class="section profile">
        <div class="row">
            <div class="col-xl-4">
                <div class="card">
                    <div class="card-body profile-card pt-4 d-flex flex-column align-items-center">
                        <img src="assets/img/profile-img.jpg" alt="Profile" class="rounded-circle">
                        <h2>Kevin Anderson</h2>
                        <h3>Web Designer</h3>
                        <div class="social-links mt-2">
                            <a href="#" class="twitter"><i class="bi bi-twitter"></i></a>
                            <a href="#" class="facebook"><i class="bi bi-facebook"></i></a>
                            <a href="#" class="instagram"><i class="bi bi-instagram"></i></a>
                            <a href="#" class="linkedin"><i class="bi bi-linkedin"></i></a>
                        </div>
                    </div>
                </div>
                <div class="card p-2">
                    <h5 class="card-title fw-bold ms-3">Biography</h5>
                    <div class="card-body">
                        <p class="fst-italic">
                            Using third-person will make your bio sound more authoritative and objective. So, if you’re
                            job searching in a formal industry, applying for grants, or trying to get published, you may
                            want to stick to the third person.

                            For instance, when you write a third-person bio you may start with "Jasmine Montgomery is a
                            Senior Hiring Manager at L’Oreal based in New York. She recruits across several business
                            units to connect with the brightest talent from around the globe." By only using your name
                            and pronouns to speak about yourself here, you are letting your title and skill set speak
                            for themselves.

                            These bios create distance between the subject of the bio (you) and the reader, through a
                            third person. This person could be anyone, but they usually speak in a tone that emphasizes
                            their expertise. This means that third-person reviews can sometimes feel aloof or overly
                            formal.
                        </p>
                    </div>
                </div>
            </div>
            <div class="col-xl-8">
                <div class="card">
                    <div class="card-body pt-3">
                        <!-- Bordered Tabs -->
                        <ul class="nav nav-tabs nav-tabs-bordered">
                            <li class="nav-item">
                                <button class="nav-link active" data-bs-toggle="tab"
                                        data-bs-target="#personal_information">
                                    Personal Information
                                </button>
                            </li>

                            <li class="nav-item">
                                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#address">
                                    Address
                                </button>
                            </li>

                            <li class="nav-item">
                                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#family_background">
                                    Family Background
                                </button>
                            </li>
                            <li class="nav-item">
                                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#educational_background">
                                    Educational Background
                                </button>
                            </li>
                            <li class="nav-item">
                                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#civil_service">
                                    Civil Service Eligibility
                                </button>
                            </li>
                            <li class="nav-item">
                                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#work_experience">
                                    Work Experience
                                </button>
                            </li>
                            <li class="nav-item">
                                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#voluntary_work">
                                    Voluntary Work
                                </button>
                            </li>
                            <li class="nav-item">
                                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#learning_development">
                                    Learning & Development
                                </button>
                            </li>
                            <li class="nav-item">
                                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#other_information">
                                    Other Information
                                </button>
                            </li>
                            <li class="nav-item">
                                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#references">
                                    References
                                </button>
                            </li>
                            <li class="nav-item">
                                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#survey">
                                    Survey
                                </button>
                            </li>
                        </ul>
                        <div class="tab-content pt-2">

                            <div class="tab-pane fade pt-3" id="personal_information">
                                @include('forms.personal_information')
                            </div>

                            <div class="tab-pane fade pt-3" id="address">
                                @include('forms.address')
                            </div>
                            <div class="tab-pane fade pt-3" id="family_background">
                                @include('forms.family_background')
                            </div>
                            <div class="tab-pane fade pt-3" id="educational_background">
                                @include('forms.educational_background')
                            </div>
                            <div class="tab-pane fade pt-3" id="civil_service">
                                @include('forms.civil_service')
                            </div>
                            <div class="tab-pane fade pt-3" id="work_experience">
                                @include('forms.work_experience')
                            </div>
                            <div class="tab-pane fade pt-3" id="voluntary_work">
                                @include('forms.voluntary_work')
                            </div>
                            <div class="tab-pane fade pt-3" id="learning_development">
                                @include('forms.learning_development')
                            </div>
                            <div class="tab-pane fade pt-3" id="other_information">
                                @include('forms.other_information')
                            </div>
                        </div><!-- End Bordered Tabs -->
                    </div>
                </div>
            </div>
        </div>
    </section>
@stop
@section('js')
    @include('js.employee')
    <script>
        toastr.options = {
            "positionClass": "toast-bottom-right",
        }

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
                const pagibig_id_no = $("#pagibig_id_no").val().trim();
                const philhealth_no = $("#philhealth_no").val().trim();
                const sss_no = $("#sss_no").val().trim();
                const tin_no = $("#tin_no").val().trim();
                const employee_no = $("#employee_no").val().trim();
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

                if (birthdate == "") {
                    is_valid = false;
                    $(".error_list").append("<li>Date of birth is required</li>");
                    $("#form_birthdate").addClass("is-invalid");
                }

                if (birthplace == "") {
                    is_valid = false;
                    $(".error_list").append("<li>Place of birth is required</li>");
                    $("#form_birthplace").addClass("is-invalid");
                }

                if (sex == "" || sex === null) {
                    is_valid = false;
                    $(".error_list").append("<li>Sex is required</li>");
                    $("#form_sex").addClass("is-invalid");
                }

                if (!civil_status) {
                    is_valid = false;
                    $(".error_list").append("<li>Civil status is required</li>");
                    $("#form_civil_status").addClass("is-invalid");
                }

                if (height == "") {
                    is_valid = false;
                    $(".error_list").append("<li>Height is required</li>");
                    $("#form_height").addClass("is-invalid");
                }

                if (weight == "") {
                    is_valid = false;
                    $(".error_list").append("<li>Weight is required</li>");
                    $("#form_weight").addClass("is-invalid");
                }

                if (blood_type === null || blood_type == "") {
                    is_valid = false;
                    $(".error_list").append("<li>Blood type is required</li>");
                    $("#form_blood_type").addClass("is-invalid");
                }

                if (employee_no == "") {
                    is_valid = false;
                    $(".error_list").append("<li>Agency Employee No. is required</li>");
                    $("#form_employee_no").addClass("is-invalid");
                }

                if (telephone_no == "") {
                    is_valid = false;
                    $(".error_list").append("<li>Telephone No. is required</li>");
                    $("#form_telephone_no").addClass("is-invalid");
                }

                if (mobile_no == "") {
                    is_valid = false;
                    $(".error_list").append("<li>Mobile No. is required</li>");
                    $("#form_mobile_no").addClass("is-invalid");
                }

                if (email_address == "") {
                    is_valid = false;
                    $(".error_list").append("<li>Email address is required</li>");
                    $("#form_email_address").addClass("is-invalid");
                }

                if (citizenship == "") {
                    is_valid = false;
                    $(".error_list").append("<li>Citizenship is required</li>");
                }

                if (citizenship != "" && citizenship_type == "") {
                    $(".error_list").append("<li>Please specify citizenship type</li>");
                    is_valid = false;
                }

                if (citizenship_type && citizenship_country == "") {
                    is_valid = false;
                    $(".error_list").append("<li>Citizenship country type is required</li>");
                    $("#form_citizenship_country").addClass("is-invalid");
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
                    pagibig_id_no: pagibig_id_no,
                    philhealth_no: philhealth_no,
                    sss_no: sss_no,
                    tin_no: tin_no,
                    employee_no: employee_no,
                    citizenship: citizenship,
                    citizenship_type: citizenship_type,
                    citizenship_country: citizenship_country,
                    telephone_no: telephone_no,
                    mobile_no: mobile_no,
                    email_address: email_address
                }

                insertUpdateEmployee(params);
            });

            getEmployeeDetails();
        });
    </script>
@stop

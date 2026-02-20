<div class="modal fade" id="employee_modal" tabindex="-1" aria-labelledby="updateModalLabel" aria-hidden="true"
     data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content">
            <div class="modal-body m-0 p-0">
                <section class="section profile">
                    <div class="row">
                        <div class="col-xl-12">
                            <div class="card">
                                <div class="card-body profile-card pt-4 d-flex flex-column align-items-center">
                                    <img alt="Profile" class="rounded-circle" id="employee_img">
                                    <h2 id="employee_name"></h2>
                                    <h3 id="employee_designation"></h3>
                                    <div class="social-links mt-2">
                                        <button class="btn btn-sm btn-success d-none" id="btn_print_pds">Print PDS</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-xl-12">
                            <div class="card m-0">
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
                                            <button class="nav-link" data-bs-toggle="tab"
                                                    data-bs-target="#family_background">
                                                Family Background
                                            </button>
                                        </li>
                                        <li class="nav-item">
                                            <button class="nav-link" data-bs-toggle="tab"
                                                    data-bs-target="#educational_background">
                                                Educational Background
                                            </button>
                                        </li>
                                        <li class="nav-item">
                                            <button class="nav-link" data-bs-toggle="tab"
                                                    data-bs-target="#civil_service">
                                                Civil Service Eligibility
                                            </button>
                                        </li>
                                        <li class="nav-item">
                                            <button class="nav-link" data-bs-toggle="tab"
                                                    data-bs-target="#work_experience">
                                                Work Experience
                                            </button>
                                        </li>
                                        <li class="nav-item">
                                            <button class="nav-link" data-bs-toggle="tab"
                                                    data-bs-target="#voluntary_work">
                                                Voluntary Work
                                            </button>
                                        </li>
                                        <li class="nav-item">
                                            <button class="nav-link" data-bs-toggle="tab"
                                                    data-bs-target="#learning_development">
                                                Learning & Development
                                            </button>
                                        </li>
                                        <li class="nav-item">
                                            <button class="nav-link" data-bs-toggle="tab"
                                                    data-bs-target="#other_information">
                                                Other Information
                                            </button>
                                        </li>
                                        <li class="nav-item">
                                            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#reference">
                                                References
                                            </button>
                                        </li>
                                        <li class="nav-item">
                                            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#survey">
                                                Survey
                                            </button>
                                        </li>
                                        <li class="nav-item">
                                            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#service_records">
                                                Service Records
                                            </button>
                                        </li>
                                        <li class="nav-item">
                                            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#leave_credits">
                                                Leave Credits
                                            </button>
                                        </li>
                                        <li class="nav-item">
                                            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#payroll">
                                                Payroll
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
                                        <div class="tab-pane fade pt-3" id="reference">
                                            @include('forms.reference')
                                        </div>
                                        <div class="tab-pane fade pt-3" id="survey">
                                            @include('forms.survey')
                                        </div>
                                        <div class="tab-pane fade pt-3" id="service_records">
                                            @include('forms.service_record')
                                        </div>
                                        <div class="tab-pane fade pt-3" id="leave_credits">
                                            @include('forms.leave_credits')
                                        </div>
                                        <div class="tab-pane fade pt-3" id="payroll">
                                            @include('forms.payroll')
                                        </div>
                                    </div><!-- End Bordered Tabs -->
                                </div>
                            </div>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </div>
</div>

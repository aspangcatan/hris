<style>
    .payroll-tab-board {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 20px;
        padding: 1.5rem;
        box-shadow: 0 18px 38px rgba(15, 23, 42, 0.08);
    }

    .payroll-tab-section {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 16px;
        padding: 1rem;
        margin-bottom: 1.25rem;
    }

    .payroll-tab-section .form-control {
        border-radius: 12px;
        border: 1px solid #d8e1ee;
        background: #fff;
    }

    .payroll-tab-table-shell {
        border: 1px solid #e2e8f0;
        border-radius: 16px;
        overflow: hidden;
        background: #fff;
    }

    .payroll-tab-table-shell table {
        margin-bottom: 0;
    }

    .payroll-tab-table-shell table thead th {
        background: #f8fafc;
        color: #0f172a;
        font-size: 0.72rem;
        text-transform: uppercase;
        letter-spacing: 0.08em;
    }

    .payroll-tab-table-shell table tbody td {
        color: #1e293b;
        font-weight: 500;
        vertical-align: middle;
    }
</style>

<div class="modal fade" id="employee_modal" tabindex="-1" aria-labelledby="updateModalLabel" aria-hidden="true"
     data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Employee Information</h5>
                <button type="button" class="btn-close fs-1" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
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
                                        <button class="btn btn-sm btn-success d-none" id="btn_print_pds_2025">Print PDS 2025</button>
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
                                        {{-- Payroll tab hidden: pay setup is managed on Payroll Settings (/payroll_settings). --}}
                                        @if(false)
                                        <li class="nav-item">
                                            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#payroll">
                                                Payroll
                                            </button>
                                        </li>
                                        @endif
                                    </ul>
                                    <div class="tab-content pt-2">
                                        <div class="tab-pane fade pt-3" id="personal_information">
                                            <div class="payroll-tab-board">
                                                @include('forms.personal_information')
                                            </div>
                                        </div>
                                        <div class="tab-pane fade pt-3" id="address">
                                            <div class="payroll-tab-board">
                                                @include('forms.address')
                                            </div>
                                        </div>
                                        <div class="tab-pane fade pt-3" id="family_background">
                                            <div class="payroll-tab-board">
                                                @include('forms.family_background')
                                            </div>
                                        </div>
                                        <div class="tab-pane fade pt-3" id="educational_background">
                                            <div class="payroll-tab-board">
                                                @include('forms.educational_background')
                                            </div>
                                        </div>
                                        <div class="tab-pane fade pt-3" id="civil_service">
                                            <div class="payroll-tab-board">
                                                @include('forms.civil_service')
                                            </div>
                                        </div>
                                        <div class="tab-pane fade pt-3" id="work_experience">
                                            <div class="payroll-tab-board">
                                                @include('forms.work_experience')
                                            </div>
                                        </div>
                                        <div class="tab-pane fade pt-3" id="voluntary_work">
                                            <div class="payroll-tab-board">
                                                @include('forms.voluntary_work')
                                            </div>
                                        </div>
                                        <div class="tab-pane fade pt-3" id="learning_development">
                                            <div class="payroll-tab-board">
                                                @include('forms.learning_development')
                                            </div>
                                        </div>
                                        <div class="tab-pane fade pt-3" id="other_information">
                                            <div class="payroll-tab-board">
                                                @include('forms.other_information')
                                            </div>
                                        </div>
                                        <div class="tab-pane fade pt-3" id="reference">
                                            <div class="payroll-tab-board">
                                                @include('forms.reference')
                                            </div>
                                        </div>
                                        <div class="tab-pane fade pt-3" id="survey">
                                            <div class="payroll-tab-board">
                                                @include('forms.survey')
                                            </div>
                                        </div>
                                        <div class="tab-pane fade pt-3" id="service_records">
                                            <div class="payroll-tab-board">
                                                @include('forms.service_record')
                                            </div>
                                        </div>
                                        <div class="tab-pane fade pt-3" id="leave_credits">
                                            <div class="payroll-tab-board">
                                                @include('forms.leave_credits')
                                            </div>
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

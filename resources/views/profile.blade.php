@extends('master')
@section('content')
    <div class="pagetitle">
        <h1>Profile</h1>
    </div>
    <section class="section profile">
        <div class="row">
            <div class="col-xl-4">
                <div class="card">
                    <div class="card-body profile-card pt-4 d-flex flex-column align-items-center">
                        <img src="{{ (Auth::user()->picture) ? url('/avatar/'.Auth::user()->picture) : "images/ic_user.png"}}?v={{ date('Ymdhis') }}" alt="Profile" class="rounded-circle">
                        <h2>{{ \Illuminate\Support\Facades\Auth::user()->fname." ".\Illuminate\Support\Facades\Auth::user()->lname }}</h2>
                        <h3>{{ session('designation') }}</h3>
                        <div class="social-links mt-2">
                            <button class="btn btn-sm btn-success" id="btn_print_pds">Print PDS</button>
                        </div>
                    </div>
                </div>
                <div class="card p-2">
                    <h5 class="card-title fw-bold ms-3">Vision</h5>
                    <div class="card-body">
                        <p class="fst-italic">
                            Cebu South Medical Center is a premiere academic medical institution centered on excellence
                            in Physical and Rehabilitation Medicine, Trauma, and Orthopedics in the Visayas by 2028.
                        </p>
                    </div>
                </div>
                <div class="card p-2">
                    <h5 class="card-title fw-bold ms-3">Mission</h5>
                    <div class="card-body">
                        <p class="fst-italic">
                            We shall provide the highest standards of care for Physical and Rehabilitation Medicine,
                            Trauma, and Orthopedics is driven by a culture of learning, continuous quality improvement,
                            and state-of-the-art technology for all. Core Values Love for God | Client-centered Care |
                            Integrity | Excellence
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
                                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#reference">
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
                            <div class="tab-pane fade pt-3" id="reference">
                                @include('forms.reference')
                            </div>
                            <div class="tab-pane fade pt-3" id="survey">
                                @include('forms.survey')
                            </div>
                        </div><!-- End Bordered Tabs -->
                    </div>
                </div>
            </div>
        </div>
    </section>
    @include('modals.children')
    @include('modals.civil_service')
    @include('modals.work_experience')
    @include('modals.voluntary')
    @include('modals.learning')
    @include('modals.reference')
    @include('modals.other_information')
    @include('modals.print')
@stop
@section('js')
    @include('js.profile')
@stop

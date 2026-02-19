@extends('master')
@section('content')
    <style>
        .profile-shell {
            background:
                radial-gradient(circle at 15% 20%, rgba(14, 116, 144, 0.16), transparent 42%),
                radial-gradient(circle at 80% 0%, rgba(14, 165, 233, 0.14), transparent 38%),
                #f8fafc;
        }

        .profile-tab {
            border: 1px solid #dbe2eb;
            border-radius: 9999px;
            color: #334155;
            font-weight: 600;
            font-size: 13px;
            letter-spacing: .02em;
            padding: .5rem .9rem;
            background: #fff;
            transition: all .2s ease;
        }

        .profile-tab:hover {
            border-color: #0f766e;
            color: #0f766e;
        }

        .profile-tab.active {
            border-color: #0f766e;
            background: linear-gradient(120deg, #0f766e, #0ea5a4);
            color: #fff;
            box-shadow: 0 10px 24px rgba(15, 118, 110, .28);
        }
    </style>

    <section class="profile-shell relative rounded-[28px] px-4 pb-6 pt-5 shadow-sm sm:px-6 lg:px-8">
        <div class="mb-6 flex flex-col gap-4 lg:flex-row">
            <div class="rounded-3xl border-0 bg-slate-900/95 p-5 text-white shadow-2xl lg:w-4/12">
                <div class="flex flex-col items-center text-center">
                    <img
                        src="{{ (Auth::user()->picture) ? "https://dohcsmc.com/id/storage/crop/".Auth::user()->picture : "images/ic_user.png"}}"
                        alt="Profile"
                        class="h-24 w-24 rounded-full border-4 border-white/30 object-cover shadow-lg">
                    <p class="mt-4 text-2xl font-semibold tracking-tight">
                        {{ \Illuminate\Support\Facades\Auth::user()->fname." ".\Illuminate\Support\Facades\Auth::user()->lname }}
                    </p>
                    <p class="mt-1 text-sm text-slate-300">{{ session('designation') }}</p>
                    <button class="mt-4 inline-flex items-center rounded-xl bg-emerald-400 px-4 py-2 text-sm font-semibold text-slate-900 transition hover:bg-emerald-300"
                            id="btn_print_pds">
                        Print PDS
                    </button>
                </div>
            </div>
            <div class="grid gap-4 lg:w-8/12 lg:grid-cols-2">
                <article class="rounded-3xl bg-white p-5 shadow-xl ring-1 ring-slate-200">
                    <h5 class="text-xs font-semibold uppercase tracking-[0.22em] text-teal-700">Vision</h5>
                    <p class="mt-3 text-sm leading-7 text-slate-600">
                        Cebu South Medical Center is a premiere academic medical institution centered on excellence
                        in Physical and Rehabilitation Medicine, Trauma, and Orthopedics in the Visayas by 2028.
                    </p>
                </article>
                <article class="rounded-3xl bg-white p-5 shadow-xl ring-1 ring-slate-200">
                    <h5 class="text-xs font-semibold uppercase tracking-[0.22em] text-teal-700">Mission</h5>
                    <p class="mt-3 text-sm leading-7 text-slate-600">
                        We shall provide the highest standards of care for Physical and Rehabilitation Medicine,
                        Trauma, and Orthopedics is driven by a culture of learning, continuous quality improvement,
                        and state-of-the-art technology for all. Core Values Love for God | Client-centered Care |
                        Integrity | Excellence
                    </p>
                </article>
            </div>
        </div>

        <div class="rounded-3xl bg-white p-4 shadow-xl ring-1 ring-slate-200 sm:p-5">
            <ul class="nav flex flex-wrap gap-2 overflow-x-auto pb-1" role="tablist">
                <li class="nav-item">
                    <button class="nav-link profile-tab active" data-bs-toggle="tab"
                            data-bs-target="#personal_information">Personal Information
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link profile-tab" data-bs-toggle="tab" data-bs-target="#address">Address</button>
                </li>
                <li class="nav-item">
                    <button class="nav-link profile-tab" data-bs-toggle="tab" data-bs-target="#family_background">Family Background</button>
                </li>
                <li class="nav-item">
                    <button class="nav-link profile-tab" data-bs-toggle="tab" data-bs-target="#educational_background">Educational Background</button>
                </li>
                <li class="nav-item">
                    <button class="nav-link profile-tab" data-bs-toggle="tab" data-bs-target="#civil_service">Civil Service Eligibility</button>
                </li>
                <li class="nav-item">
                    <button class="nav-link profile-tab" data-bs-toggle="tab" data-bs-target="#work_experience">Work Experience</button>
                </li>
                <li class="nav-item">
                    <button class="nav-link profile-tab" data-bs-toggle="tab" data-bs-target="#voluntary_work">Voluntary Work</button>
                </li>
                <li class="nav-item">
                    <button class="nav-link profile-tab" data-bs-toggle="tab" data-bs-target="#learning_development">Learning & Development</button>
                </li>
                <li class="nav-item">
                    <button class="nav-link profile-tab" data-bs-toggle="tab" data-bs-target="#other_information">Other Information</button>
                </li>
                <li class="nav-item">
                    <button class="nav-link profile-tab" data-bs-toggle="tab" data-bs-target="#reference">References</button>
                </li>
                <li class="nav-item">
                    <button class="nav-link profile-tab" data-bs-toggle="tab" data-bs-target="#survey">Survey</button>
                </li>
            </ul>

            <div class="tab-content mt-5">
                <div class="tab-pane fade pt-1" id="personal_information">
                    @include('forms.personal_information')
                </div>
                <div class="tab-pane fade pt-1" id="address">
                    @include('forms.address')
                </div>
                <div class="tab-pane fade pt-1" id="family_background">
                    @include('forms.family_background')
                </div>
                <div class="tab-pane fade pt-1" id="educational_background">
                    @include('forms.educational_background')
                </div>
                <div class="tab-pane fade pt-1" id="civil_service">
                    @include('forms.civil_service')
                </div>
                <div class="tab-pane fade pt-1" id="work_experience">
                    @include('forms.work_experience')
                </div>
                <div class="tab-pane fade pt-1" id="voluntary_work">
                    @include('forms.voluntary_work')
                </div>
                <div class="tab-pane fade pt-1" id="learning_development">
                    @include('forms.learning_development')
                </div>
                <div class="tab-pane fade pt-1" id="other_information">
                    @include('forms.other_information')
                </div>
                <div class="tab-pane fade pt-1" id="reference">
                    @include('forms.reference')
                </div>
                <div class="tab-pane fade pt-1" id="survey">
                    @include('forms.survey')
                </div>
            </div>
        </div>
    </section>
    @include('modals.children')
    @include('modals.education')
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

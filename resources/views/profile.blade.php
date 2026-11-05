@extends('master')
@section('content')
    @php
        $me       = \Illuminate\Support\Facades\Auth::user();
        $section  = \Illuminate\Support\Facades\DB::table('tdh_user.section')->where('id', $me->section)->value('description');
        $division = \Illuminate\Support\Facades\DB::table('tdh_user.division')->where('id', $me->division)->value('description');
        $employeeNo = \Illuminate\Support\Facades\DB::table('hris.employees')->where('user_id', $me->id)->value('employee_no');
        $avatar   = $me->picture ? 'https://dohcsmc.com/id/storage/crop/' . $me->picture : asset('images/ic_user.png');
        $pdsTabs  = [
            'personal_information'   => ['bi-person-vcard', 'Personal Information'],
            'address'                => ['bi-geo-alt', 'Address'],
            'family_background'      => ['bi-people', 'Family Background'],
            'educational_background' => ['bi-mortarboard', 'Educational Background'],
            'civil_service'          => ['bi-award', 'Civil Service Eligibility'],
            'work_experience'        => ['bi-briefcase', 'Work Experience'],
            'voluntary_work'         => ['bi-heart', 'Voluntary Work'],
            'learning_development'   => ['bi-journal-bookmark', 'Learning & Development'],
            'other_information'      => ['bi-info-circle', 'Other Information'],
            'reference'              => ['bi-person-lines-fill', 'References'],
            'survey'                 => ['bi-ui-checks', 'Survey'],
        ];
    @endphp

    <div class="page-head">
        <div>
            <p class="eyebrow">Personal Data Sheet</p>
            <h1>My Profile</h1>
            <p class="page-sub">Keep your PDS up to date. HR uses it for your records and official documents.</p>
        </div>
        <div class="page-actions">
            <button type="button" class="btn btn-primary" id="btn_print_pds">
                <i class="bi bi-printer"></i> Print PDS
            </button>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-xl-6">
            <div class="card profile-summary">
                <img src="{{ $avatar }}" onerror="this.onerror=null; this.src='{{ asset('images/ic_user.png') }}';"
                     alt="Profile photo" class="profile-avatar">
                <div class="profile-ident">
                    <h2 class="profile-name">{{ $me->fname }} {{ $me->lname }}</h2>
                    <p class="profile-role">{{ session('designation') ?: 'No designation on file' }}</p>
                    <div class="info-grid">
                        <div class="info-tile">
                            <span class="info-label">Section</span>
                            <span class="info-value" title="{{ $section }}">{{ $section ?: '—' }}</span>
                        </div>
                        <div class="info-tile">
                            <span class="info-label">Division</span>
                            <span class="info-value" title="{{ $division }}">{{ $division ?: '—' }}</span>
                        </div>
                        <div class="info-tile">
                            <span class="info-label">Employee No.</span>
                            <span class="info-value">{{ $employeeNo ?: '—' }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <article class="card about-card">
                <h3 class="section-title"><i class="bi bi-eye"></i> Vision</h3>
                <p>
                    Cebu South Medical Center is a premiere academic medical institution centered on excellence
                    in Physical and Rehabilitation Medicine, Trauma, and Orthopedics in the Visayas by 2028.
                </p>
            </article>
        </div>
        <div class="col-md-6 col-xl-3">
            <article class="card about-card">
                <h3 class="section-title"><i class="bi bi-flag"></i> Mission</h3>
                <p>
                    We shall provide the highest standards of care for Physical and Rehabilitation Medicine,
                    Trauma, and Orthopedics is driven by a culture of learning, continuous quality improvement,
                    and state-of-the-art technology for all.
                </p>
                <div class="value-pills" aria-label="Core values">
                    <span class="value-pill">Love for God</span>
                    <span class="value-pill">Client-centered Care</span>
                    <span class="value-pill">Integrity</span>
                    <span class="value-pill">Excellence</span>
                </div>
            </article>
        </div>
    </div>

    <div class="card pds-card">
        <ul class="nav pds-tabs" role="tablist">
            @foreach($pdsTabs as $pane => [$icon, $label])
                <li class="nav-item" role="presentation">
                    <button type="button" class="nav-link {{ $loop->first ? 'active' : '' }}" data-bs-toggle="tab"
                            data-bs-target="#{{ $pane }}" role="tab">
                        <i class="bi {{ $icon }}"></i> {{ $label }}
                    </button>
                </li>
            @endforeach
        </ul>

        <div class="tab-content pds-body">
            @foreach(array_keys($pdsTabs) as $pane)
                <div class="tab-pane fade" id="{{ $pane }}" role="tabpanel">
                    @include('forms.' . $pane)
                </div>
            @endforeach
        </div>
    </div>

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

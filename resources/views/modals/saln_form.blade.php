@php
    /*
     * List sections of the SALN form. Every id here (pane, tbody, add button, checkbox) is wired
     * up in js/saln.blade.php (SALN_INLINE_CFG + the get* loaders) - keep them in sync.
     */
    $salnListSections = [
        'children' => [
            'icon' => 'bi-people', 'title' => 'Unmarried children below 18',
            'help' => 'Your unmarried children below 18 years old who live in your household.',
            'add' => 'Add child',
            'cols' => ['Full name', 'Date of birth'],
        ],
        'real' => [
            'icon' => 'bi-house', 'title' => 'Real properties',
            'help' => 'Land, houses, buildings and other real estate. Copy the assessed and market values from the tax declaration.',
            'add' => 'Add property',
            'cols' => ['Description<small>e.g. lot, house</small>', 'Kind<small>e.g. residential</small>', 'Exact location',
                       'Assessed value', 'Current fair market value', 'Year acquired', 'Mode of acquisition', 'Acquisition cost'],
        ],
        'personal' => [
            'icon' => 'bi-car-front', 'title' => 'Personal properties',
            'help' => 'Vehicles, jewelry, appliances, gadgets, furniture, cash in bank and other personal items.',
            'add' => 'Add item',
            'cols' => ['Description', 'Year(s) acquired', 'Acquisition cost / amount'],
        ],
        'liabilities' => [
            'icon' => 'bi-credit-card', 'title' => 'Liabilities',
            'help' => 'Loans, mortgages, credit card balances and other debts still unpaid as of the statement date.',
            'add' => 'Add liability',
            'cols' => ['Nature<small>e.g. housing loan</small>', 'Name of creditor', 'Outstanding balance'],
        ],
        'business' => [
            'icon' => 'bi-briefcase', 'title' => 'Business interests & financial connections',
            'help' => 'Businesses, companies or partnerships where you hold shares, ownership or any financial connection.',
            'add' => 'Add business',
            'cols' => ['Name of business / entity', 'Business address', 'Nature of interest', 'Date acquired'],
            'none' => 'I/We do not have any business interest or financial connection.',
        ],
        'relatives' => [
            'icon' => 'bi-diagram-3', 'title' => 'Relatives in government service',
            'help' => 'Relatives within the fourth civil degree of blood or marriage (including in-laws) who work in government.',
            'add' => 'Add relative',
            'cols' => ['Name of relative', 'Relationship', 'Position', 'Agency & address'],
            'none' => 'I/We do not know of any relative/s in the government service.',
        ],
        'marriages' => [
            'icon' => 'bi-heart', 'title' => 'Other marriages',
            'help' => 'Only if you have been married more than once: the full name of each previous spouse.',
            'add' => 'Add name',
            'cols' => ['Full name of spouse'],
        ],
    ];

    // [pane id => [tab label, section key, tbody id, add-button id, "none" checkbox id]]
    $salnBoards = [
        'declarant' => [
            'pane' => 'declarant_board', 'label' => 'Declarant', 'icon' => 'bi-person-fill', 'profile' => 'personal-info',
            'tabs' => [
                'children-info'       => ['Children', 'children', 'children_list', 'btn_add_children', null],
                'real-properties'     => ['Real properties', 'real', 'real_properties_list', 'btn_add_real_properties', null],
                'personal-properties' => ['Personal properties', 'personal', 'personal_properties_list', 'btn_add_personal_properties', null],
                'liabilities'         => ['Liabilities', 'liabilities', 'liabilities_list', 'btn_add_liabilities', null],
                'business-interests'  => ['Business interests', 'business', 'business_interest_list', 'btn_add_business_interest', 'business_interest_checkbox'],
                'relatives'           => ['Relatives in gov\'t', 'relatives', 'government_relative_list', 'btn_add_government_relative', 'government_relatives_checkbox'],
                'marriages'           => ['Other marriages', 'marriages', 'marriages_list', 'btn_add_marriages', null],
            ],
        ],
        'spouse' => [
            'pane' => 'spouse_board', 'label' => 'Spouse', 'icon' => 'bi-people-fill', 'profile' => 'spouse_personal-info',
            'tabs' => [
                'spouse_real-properties'     => ['Real properties', 'real', 'spouse_real_properties_list', 'spouse_btn_add_real_properties', null],
                'spouse_personal-properties' => ['Personal properties', 'personal', 'spouse_personal_properties_list', 'spouse_btn_add_personal_properties', null],
                'spouse_liabilities'         => ['Liabilities', 'liabilities', 'spouse_liabilities_list', 'spouse_btn_add_liabilities', null],
                'spouse_business-interests'  => ['Business interests', 'business', 'spouse_business_interest_list', 'spouse_btn_add_business_interest', 'spouse_business_interest_checkbox'],
            ],
        ],
    ];

    // Flat reading order of every pane, for the "Next section" buttons.
    $salnOrder = [];
    foreach ($salnBoards as $board) {
        $salnOrder[] = ['board' => $board['pane'], 'pane' => $board['profile'], 'label' => $board['label'] . ' profile'];
        foreach ($board['tabs'] as $paneId => $tab) {
            $salnOrder[] = ['board' => $board['pane'], 'pane' => $paneId, 'label' => ($board['pane'] === 'spouse_board' ? 'Spouse ' . lcfirst($tab[0]) : $tab[0])];
        }
    }
    $salnNext = function ($paneId) use ($salnOrder) {
        foreach ($salnOrder as $i => $step) {
            if ($step['pane'] === $paneId) return $salnOrder[$i + 1] ?? null;
        }
        return null;
    };
@endphp

<div class="modal fade sf-modal" id="saln_form_modal" tabindex="-1" aria-labelledby="salnFormModalLabel" aria-hidden="true"
     data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-heading">
                    <div class="modal-icon"><i class="bi bi-file-earmark-text"></i></div>
                    <div>
                        <h5 class="modal-title" id="salnFormModalLabel">SALN Declaration</h5>
                        <p class="modal-sub">Sworn Statement of Assets, Liabilities and Net Worth</p>
                    </div>
                </div>
                <button type="button" class="btn-close ms-auto" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body sf-body" id="saln_form_body">
                <div id="saln_loader_overlay" class="saln-loader-overlay" style="display: none;">
                    <div class="loader-content">
                        <div class="premium-spinner"></div>
                        <div class="loader-text fw-bold">Loading your SALN</div>
                        <div class="loader-subtext text-muted">Fetching the records for this statement...</div>
                    </div>
                </div>

                {{-- How it works --}}
                <div class="sf-guide">
                    <i class="bi bi-info-circle"></i>
                    <div>
                        <strong>How to fill this in:</strong>
                        <span>1. Set the statement details below.</span>
                        <span>2. Go through each section. Rows in a list are saved as soon as you click <i class="bi bi-check-lg"></i>.</span>
                        <span>3. Click <strong>Save declaration</strong> to save the profile and ID details.</span>
                    </div>
                </div>

                {{-- Step 1: statement details --}}
                <section class="sf-card">
                    <div class="sf-card-head">
                        <div class="sf-card-icon"><i class="bi bi-calendar2-check"></i></div>
                        <div class="sf-card-text">
                            <h6>Statement details</h6>
                            <p>Which SALN this is and how it is filed.</p>
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-lg-4">
                            <label class="sf-label">Assets declared as of</label>
                            <div class="sf-date">
                                <select class="form-select" id="as_of_month" aria-label="Month">
                                    @foreach(['January','February','March','April','May','June','July','August','September','October','November','December'] as $m)
                                        <option value="{{ $m }}" {{ $m == 'December' ? 'selected' : '' }}>{{ $m }}</option>
                                    @endforeach
                                </select>
                                <select class="form-select" id="as_of_day" aria-label="Day">
                                    @for($d = 1; $d <= 31; $d++)
                                        <option value="{{ $d }}" {{ $d == 31 ? 'selected' : '' }}>{{ $d }}</option>
                                    @endfor
                                </select>
                                <select class="form-select" id="as_of_year" aria-label="Year">
                                    @for($y = 2020; $y <= date('Y'); $y++)
                                        <option value="{{ $y }}" {{ $y == date('Y') ? 'selected' : '' }}>{{ $y }}</option>
                                    @endfor
                                </select>
                            </div>
                            <div class="sf-hint">Annual SALN is usually as of December 31.</div>
                        </div>

                        <div class="col-lg-4">
                            <label class="sf-label">Reason for filing</label>
                            <div class="sf-seg" role="radiogroup" aria-label="Reason for filing">
                                <input type="radio" class="btn-check" value="Assumption of office" name="compliance_for" id="comp_assumption">
                                <label for="comp_assumption">Assumption</label>
                                <input type="radio" class="btn-check" value="Annual Filing" name="compliance_for" id="comp_annual" checked>
                                <label for="comp_annual">Annual</label>
                                <input type="radio" class="btn-check" value="Exit" name="compliance_for" id="comp_exit">
                                <label for="comp_exit">Exit</label>
                            </div>
                            <div class="sf-hint" id="sf_compliance_hint"></div>
                        </div>

                        <div class="col-lg-4">
                            <label class="sf-label">Filing with spouse</label>
                            <div class="sf-seg" role="radiogroup" aria-label="Filing with spouse">
                                <input type="radio" class="btn-check" value="Joint Filing" name="filing_type" id="filing_joint">
                                <label for="filing_joint">Joint</label>
                                <input type="radio" class="btn-check" value="Separate Filing" name="filing_type" id="filing_separate">
                                <label for="filing_separate">Separate</label>
                                <input type="radio" class="btn-check" value="Not Applicable" name="filing_type" id="filing_na" checked>
                                <label for="filing_na">Not applicable</label>
                            </div>
                            <div class="sf-hint" id="sf_filing_hint"></div>
                        </div>
                    </div>
                </section>

                {{-- Whose data --}}
                <div class="sf-who" role="tablist">
                    @foreach($salnBoards as $key => $board)
                        <button class="sf-who-btn {{ $loop->first ? 'active' : '' }}" id="tab_{{ $board['pane'] }}"
                                data-bs-toggle="tab" data-bs-target="#{{ $board['pane'] }}" type="button" role="tab">
                            <i class="bi {{ $board['icon'] }}"></i>
                            <span>{{ $board['label'] }}</span>
                            <small>{{ $key === 'declarant' ? 'Your own information' : 'Your spouse\'s information' }}</small>
                        </button>
                    @endforeach
                </div>

                <div class="tab-content">
                    @foreach($salnBoards as $key => $board)
                        <div class="tab-pane fade {{ $loop->first ? 'show active' : '' }}" id="{{ $board['pane'] }}" role="tabpanel">
                            <ul class="nav nav-tabs sf-tabs" role="tablist">
                                <li class="nav-item">
                                    <button class="nav-link active" id="tab_{{ $board['profile'] }}" data-bs-toggle="tab"
                                            data-bs-target="#{{ $board['profile'] }}" type="button" role="tab">Profile &amp; ID</button>
                                </li>
                                @foreach($board['tabs'] as $paneId => $tab)
                                    <li class="nav-item">
                                        <button class="nav-link" id="tab_{{ $paneId }}" data-bs-toggle="tab"
                                                data-bs-target="#{{ $paneId }}" type="button" role="tab">
                                            {{ $tab[0] }}<span class="sf-count" data-sf-count="#{{ $tab[2] }}"></span>
                                        </button>
                                    </li>
                                @endforeach
                            </ul>

                            <div class="tab-content">
                                {{-- Profile & ID --}}
                                @php $p = $key === 'declarant' ? 'declarant' : 'spouse'; $next = $salnNext($board['profile']); @endphp
                                <div class="tab-pane fade show active" id="{{ $board['profile'] }}" role="tabpanel">
                                    <section class="sf-card">
                                        <div class="sf-card-head">
                                            <div class="sf-card-icon"><i class="bi {{ $key === 'declarant' ? 'bi-person-badge' : 'bi-person-heart' }}"></i></div>
                                            <div class="sf-card-text">
                                                <h6>{{ $key === 'declarant' ? 'Your profile & government ID' : 'Spouse profile & government ID' }}</h6>
                                                <p>
                                                    @if($key === 'declarant')
                                                        Your name comes from your Personal Data Sheet. Check your position, office and a valid government ID.
                                                    @else
                                                        Leave this blank if you have no spouse. The government ID is the one your spouse will present.
                                                    @endif
                                                </p>
                                            </div>
                                        </div>
                                        <div class="row g-3">
                                            <div class="col-md-4">
                                                <label class="sf-label" for="{{ $p }}_fname">First name</label>
                                                <input type="text" class="form-control" id="{{ $p }}_fname" {{ $p === 'declarant' ? 'readonly' : '' }}/>
                                            </div>
                                            <div class="col-md-4">
                                                <label class="sf-label" for="{{ $p }}_mname">Middle name</label>
                                                <input type="text" class="form-control" id="{{ $p }}_mname" {{ $p === 'declarant' ? 'readonly' : '' }}/>
                                            </div>
                                            <div class="col-md-4">
                                                <label class="sf-label" for="{{ $p }}_lname">Last name</label>
                                                <input type="text" class="form-control" id="{{ $p }}_lname" {{ $p === 'declarant' ? 'readonly' : '' }}/>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="sf-label" for="{{ $p }}_position">Position</label>
                                                <input type="text" class="form-control" id="{{ $p }}_position" placeholder="e.g. Nurse II"/>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="sf-label" for="{{ $p }}_agency">Agency / office</label>
                                                <input type="text" class="form-control" id="{{ $p }}_agency"/>
                                            </div>
                                            <div class="col-12">
                                                <label class="sf-label" for="{{ $p }}_address">Office address</label>
                                                <input type="text" class="form-control" id="{{ $p }}_address"/>
                                            </div>
                                            <div class="col-md-4">
                                                <label class="sf-label" for="{{ $p }}_issued_id">Government-issued ID</label>
                                                <input type="text" class="form-control" id="{{ $p }}_issued_id" placeholder="e.g. Passport, UMID"/>
                                            </div>
                                            <div class="col-md-4">
                                                <label class="sf-label" for="{{ $p }}_id_no">ID number</label>
                                                <input type="text" class="form-control" id="{{ $p }}_id_no"/>
                                            </div>
                                            <div class="col-md-4">
                                                <label class="sf-label" for="{{ $p }}_date_issued">Date issued</label>
                                                <input type="date" class="form-control" id="{{ $p }}_date_issued"/>
                                            </div>
                                        </div>
                                        @include('modals.partials.saln_form_next', ['next' => $next])
                                    </section>
                                </div>

                                {{-- List sections --}}
                                @foreach($board['tabs'] as $paneId => $tab)
                                    @php
                                        [$tabLabel, $sectionKey, $tbodyId, $addBtnId, $noneId] = $tab;
                                        $s = $salnListSections[$sectionKey];
                                        $isSpouse = $key === 'spouse';
                                        $next = $salnNext($paneId);
                                    @endphp
                                    <div class="tab-pane fade" id="{{ $paneId }}" role="tabpanel">
                                        <section class="sf-card">
                                            <div class="sf-card-head">
                                                <div class="sf-card-icon"><i class="bi {{ $s['icon'] }}"></i></div>
                                                <div class="sf-card-text">
                                                    <h6>{{ $isSpouse ? 'Spouse ' . lcfirst($s['title']) : $s['title'] }}</h6>
                                                    <p>{{ $s['help'] }}</p>
                                                </div>
                                                <button type="button" class="btn btn-primary btn-sm sf-add" id="{{ $addBtnId }}">
                                                    <i class="bi bi-plus-lg me-1"></i>{{ $s['add'] }}
                                                </button>
                                            </div>

                                            @if($noneId)
                                                <label class="sf-none" for="{{ $noneId }}">
                                                    <input class="form-check-input" type="checkbox" id="{{ $noneId }}">
                                                    <span>{{ $isSpouse ? 'My spouse does not have any business interest or financial connection.' : $s['none'] }}</span>
                                                </label>
                                            @endif

                                            <div class="table-responsive sf-table-shell">
                                                <table class="table align-middle mb-0">
                                                    <thead>
                                                        <tr>
                                                            @foreach($s['cols'] as $col)
                                                                <th>{!! $col !!}</th>
                                                            @endforeach
                                                            <th class="text-center sf-col-actions">Actions</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody id="{{ $tbodyId }}"></tbody>
                                                </table>
                                                <div class="sf-empty">
                                                    <i class="bi bi-inbox"></i>
                                                    <div>Nothing added yet.</div>
                                                    <small>Click <strong>{{ $s['add'] }}</strong> to add one{{ $noneId ? ', or tick the box above if you have none' : '' }}.</small>
                                                </div>
                                            </div>
                                            @include('modals.partials.saln_form_next', ['next' => $next])
                                        </section>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="modal-footer sf-footer">
                <div class="sf-status">
                    <span class="sf-status-label">Filing</span>
                    <span class="sf-status-value" id="footer_filing_mode_display">ANNUAL FILING</span>
                </div>
                <div class="d-flex flex-wrap gap-2 ms-auto">
                    <button type="button" class="btn btn-modal-close" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-outline-primary" id="btn_print_saln">
                        <i class="bi bi-printer me-1"></i>Preview PDF
                    </button>
                    <button type="button" class="btn btn-modal-save" id="btn_save_and_finalize">
                        <i class="bi bi-shield-check me-1"></i>Save declaration
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

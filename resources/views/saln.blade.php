@extends('master')
@section('content')
    <div class="d-flex justify-content-between">
        <div class="pagetitle">
            <h1>SWORN STATEMENT OF ASSETS, LIABILITIES AND NET WORTH</h1>
        </div>
        <div>
            <button class="btn btn-success" id="btn_print_saln">PRINT SALN</button>
        </div>
    </div>

    <section class="section profile mt-3">
        <div class="card">
            <div class="card-body">
                <div class="card bg-info mt-2">
                    <div class="card-body p-2">
                        <strong>NOTE:</strong>
                        <p>Declarant's name and Spouse name are found in your <strong style="font-style: italic">Personal Data Sheet (PDS)</strong></p>
                    </div>
                </div>
                <div class="row mt-2">
                    <div class="col-md-6">
                        <h5 class="card-title fw-bold">Declarant</h5>
                        <div class="row mb-3">
                            <label for="fullName" class="col-md-4 col-lg-3 col-form-label">Firstname</label>
                            <div class="col-md-8 col-lg-9">
                                <input type="text" class="form-control" id="declarant_fname" readonly/>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <label for="fullName" class="col-md-4 col-lg-3 col-form-label">Middlename</label>
                            <div class="col-md-8 col-lg-9">
                                <input type="text" class="form-control" id="declarant_mname" readonly/>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <label for="fullName" class="col-md-4 col-lg-3 col-form-label">Lastname</label>
                            <div class="col-md-8 col-lg-9">
                                <input type="text" class="form-control" id="declarant_lname" readonly/>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <label for="fullName" class="col-md-4 col-lg-3 col-form-label">Position</label>
                            <div class="col-md-8 col-lg-9">
                                <input type="text" class="form-control" id="declarant_position"/>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <label for="fullName" class="col-md-4 col-lg-3 col-form-label">Agency/Office</label>
                            <div class="col-md-8 col-lg-9">
                                <input type="text" class="form-control" id="declarant_agency"/>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <label for="fullName" class="col-md-4 col-lg-3 col-form-label">Office Address</label>
                            <div class="col-md-8 col-lg-9">
                                <input type="text" class="form-control" id="declarant_address"/>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <label for="fullName" class="col-md-4 col-lg-3 col-form-label">Government Issued ID</label>
                            <div class="col-md-8 col-lg-9">
                                <input type="text" class="form-control" id="declarant_issued_id"/>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <label for="fullName" class="col-md-4 col-lg-3 col-form-label">ID No.</label>
                            <div class="col-md-8 col-lg-9">
                                <input type="text" class="form-control" id="declarant_id_no"/>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <label for="fullName" class="col-md-4 col-lg-3 col-form-label">Date Issued</label>
                            <div class="col-md-8 col-lg-9">
                                <input type="date" class="form-control" id="declarant_date_issued"/>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <h5 class="card-title fw-bold">Spouse</h5>
                        <div class="row mb-3">
                            <label for="fullName" class="col-md-4 col-lg-3 col-form-label">Firstname</label>
                            <div class="col-md-8 col-lg-9">
                                <input type="text" class="form-control" id="spouse_fname" readonly/>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <label for="fullName" class="col-md-4 col-lg-3 col-form-label">Middlename</label>
                            <div class="col-md-8 col-lg-9">
                                <input type="text" class="form-control" id="spouse_mname" readonly/>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <label for="fullName" class="col-md-4 col-lg-3 col-form-label">Lastname</label>
                            <div class="col-md-8 col-lg-9">
                                <input type="text" class="form-control" id="spouse_lname" readonly/>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <label for="fullName" class="col-md-4 col-lg-3 col-form-label">Position</label>
                            <div class="col-md-8 col-lg-9">
                                <input type="text" class="form-control" id="spouse_position"/>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <label for="fullName" class="col-md-4 col-lg-3 col-form-label">Agency/Office</label>
                            <div class="col-md-8 col-lg-9">
                                <input type="text" class="form-control" id="spouse_agency"/>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <label for="fullName" class="col-md-4 col-lg-3 col-form-label">Office
                                Address</label>
                            <div class="col-md-8 col-lg-9">
                                <input type="text" class="form-control" id="spouse_address"/>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <label for="fullName" class="col-md-4 col-lg-3 col-form-label">Government Issued
                                ID</label>
                            <div class="col-md-8 col-lg-9">
                                <input type="text" class="form-control" id="spouse_issued_id"/>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <label for="fullName" class="col-md-4 col-lg-3 col-form-label">ID No.</label>
                            <div class="col-md-8 col-lg-9">
                                <input type="text" class="form-control" id="spouse_id_no"/>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <label for="fullName" class="col-md-4 col-lg-3 col-form-label">Date Issued</label>
                            <div class="col-md-8 col-lg-9">
                                <input type="date" class="form-control" id="spouse_date_issued"/>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="float-end">
                    <button class="btn btn-success" id="btn_save_declarant">SAVE CHANGES</button>
                </div>
            </div>
        </div>
        <div class="card">
            <div class="card-body">
                <div id="assets">
                    <h5 class="card-title fw-bold">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>Unmarried children below eighteen (18) years of age living in declarant's household
                            </div>
                            <div>
                                <button class="btn btn-sm btn-success" id="btn_add_children">ADD NEW</button>
                            </div>
                        </div>
                    </h5>
                    <div class="card">
                        <div class="card-body p-0  m-0">
                            <div class="table-responsive">
                                <table class="table table-bordered text-center">
                                    <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th style="width: 25%">Date of birth</th>
                                        <th colspan="2" style="width: 10%"></th>
                                    </tr>
                                    </thead>
                                    <tbody id="children_list">
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <hr/>
                    <h5 class="card-title fw-bold">
                        Assets, Liabilities and Networth
                        <div class="text-black" style="font-size: 12px">(Including those of the spouse and unmarried
                            children below eighteen (18) years of age living in declarant’s household)
                        </div>
                    </h5>
                    <h6 class="card-title fw-bold">1. Assets</h6>
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <h6 class="card-title text-dark">A. Real Properties</h6>
                        </div>
                        <div>
                            <button class="btn btn-sm btn-success" id="btn_add_real_properties">ADD NEW</button>
                        </div>
                    </div>
                    <div class="card">
                        <div class="card-body p-0  m-0">
                            <div class="table-responsive">
                                <table class="table table-bordered text-center">
                                    <thead>
                                    <tr>
                                        <th rowspan="2">
                                            <div>Description</div>
                                            <div style="font-size: 12px">(e.g. lot, house and lot, condominium and
                                                improvements)
                                            </div>
                                        </th>
                                        <th rowspan="2">
                                            <div>Kind</div>
                                            <div style="font-size: 12px">
                                                (e.g. residential, commercial, industrial, agricultural and mixed use)
                                            </div>
                                        </th>
                                        <th rowspan="2">Exact location</th>
                                        <th>Assessed value</th>
                                        <th>Current fair market value</th>
                                        <th colspan="2">Acquisition</th>
                                        <th>Acquisition cost</th>
                                        <th rowspan="2" colspan="2" style="width: 10%"></th>
                                    </tr>
                                    <tr>
                                        <th colspan="2">
                                            <div style="font-size: 12px">
                                                (As found in the Tax Declaration of Real Property)
                                            </div>
                                        </th>
                                        <th style="width: 5%">Year</th>
                                        <th style="width: 5%">Mode</th>
                                    </tr>
                                    </thead>
                                    <tbody id="real_properties_list"></tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <h6 class="card-title text-dark">B. Personal Properties</h6>
                        </div>
                        <div>
                            <button class="btn btn-sm btn-success" id="btn_add_personal_properties">ADD NEW</button>
                        </div>
                    </div>
                    <div class="card">
                        <div class="card-body p-0  m-0">
                            <div class="table-responsive">
                                <table class="table table-bordered text-center">
                                    <thead>
                                    <tr>
                                        <th>Gadgets/Appliances</th>
                                        <th>Various Years</th>
                                        <th>Acquisition Cost/Amount</th>
                                        <th colspan="2" style="width: 10%"></th>
                                    </tr>

                                    </thead>
                                    <tbody id="personal_properties_list">
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <h6 class="card-title fw-bold">2. Liabilities</h6>
                        </div>
                        <div>
                            <button class="btn btn-sm btn-success" id="btn_add_liabilities">ADD NEW</button>
                        </div>
                    </div>
                    <div class="card">
                        <div class="card-body p-0  m-0">
                            <div class="table-responsive">
                                <table class="table table-bordered text-center">
                                    <thead>
                                    <tr>
                                        <th>Nature</th>
                                        <th>Name of Creditors</th>
                                        <th>Outstanding Balance</th>
                                        <th style="width: 10%"></th>
                                    </tr>
                                    </thead>
                                    <tbody id="liabilities_list">
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                <hr/>
            </div>
        </div>
        <div class="card">
            <div class="card-body">
                <div id="business_interest">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <h5 class="card-title fw-bold">
                                Business Interests and Financial Connections
                                <div class="text-black" style="font-size: 12px">(of Declarant /Declarant’s spouse/
                                    Unmarried Children Below Eighteen (18) years of Age Living in Declarant’s Household)
                                </div>
                            </h5>
                        </div>
                        <div>
                            <button class="btn btn-sm btn-success" id="btn_add_business_interest">ADD NEW</button>
                        </div>
                    </div>
                    <div class="card">
                        <div class="card-body p-0  m-0">
                            <div class="table-responsive">
                                <table class="table table-bordered text-center">
                                    <thead>
                                    <tr>
                                        <th>Name of Entity/Business Enterprise</th>
                                        <th>Business Address</th>
                                        <th>Nature of Business Interest &/or Financial Connection</th>
                                        <th>Date of acquisition of interest or connection</th>
                                        <th style="width: 10%"></th>
                                    </tr>
                                    </thead>
                                    <tbody id="business_interest_list"></tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                <hr/>
            </div>
        </div>
        <div class="card">
            <div class="card-body">
                <div id="government_relatives">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <h5 class="card-title fw-bold">
                                Relatives in the government service
                                <div class="text-black" style="font-size: 12px">(Within the Fourth Degree of
                                    Consanguinity or Affinity. Include also Bilas, Balae and Inso)
                                </div>
                            </h5>
                        </div>
                        <div>
                            <button class="btn btn-sm btn-success" id="btn_add_government_relative">ADD NEW</button>
                        </div>
                    </div>
                    <div class="card">
                        <div class="card-body p-0  m-0">
                            <div class="table-responsive">
                                <table class="table table-bordered text-center">
                                    <thead>
                                    <tr>
                                        <th>Name of relative</th>
                                        <th>Relationship</th>
                                        <th>Position</th>
                                        <th>Agency/Office & Address</th>
                                        <th style="width: 10%"></th>
                                    </tr>
                                    </thead>
                                    <tbody id="government_relative_list">
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
    @include('modals.saln')
@stop
@section('js')
    @include('js.saln')
@stop

@extends('master')
@section('content')

    <section class="section profile">
        <div class="card">
            <div class="card-body">
                <div class="text-end">
                    <p class="p-0 mb-0">Revised as of January 2015</p>
                    <p class="p-0 mb-0">Per CSC Resolution No. 1500088</p>
                    <p class="p-0 mb-0">Promulgated on January 23, 2015</p>
                </div>
                <div class="text-center">
                    <div class="mt-3 fw-bold">
                        SWORN STATEMENT OF ASSETS, LIABILITIES AND NET WORTH
                    </div>
                    <div>As of _______________________________</div>
                    <div>(Required by R.A 6713</div>
                    <div class="mt-2">
                        <strong>Note:</strong>
                        Husband and wife who are both public officials and employees may file the required statements
                        jointly or separately.
                        <div class="w-50 m-auto">
                            <div class="d-flex justify-content-between">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" value="" id="flexCheckDefault">
                                    <label class="form-check-label" for="flexCheckDefault">
                                        Joint Filing
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" value="" id="flexCheckDefault">
                                    <label class="form-check-label" for="flexCheckDefault">
                                        Separate Filing
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" value="" id="flexCheckDefault">
                                    <label class="form-check-label" for="flexCheckDefault">
                                        Not Applicable
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <h5 class="card-title fw-bold">Declarant</h5>
                        <div class="row mb-3">
                            <label for="fullName" class="col-md-4 col-lg-3 col-form-label">Firstname</label>
                            <div class="col-md-8 col-lg-9">
                                <input type="text" class="form-control" id="spouse_fname"/>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <label for="fullName" class="col-md-4 col-lg-3 col-form-label">Middlename</label>
                            <div class="col-md-8 col-lg-9">
                                <input type="text" class="form-control" id="spouse_mname"/>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <label for="fullName" class="col-md-4 col-lg-3 col-form-label">Lastname</label>
                            <div class="col-md-8 col-lg-9">
                                <input type="text" class="form-control" id="spouse_lname"/>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <label for="fullName" class="col-md-4 col-lg-3 col-form-label">Position</label>
                            <div class="col-md-8 col-lg-9">
                                <input type="text" class="form-control" id="spouse_fname"/>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <label for="fullName" class="col-md-4 col-lg-3 col-form-label">Agency/Office</label>
                            <div class="col-md-8 col-lg-9">
                                <input type="text" class="form-control" id="spouse_mname"/>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <label for="fullName" class="col-md-4 col-lg-3 col-form-label">Office Address</label>
                            <div class="col-md-8 col-lg-9">
                                <input type="text" class="form-control" id="spouse_lname"/>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <label for="fullName" class="col-md-4 col-lg-3 col-form-label">Government Issued ID</label>
                            <div class="col-md-8 col-lg-9">
                                <input type="text" class="form-control" id="spouse_fname"/>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <label for="fullName" class="col-md-4 col-lg-3 col-form-label">ID No.</label>
                            <div class="col-md-8 col-lg-9">
                                <input type="text" class="form-control" id="spouse_mname"/>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <label for="fullName" class="col-md-4 col-lg-3 col-form-label">Date Issued</label>
                            <div class="col-md-8 col-lg-9">
                                <input type="text" class="form-control" id="spouse_lname"/>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <h5 class="card-title fw-bold">Spouse</h5>
                        <div class="row mb-3">
                            <label for="fullName" class="col-md-4 col-lg-3 col-form-label">Firstname</label>
                            <div class="col-md-8 col-lg-9">
                                <input type="text" class="form-control" id="spouse_fname"/>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <label for="fullName" class="col-md-4 col-lg-3 col-form-label">Middlename</label>
                            <div class="col-md-8 col-lg-9">
                                <input type="text" class="form-control" id="spouse_mname"/>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <label for="fullName" class="col-md-4 col-lg-3 col-form-label">Lastname</label>
                            <div class="col-md-8 col-lg-9">
                                <input type="text" class="form-control" id="spouse_lname"/>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <label for="fullName" class="col-md-4 col-lg-3 col-form-label">Position</label>
                            <div class="col-md-8 col-lg-9">
                                <input type="text" class="form-control" id="spouse_fname"/>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <label for="fullName" class="col-md-4 col-lg-3 col-form-label">Agency/Office</label>
                            <div class="col-md-8 col-lg-9">
                                <input type="text" class="form-control" id="spouse_mname"/>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <label for="fullName" class="col-md-4 col-lg-3 col-form-label">Office
                                Address</label>
                            <div class="col-md-8 col-lg-9">
                                <input type="text" class="form-control" id="spouse_lname"/>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <label for="fullName" class="col-md-4 col-lg-3 col-form-label">Government Issued
                                ID</label>
                            <div class="col-md-8 col-lg-9">
                                <input type="text" class="form-control" id="spouse_fname"/>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <label for="fullName" class="col-md-4 col-lg-3 col-form-label">ID No.</label>
                            <div class="col-md-8 col-lg-9">
                                <input type="text" class="form-control" id="spouse_mname"/>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <label for="fullName" class="col-md-4 col-lg-3 col-form-label">Date Issued</label>
                            <div class="col-md-8 col-lg-9">
                                <input type="text" class="form-control" id="spouse_lname"/>
                            </div>
                        </div>
                    </div>
                </div>
                <hr/>
                <div id="assets">
                    <h5 class="card-title fw-bold">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>Unmarried children below eighteen (18) years of age living in declarant's household
                            </div>
                            <div>
                                <button class="btn btn-sm btn-success">ADD NEW</button>
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
                                        <th style="width: 15%">Age</th>
                                        <th colspan="2" style="width: 10%"></th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <tr>
                                        <td>N/A</td>
                                        <td>N/A</td>
                                        <td>N/A</td>
                                    </tr>
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
                            <button class="btn btn-sm btn-success">ADD NEW</button>
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
                                        <th>Exact location</th>
                                        <th>Assessed value</th>
                                        <th>Current fair market value</th>
                                        <th>Acquisition</th>
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
                                    <tbody>
                                    <tr>
                                        <td>N/A</td>
                                        <td>N/A</td>
                                        <td>N/A</td>
                                    </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <h6 class="card-title text-dark">B. Personal Properties</h6>
                        </div>
                        <div>
                            <button class="btn btn-sm btn-success">ADD NEW</button>
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
                                    <tbody>
                                    <tr>
                                        <td>N/A</td>
                                        <td>N/A</td>
                                        <td>N/A</td>
                                        <td></td>
                                        <td></td>
                                    </tr>
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
                            <button class="btn btn-sm btn-success">ADD NEW</button>
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
                                    <tbody>
                                    <tr>
                                        <td>N/A</td>
                                        <td>N/A</td>
                                        <td>N/A</td>
                                        <td>N/A</td>
                                    </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                <hr/>
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
                            <button class="btn btn-sm btn-success">ADD NEW</button>
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
                                    <tbody>
                                    <tr>
                                        <td>N/A</td>
                                        <td>N/A</td>
                                        <td>N/A</td>
                                        <td>N/A</td>
                                    </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                <hr/>
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
                            <button class="btn btn-sm btn-success">ADD NEW</button>
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
                                    <tbody>
                                    <tr>
                                        <td>N/A</td>
                                        <td>N/A</td>
                                        <td>N/A</td>
                                        <td>N/A</td>
                                    </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
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

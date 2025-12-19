
<table style="width:100%; font-size:9px;">
    <tr>
        <td style="width:70%">
            <h1 class="fw-bold">ANNEX C</h1>
        </td>
        <td style="width:30%; text-align:right;">
            <div>2025 SALN Form</div>
            <div>Per CSC Resolution No. 2500632</div>
            <div>Promulgated on 25 June 2025</div>
        </td>
    </tr>
</table>

<!-- TITLE -->
<div style="text-align:center; margin-top:20px;">
    <div style="font-size:14px; font-weight:bold;">
        SWORN STATEMENT OF ASSETS, LIABILITIES, AND NET WORTH
    </div>
    <div style="font-size: 13px">As of
        <u style="margin-left: 5px">{{ $as_of_month }}{{ $as_of_day }}, {{ $as_of_year }}</u>
    </div>
    <div>(Additional sheet/s for the exclusive properties of the declarant's spouse and unmarried children <br />below eighteen (18) years of age living in declarant's household)</div>
</div>
<div style="text-align: center;">
    <table id="declarant" style="text-align: center;">
        <tr>
            <td class="fw-bold" style="width: 11%">NAME:</td>
            <td style="width: 39%">
                <table style="text-align: center" class="box">
                    <tr>
                        <td style="font-size: 13px">
                            {{ isset($declarant->lname) ? strtoupper($declarant->lname) : '' }}
                        </td>
                        <td>
                            {{ isset($declarant->fname) ? strtoupper($declarant->fname) : '' }}
                            @if(isset($declarant->suffix))
                                @php
                                    $suffixes = ['Jr.', 'Sr.', 'I', 'II', 'III', 'IV', 'V'];
                                @endphp

                                @if(in_array($declarant->suffix, $suffixes))
                                    <span style="text-transform: uppercase">
                                      {{ $declarant->suffix }}
                                     </span>
                                @endif
                            @endif
                        </td>
                        <td>
                            @if(isset($declarant->mname) && !empty($declarant->mname[0]))
                                {{ strtoupper($declarant->mname[0]) }}.
                            @else
                                
                            @endif
                        </td>
                    </tr>
                </table>
            </td>
            <td style="width: 5%"></td>
            <td class="fw-bold" style="width: 15%">POSITION:</td>
            <td style="width: 30%" class="border-bottom">{{ isset($declarant->position) ? strtoupper($declarant->position) : '' }}</td>
        </tr>
        <tr>
            <td></td>
            <td class="border-top">
                <table style="text-align: center">
                    <tr>
                        <td>(Family Name)</td>
                        <td>(First Name)</td>
                        <td>(M.I)</td>
                    </tr>
                </table>
            </td>
            <td></td>
            <td class="fw-bold">AGENCY/OFFICE:</td>
            <td class="border-bottom">{{ isset($declarant->agency) ? strtoupper($declarant->agency) : '' }}</td>
        </tr>
    </table>
    <div class="border-bottom" style="margin-top: 20px"></div>
    <div class="border-bottom" style="margin-top: 2px"></div>
    <div style="margin-top: 10px;text-decoration: underline" class="fw-bold title">
        ASSETS, LIABILITIES AND NET WORTH
    </div>
</div>
<div class="fw-bold" style="margin-top: 10px;font-size: 13px">
    1. ASSETS
</div>
<div class="fw-bold" style="margin-top: 10px;margin-left: 30px;font-size: 13px">
    a. Real Properties
</div>
<table id="assets" style="text-align: center;margin-top: 10px;font-size: 13px">
    <tr class="fw-bold gray border-top border-left border-right">
        <td rowspan="2" class="border-right">
            <div class="fw-bold" style="font-size: 10px">DESCRIPTION</div>
            <div style="font-size: 8px;font-style: unset;margin-top: 5px">
                (e.g. lot, house and<br/>lot,condominium<br/>and improvements)
            </div>
        </td>
        <td rowspan="2" class="border-right">
            <div class="fw-bold" style="font-size: 10px">KIND</div>
            <div style="font-size: 8px;font-style: unset;">(e.g. residential,<br/>commercial,
                industrial,<br/>agricultural
                and mixed<br/>use)
            </div>
        </td>
        <td rowspan="2" class="fw-bold border-right" style="font-size: 10px">
            EXACT<br/>LOCATION
        </td>
        <td class="border-right" style="font-size: 10px">
            ASSESSED<br/>VALUE
        </td>
        <td class="border-right" style="font-size: 10px">CURRENT FAIR<br/>MARKET VALUE</td>
        <td colspan="2" class="border-right" style="font-size: 10px">ACQUISITION</td>
        <td rowspan="2" style="font-size: 10px">ACQUISITION<br/>COST</td>
    </tr>
    <tr class="fw-bold gray border-bottom border-left border-right">
        <td colspan="2" class="border-top border-right">
            <div style="font-size: 8px;font-style: normal !important;">
                (As found in the Tax Declaration of<br/>Real Property)
            </div>
        </td>
        <td class="fw-bold border-top border-right" style="font-size: 10px">YEAR</td>
        <td class="fw-bold border-top border-right" style="font-size: 10px">MODE</td>
    </tr>
    @forelse($spouse_real_properties as $key)
        <tr class="border-bottom border-left border-right assets" style="font-size: 13px">
            <td class="fw-bold border-right" style="width: 10%">{{ strtoupper($key['description'] ?? '') }}</td>
            <td class="fw-bold border-right" style="width: 10%">{{ strtoupper($key['kind'] ?? '') }}</td>
            <td class="fw-bold border-right" style="width: 18%">{{ strtoupper($key['exact_location'] ?? '') }}</td>
            <td class="fw-bold border-right" style="width: 8%;">{{ strtoupper($key['assessed_value'] ?? '') }}</td>
            <td class="fw-bold border-right" style="width: 14%;">{{ strtoupper($key['current_fair'] ?? '') }}</td>
            <td class="fw-bold border-right" style="width: 8%">{{ strtoupper($key['acquisition_year'] ?? '') }}</td>
            <td class="fw-bold border-right" style="width: 8%">{{ strtoupper($key['acquisition_mode'] ?? '') }}</td>
            <td class="fw-bold" style="width: 14%;">{{ strtoupper($key['acquisition_cost'] ?? '') }}</td>
        </tr>
    @empty
        <tr class="border-bottom border-left border-right assets" style="font-size: 13px">
            <td colspan="8" class="fw-bold" style="text-align: center">N/A</td>
        </tr>
    @endforelse
</table>
<div class="fw-bold" style="margin-top: 20px;margin-left: 30px;font-size: 13px">
    b. Personal Properties
</div>
<table style="text-align: center;margin-top: 10px;font-size: 13px">
    <tr class="fw-bold gray border-top border-left">
        <td class="border-right">DESCRIPTION</td>
        <td class="border-right border-top">ACQUISITION YEAR</td>
        <td class="border-top border-right">AQUISITION<br/>COST/AMOUNT</td>
    </tr>
    @forelse($spouse_personal_properties as $key)
        <tr class="border-top border-bottom border-right border-left" style="font-size: 13px;">
            <td class="fw-bold border-right" style="width: 50%;">{{ strtoupper($key['gadget'] ?? '') }}</td>
            <td class="fw-bold border-right" style="width: 36%;">{{ strtoupper($key['various_years'] ?? '') }}</td>
            <td class="fw-bold" style="width: 14%;">{{ strtoupper($key['acquisition_cost'] ?? '') }}</td>
        </tr>
    @empty
        <tr class="border-top border-bottom border-right border-left" style="font-size: 13px;">
            <td colspan="3" class="fw-bold" style="text-align: center">N/A</td>
        </tr>
    @endforelse
</table>

<div class="fw-bold" style="margin-top: 20px;margin-left: 30px;font-size: 13px">
    2. LIABILITIES
</div>
<table style="text-align: center;margin-top: 10px;font-size: 13px">
    <tr class="fw-bold gray border-top border-left" style="font-size: 10px">
        <td class="border-right">NATURE</td>
        <td class="border-right border-top">NAME OF CREDITORS</td>
        <td class="border-top border-right">OUTSTANDING BALANCE</td>
    </tr>
    @forelse($spouse_liabilities as $key)
        <tr class="border-top border-right border-left" style="font-size: 13.33px">
            <td class="fw-bold border-right" style="width: 35%">{{ strtoupper($key['nature'] ?? '') }}</td>
            <td class="fw-bold border-right" style="width: 30%">{{ strtoupper($key['creditors_name'] ?? '') }}</td>
            <td class="fw-bold" style="width: 14%">{{ strtoupper($key['outstanding_balance'] ?? '') }}</td>
        </tr>
    @empty
        <tr class="border-top border-right border-left" style="font-size: 13.33px">
            <td colspan="3" class="fw-bold" style="text-align: center">N/A</td>
        </tr>
    @endforelse
</table>
<div class="border-bottom" style="margin-top: 20px"></div>
<div class="border-bottom" style="margin-top: 2px"></div>
<div style="margin-top: 20px;font-size: 13px;text-decoration: underline;text-align: center" class="fw-bold">
    BUSINESS INTERESTS AND FINANCIAL CONNECTIONS
</div>
<table style="text-align: center;margin-top: 10px;font-size: 13px">
    <tr class="fw-bold gray border-bottom border-top border-left" style="font-size: 10.66px">
        <td class="border-right">NAME OF ENTITY/BUSINESS<br/>ENTERPRISE</td>
        <td class="border-right border-top">BUSINESS ADDRESS</td>
        <td class="border-right border-top">NATURE OF BUSINESS<br/>INTEREST &/OR FINANCIAL<br/>CONNECTION</td>
        <td class="border-top border-right">DATE OF ACQUISITION OF <br/>INTEREST OR CONNECTION</td>
    </tr>
    @forelse($spouse_business as $key)
        <tr class="border-bottom border-right border-left" style="font-size: 13px">
            <td class="fw-bold border-right" style="width: 25%">{{ strtoupper($key['enterprise'] ?? '') }}</td>
            <td class="fw-bold border-right" style="width: 25%">{{ strtoupper($key['address'] ?? '') }}</td>
            <td class="fw-bold border-right" style="width: 25%">{{ strtoupper($key['nature'] ?? '') }}</td>
            <td class="fw-bold" style="width: 25%">{{ strtoupper($key['date_acquisition'] ?? '') }}</td>
        </tr>
    @empty
        <tr class="border-bottom border-right border-left" style="font-size: 13px">
            <td colspan="4" class="fw-bold" style="text-align: center">N/A</td>
        </tr>
    @endforelse
</table>

<div
    style="position: absolute; bottom: 10px; text-align: center; font-size: 13px; font-family: 'Times New Roman'; width: 100%;">
    Page {{ 2 + ($show_annex_b ? 1 : 0) + 1 }} of {{ $total_pages }}
</div>
<div
    style="width:200px;border-top: solid 3px black;padding-left:15px;padding-right:15px;position: absolute; bottom: 10px; right: 0; text-align: center; font-size: 13px; font-family: 'Times New Roman';margin-right:20px">
    <small>Signature/Initial of Declarant</small>
</div>

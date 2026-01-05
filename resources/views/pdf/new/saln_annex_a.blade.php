<table style="width:100%; font-size:9px;">
    <tr>
        <td style="width:70%">
            <h1 class="fw-bold">ANNEX A</h1>
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
    <div style="font-size:13px; margin-top:2px;">
        (As required by R.A. No. 6713)
    </div>

    <div style="font-size:12px;text-align: center;margin-top:20px">
        <div class="fw-bold">COMPLIANCE FOR:</div>
        <div style="display: inline-block;width: 33%">
            @if($compliance_for == 'Assumption of office')
                <input type="checkbox" style="display: inline" checked/>
                <div style="display: inline;font-style: italic;">Assumption of office as of <span
                        style="text-decoration: underline">{{ $as_of_month }} {{$as_of_day  }}, {{ $as_of_year }}</span>
                </div>
            @else
                <input type="checkbox" style="display: inline"/>
                <div style="display: inline;font-style: italic;">Assumption of office as of ___________
                </div>
            @endif

        </div>
        <div style="display: inline-block;width: 33%">
            @if($compliance_for == 'Annual Filing')
                <input type="checkbox" style="display: inline" checked/>
                <div style="display: inline;font-style: italic;">Annual filing as of December 31,<span
                        style="border-bottom: 1px solid black;padding-left: 10px;padding-right: 10px">{{ $as_of_year }}</span>
                </div>
            @else
                <input type="checkbox" style="display: inline"/>
                <div style="display: inline;font-style: italic;">Annual filing as of December 31,_______
                </div>
            @endif
        </div>
        <div style="display: inline-block;width: 33%">
            @if($compliance_for == 'Exit')
                <input type="checkbox" style="display: inline" checked/>
                <div style="display: inline;font-style: italic;">Exit as of
                    <span
                        style="text-decoration: underline">{{ $as_of_month }} {{$as_of_day  }}, {{ $as_of_year }}</span>
                </div>
            @else
                <input type="checkbox" style="display: inline"/>
                <div style="display: inline;font-style: italic;">Exit as of _____________
                </div>
            @endif
        </div>
    </div>
</div>
<div class="border-bottom" style="margin-top: 10px"></div>
<div class="border-bottom" style="margin-top: 2px"></div>
<div style="text-align: center;">
    <table id="declarant" style="text-align: center;font-size: 13px">
        <tr>
            <td class="fw-bold" style="width: 11%">DECLARANT:</td>
            <td style="width: 39%">
                <table style="text-align: center" class="box">
                    <tr>
                        <td>
                            {{ strtoupper($declarant->lname) }}
                        </td>
                        <td>
                            {{ strtoupper($declarant->fname) }}
                            @if($declarant->suffix)
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
                            @if($declarant->mname)
                                {{ strtoupper($declarant->mname[0]) }}.
                            @endif
                        </td>
                    </tr>
                </table>
            </td>
            <td style="width: 5%"></td>
            <td class="fw-bold" style="width: 15%">POSITION:</td>
            <td style="width: 30%" class="border-bottom">{{ strtoupper($declarant->position) }}</td>
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
            <td class="border-bottom">{{ strtoupper($declarant->agency) }}</td>
        </tr>
        @php
            if (!function_exists('splitAddressByWords')) {
                function splitAddressByWords($address, $maxLength) {
                    $words = explode(' ', $address); // Split address into words
                    $line1 = '';
                    $line2 = '';

                    foreach ($words as $word) {
                        if (strlen($line1) + strlen($word) + 1 <= $maxLength) { // +1 for space
                            $line1 .= ($line1 === '' ? '' : ' ') . $word;
                        } else {
                            $line2 .= ($line2 === '' ? '' : ' ') . $word;
                        }
                    }

                    return [$line1, $line2];
                }
            }

            if (!function_exists('adjustFontSize')) {
                function adjustFontSize($text, $baseSize = 13) {
                    $length = strlen($text);
                    if ($length > 60) return 'font-size: 7px;';
                    if ($length > 45) return 'font-size: 8px;';
                    if ($length > 35) return 'font-size: 9px;';
                    if ($length > 20) return 'font-size: 10px;';
                    if ($length > 15) return 'font-size: 11px;';
                    return "font-size: {$baseSize}px;";
                }
            }

            [$addressLine1, $addressLine2] = splitAddressByWords($address, 45); // Adjust 40 to your desired max length
        @endphp

        <tr>
            <td class="fw-bold"></td>
            <td class="border-bottom">
            </td>
            <td></td>
            <td class="fw-bold">OFFICE ADDRESS:</td>
            <td class="border-bottom">{{ strtoupper($declarant->office) }}</td>
        </tr>

        <tr>
            <td></td>
            <td class="border-bottom">
                {{ strtoupper($addressLine2) }}
            </td>
            <td></td>
            <td></td>
            <td class="border-bottom"></td>
        </tr>
        <tr>
            <td class="fw-bold">SPOUSE:</td>
            <td style="width: 39%">
                <table style="text-align: center" class="box">
                    <tr>
                        <td>
                            @if(isset($spouse->spouse_lname) && strtoupper($spouse->spouse_lname) !== 'N/A' && strtoupper($spouse->spouse_lname) !== 'NA')
                                {{ strtoupper($spouse->spouse_lname) }}
                            @else
                                N/A
                            @endif
                        </td>
                        <td>
                            @if(isset($spouse->spouse_fname) && strtoupper($spouse->spouse_fname) !== 'N/A' && strtoupper($spouse->spouse_fname) !== 'NA')
                                {{ strtoupper($spouse->spouse_fname) }}
                            @else
                                N/A
                            @endif
                        </td>
                        <td>
                            @if(isset($spouse->spouse_mname) && strtoupper($spouse->spouse_mname) !== 'N/A' && strtoupper($spouse->spouse_mname) !== 'NA' && !empty($spouse->spouse_mname[0]))
                                {{ strtoupper($spouse->spouse_mname[0]) }}.
                            @else
                                N/A
                            @endif
                        </td>
                    </tr>
                </table>
            </td>
            <td></td>
            <td class="fw-bold">POSITION:</td>
            <td class="border-bottom">
                @if(isset($spouse->position) && strtoupper($spouse->position) !== 'N/A' && strtoupper($spouse->position) !== 'NA')
                    {{ strtoupper($spouse->position) }}
                @else
                    N/A
                @endif
            </td>
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
            <td class="border-bottom">
                @if(isset($spouse->agency) && strtoupper($spouse->agency) !== 'N/A' && strtoupper($spouse->agency) !== 'NA')
                    {{ strtoupper($spouse->agency) }}
                @else
                    N/A
                @endif
            </td>
        </tr>
        <tr>
            <td></td>
            <td></td>
            <td></td>
            <td class="fw-bold">OFFICE ADDRESS:</td>
            <td class="border-bottom">
                @if(isset($spouse->office) && strtoupper($spouse->office) !== 'N/A' && strtoupper($spouse->office) !== 'NA')
                    {{ strtoupper($spouse->office) }}
                @else
                    N/A
                @endif
            </td>
        </tr>
    </table>
    <div style="text-align: left;margin-top: 20px;" class="fw-bold">
        <div>SPOUSES, WHO ARE BOTH PUBLIC OFFICIALS OR EMPLOYEES, MAY FILE THE SALN JOINTLY OR SEPARATELY.</div>
        <div>THE DECLARANT SHALL CHECK THE APPROPRIATE BOX</div>
    </div>
    <div style="font-size:13px;margin-top:5px">
        <div style="display: inline-block;width: 32%">
            @if($filing_type == 'Joint Filing')
                <input type="checkbox" style="display: inline" checked/>
            @else
                <input type="checkbox" style="display: inline"/>
            @endif
            <div style="display: inline;font-style: italic;">Joint Filing</div>
        </div>
        <div style="display: inline-block;width: 32%">
            @if($filing_type == 'Separate Filing')
                <input type="checkbox" style="display: inline" checked/>
            @else
                <input type="checkbox" style="display: inline"/>
            @endif
            <div style="display: inline;font-style: italic;">Separate Filing</div>
        </div>
        <div style="display: inline-block;width: 32%">
            @if($filing_type == 'Not Applicable')
                <input type="checkbox" style="display: inline" checked/>
            @else
                <input type="checkbox" style="display: inline"/>
            @endif
            <div style="display: inline;font-style: italic;">Not Applicable</div>
        </div>
    </div>

    <div style="margin-top: 10px;font-size: 13px;text-align: left" class="title fw-bold">
        IF WITH MULTIPLE MARRIAGES, INDICATE NAME(S) OF SPOUSES, OTHERWISE CHECK THE "NOT APPLICABLE" BOX.
    </div>
    <table style="font-size: 13px;text-align: center;margin-top: 5px;padding-left: 25px">
        <tr>
            <td>
                <div style="border-bottom: 1px solid black"> {{ $marriages[0]["name"] }}</div>
            </td>
            <td>
                <div style="display: inline-block;">
                    @if($marriages[0]["name"] == "")
                        <input type="checkbox" style="display: inline" checked/>
                    @else
                        <input type="checkbox" style="display: inline"/>
                    @endif
                    <div style="display: inline;font-style: italic;">Not Applicable</div>
                </div>
            </td>
        </tr>
        <tr>
            <td>
                <div style="border-bottom: 1px solid black"> {{ $marriages[1]["name"] }}</div>
            </td>
            <td></td>
        </tr>
    </table>

    <div class="border-bottom" style="margin-top: 20px"></div>
    <div class="border-bottom" style="margin-top: 2px"></div>

    <div style="margin-top: 10px;text-decoration: underline" class="title fw-bold">
        UNMARRIED CHILDREN BELOW EIGHTEEN (18) YEARS OF AGE LIVING IN DECLARANT'S HOUESHOLD
    </div>
    <table style="font-size: 13px;text-align: center;margin-top: 20px">
        <tr class="fw-bold" style="font-size: 10px">
            <td></td>
            <td>NAME OF CHILD</td>
            <td></td>
            <td>AGE</td>
            <td></td>
        </tr>

        @forelse($children as $key)
            <tr style="font-size: 13.33px">
                <td style="width: 8%"></td>
                <td class="border-bottom">{{ strtoupper($key['name'] ?? '') }}</td>
                <td style="width: 2%"></td>
                <td class="border-bottom" style="width: 15%">{{ strtoupper($key['age'] ?? '') }}</td>
                <td style="width: 8%"></td>
            </tr>
        @empty
            <tr style="font-size: 13.33px">
                <td style="width: 8%"></td>
                <td class="border-bottom" style="text-align: center">N/A</td>
                <td style="width: 2%"></td>
                <td class="border-bottom" style="width: 15%; text-align: center">N/A</td>
                <td style="width: 8%"></td>
            </tr>
        @endforelse
    </table>
    <div class="border-bottom" style="margin-top: 20px"></div>
    <div class="border-bottom" style="margin-top: 2px"></div>
    <div style="margin-top: 10px;text-decoration: underline" class="fw-bold title">
        ASSETS, LIABILITIES AND NET WORTH
    </div>
    <div style="text-align: center;" class="title">
        (Including those of the spouse and unmarried children below eighteen (18) years of age living in declarant's
        household)
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
                (As found in the Tax Declaration of<br/>Real Property, if available)
            </div>
        </td>
        <td class="fw-bold border-top border-right" style="font-size: 10px">YEAR</td>
        <td class="fw-bold border-top border-right" style="font-size: 10px">MODE</td>
    </tr>
    @forelse($real_properties as $key)
        <tr class="border-bottom border-left border-right assets" style="font-size: 13px">
            <td class="fw-bold border-right" style="width: 10%; padding: 0 4px; {{ adjustFontSize($key['description'] ?? '') }}">{{ strtoupper($key['description'] ?? '') }}</td>
            <td class="fw-bold border-right" style="width: 10%; padding: 0 4px; {{ adjustFontSize($key['kind'] ?? '', 11) }}">{{ strtoupper($key['kind'] ?? '') }}</td>
            <td class="fw-bold border-right" style="width: 18%; padding: 0 4px; {{ adjustFontSize($key['exact_location'] ?? '') }}">{{ strtoupper($key['exact_location'] ?? '') }}</td>
            <td class="fw-bold border-right" style="width: 8%; padding: 0 4px; {{ adjustFontSize($key['assessed_value'] ?? '') }}">{{ strtoupper($key['assessed_value'] ?? '') }}</td>
            <td class="fw-bold border-right" style="width: 14%; padding: 0 4px; {{ adjustFontSize($key['current_fair'] ?? '') }}">{{ strtoupper($key['current_fair'] ?? '') }}</td>
            <td class="fw-bold border-right" style="width: 8%; padding: 0 4px; {{ adjustFontSize($key['acquisition_year'] ?? '') }}">{{ strtoupper($key['acquisition_year'] ?? '') }}</td>
            <td class="fw-bold border-right" style="width: 8%; padding: 0 4px; {{ adjustFontSize($key['acquisition_mode'] ?? '', 11) }}">{{ strtoupper($key['acquisition_mode'] ?? '') }}</td>
            <td class="fw-bold" style="width: 14%; padding: 0 4px; {{ adjustFontSize($key['acquisition_cost'] ?? '') }}">{{ strtoupper($key['acquisition_cost'] ?? '') }}</td>
        </tr>
    @empty
        <tr class="border-bottom border-left border-right assets" style="font-size: 13px">
            <td class="fw-bold border-right" style="text-align: center">N/A</td>
            <td class="fw-bold border-right" style="text-align: center">N/A</td>
            <td class="fw-bold border-right" style="text-align: center">N/A</td>
            <td class="fw-bold border-right" style="text-align: center">N/A</td>
            <td class="fw-bold border-right" style="text-align: center">N/A</td>
            <td class="fw-bold border-right" style="text-align: center">N/A</td>
            <td class="fw-bold border-right" style="text-align: center">N/A</td>
            <td class="fw-bold" style="text-align: center">N/A</td>
        </tr>
    @endforelse

    <tr class="border-top" style="font-size: 13px;">
        <td colspan="7" style="text-align: right;" class="fw-bold">Subtotal:</td>
        <td style="text-align: left">
            <div class="fw-bold border-bottom" style="padding-left:10px;">
                {{ number_format($real_properties_subtotal,2) }}
            </div>
        </td>
    </tr>
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
    @forelse($personal_properties as $key)
        <tr class="border-top border-bottom border-right border-left" style="font-size: 13px;">
            <td class="fw-bold border-right" style="width: 50%; padding: 0 4px; {{ adjustFontSize($key['gadget'] ?? '') }}">{{ strtoupper($key['gadget'] ?? '') }}</td>
            <td class="fw-bold border-right" style="width: 36%; padding: 0 4px; {{ adjustFontSize($key['various_years'] ?? '') }}">{{ strtoupper($key['various_years'] ?? '') }}</td>
            <td class="fw-bold" style="width: 14%; padding: 0 4px; {{ adjustFontSize($key['acquisition_cost'] ?? '') }}">{{ strtoupper($key['acquisition_cost'] ?? '') }}</td>
        </tr>
    @empty
        <tr class="border-top border-bottom border-right border-left" style="font-size: 13px;">
            <td class="fw-bold border-right" style="width: 50%; text-align: center">N/A</td>
            <td class="fw-bold border-right" style="width: 36%; text-align: center">N/A</td>
            <td class="fw-bold" style="width: 14%; text-align: center">N/A</td>
        </tr>
    @endforelse

    <tr class="border-top" style="font-size: 13px;">
        <td colspan="2" style="text-align: right;" class="fw-bold">Subtotal:</td>
        <td style="text-align: left">
            <div class="fw-bold border-bottom" style="padding-left:10px;">
                {{ number_format($personal_properties_subtotal,2) }}
            </div>
        </td>
    </tr>
    <tr style="font-size: 13px">
        <td colspan="2" style="text-align: right;" class="fw-bold">TOTAL ASSETS</td>
        <td style="text-align: left">
            <div class="fw-bold border-bottom" style="padding-left:10px;border-bottom: solid 3px black">
                {{ number_format($grand_assets_total, 2) }}
            </div>
        </td>
    </tr>
</table>

<div
    style="position: absolute; bottom: 10px; text-align: center; font-size: 13px; font-family: 'Times New Roman'; width: 100%;">
    Page 1 of {{ $total_pages }}
</div>
<div
    style="width:200px;border-top: solid 3px black;padding-left:15px;padding-right:15px;position: absolute; bottom: 10px; right: 0; text-align: center; font-size: 13px; font-family: 'Times New Roman';margin-right:20px">
    <small>Signature/Initial of Declarant</small>
</div>

@if(count($real_properties_ext) > 0 || count($personal_properties_ext) > 0)
    {{-- Additional sheets handled via Annex B --}}
@endif

<div style="page-break-before: always !important;"></div>
<div class="fw-bold" style="margin-top: 20px;margin-left: 30px;font-size: 13px">
    2. LIABILITIES
</div>
<table style="text-align: center;margin-top: 10px;font-size: 13px">
    <tr class="fw-bold gray border-top border-left" style="font-size: 10px">
        <td class="border-right">NATURE</td>
        <td class="border-right border-top">NAME OF CREDITORS</td>
        <td class="border-top border-right">OUTSTANDING BALANCE</td>
    </tr>
    @forelse($liabilities as $key)
        <tr class="border-top border-right border-left" style="font-size: 13.33px">
            <td class="fw-bold border-right" style="width: 35%">{{ strtoupper($key['nature'] ?? '') }}</td>
            <td class="fw-bold border-right" style="width: 30%">{{ strtoupper($key['creditors_name'] ?? '') }}</td>
            <td class="fw-bold" style="width: 14%">{{ strtoupper($key['outstanding_balance'] ?? '') }}</td>
        </tr>
    @empty
        <tr class="border-top border-right border-left" style="font-size: 13.33px">
            <td class="fw-bold border-right" style="width: 35%; text-align: center">N/A</td>
            <td class="fw-bold border-right" style="width: 30%; text-align: center">N/A</td>
            <td class="fw-bold" style="width: 14%; text-align: center">N/A</td>
        </tr>
    @endforelse
    <tr class="border-top" style="font-size: 13px">
        <td colspan="2" style="text-align: right;" class="fw-bold">TOTAL LIABILITIES:</td>
        <td style="text-align: left">
            <div class="fw-bold border-bottom" style="padding-left:10px;">
                {{ number_format($grand_liabilities_total, 2) }}
            </div>
        </td>
    </tr>
    <tr style="font-size: 13.33px">
        <td colspan="2" style="text-align: right;" class="fw-bold">
            NET WORTH : Total Assets less Total Liabilities =
        </td>
        <td style="text-align: left">
            <div class="fw-bold border-bottom" style="padding-left:10px;border-bottom: solid 4px black !important;">
                {{ number_format($grand_net_worth, 2) }}
            </div>
        </td>
    </tr>
</table>
<div style="margin-top: 20px;font-size: 13px;text-decoration: underline;text-align: center" class="fw-bold">
    BUSINESS INTERESTS AND FINANCIAL CONNECTIONS
</div>
<div style="text-align: center;font-size: 10.66px;font-style: italic">
    (of Declarant /Declarant’s spouse/ Unmarried Children Below Eighteen (18) years of Age Living in Declarant’s
    Household)
    <div>
        @if($business_interest == '1')
            <input type="checkbox" style="display: inline" checked/>
        @else
            <input type="checkbox" style="display: inline"/>
        @endif
        <div style="display: inline;font-size: 13px">I/We do not have any business interest or financial
            connection.
        </div>
    </div>
</div>
<table style="text-align: center;margin-top: 10px;font-size: 13px">
    <tr class="fw-bold gray border-bottom border-top border-left" style="font-size: 10.66px">
        <td class="border-right">NAME OF ENTITY/BUSINESS<br/>ENTERPRISE</td>
        <td class="border-right border-top">BUSINESS ADDRESS</td>
        <td class="border-right border-top">NATURE OF BUSINESS<br/>INTEREST &/OR FINANCIAL<br/>CONNECTION</td>
        <td class="border-top border-right">DATE OF ACQUISITION OF <br/>INTEREST OR CONNECTION</td>
    </tr>
    @forelse($business as $key)
        <tr class="border-bottom border-right border-left" style="font-size: 13px">
            <td class="fw-bold border-right" style="width: 25%">{{ strtoupper($key['enterprise'] ?? '') }}</td>
            <td class="fw-bold border-right" style="width: 25%">{{ strtoupper($key['address'] ?? '') }}</td>
            <td class="fw-bold border-right" style="width: 25%">{{ strtoupper($key['nature'] ?? '') }}</td>
            <td class="fw-bold" style="width: 25%">{{ strtoupper($key['date_acquisition'] ?? '') }}</td>
        </tr>
    @empty
        <tr class="border-bottom border-right border-left" style="font-size: 13px">
            <td class="fw-bold border-right" style="width: 25%; text-align: center">N/A</td>
            <td class="fw-bold border-right" style="width: 25%; text-align: center">N/A</td>
            <td class="fw-bold border-right" style="width: 25%; text-align: center">N/A</td>
            <td class="fw-bold" style="width: 25%; text-align: center">N/A</td>
        </tr>
    @endforelse
</table>
<div style="margin-top: 20px;font-size: 13px;text-decoration: underline;text-align: center" class="fw-bold">
    RELATIVES IN THE GOVERNMENT SERVICE
</div>
<div style="text-align: center;font-size: 10.66px;font-style: italic">
    (Within the Fourth Degree of Consanguinity or Affinity. Include also Bilas, Balae and Inso)
    <div>
        @if($government_relatives == '1')
            <input type="checkbox" style="display: inline" checked/>
        @else
            <input type="checkbox" style="display: inline"/>
        @endif
        <div style="display: inline;font-size: 10.66px;font-style: italic">I/We do not know of any relative/s in the
            government service)
        </div>
    </div>
</div>
<table style="text-align: center;margin-top: 10px;font-size: 13px">
    <tr class="fw-bold gray border-bottom border-top border-left">
        <td class="border-right">NAME OF RELATIVE</td>
        <td class="border-right border-top">RELATIONSHIP</td>
        <td class="border-right border-top">POSITION</td>
        <td class="border-top border-right">NAME OF AGENCY/OFFICE AND ADDRESS</td>
    </tr>
    @forelse($relatives as $key)
        <tr class="border-bottom border-right border-left">
            <td class="fw-bold border-right" style="width: 25%">{{ strtoupper($key['name'] ?? '') }}</td>
            <td class="fw-bold border-right" style="width: 25%">{{ strtoupper($key['relationship'] ?? '') }}</td>
            <td class="fw-bold border-right" style="width: 15%">{{ strtoupper($key['position'] ?? '') }}</td>
            <td class="fw-bold" style="width: 35%">{{ strtoupper($key['agency'] ?? '') }}</td>
        </tr>
    @empty
        <tr class="border-bottom border-right border-left">
            <td class="fw-bold border-right" style="width: 25%; text-align: center">N/A</td>
            <td class="fw-bold border-right" style="width: 25%; text-align: center">N/A</td>
            <td class="fw-bold border-right" style="width: 15%; text-align: center">N/A</td>
            <td class="fw-bold" style="width: 35%; text-align: center">N/A</td>
        </tr>
    @endforelse
</table>

<div style="margin-top: 30px;font-size: 13px;text-align: justify;text-justify:inter-word" id="herby">
    <div style="text-indent: 50px;">I hereby certify that these are true and correct statements of my
        assets, liabilities, net worth,business interests and financial connections, including those of my spouse and
        unmarried
        children below eighteen (18) years of age living in my household, and that to the best of my knowledge, the
        above-enumerated
        are names of my relatives in the government within the fourth civil degree of consanguinity or affinity.
    </div>
    <div style="margin-top: 20px; text-indent: 50px;">I hereby authorize the Ombudsman or his/her
        duly authorized
        representative to obtain and secure from all appropriate government agencies, including the Bureau of Internal
        Revenue such documents that may show
        my assets, liabilities, net worth, business interests and financial connections, to include those of my spouse
        and unmarried children below 18 years of age living with me in my household covering previous years to include
        the year I first assumed office in government.
    </div>
</div>

<table style="font-size: 13px;margin-top: 20px">
    <tr>
        <td style="width: 5%">Date:</td>
        <td style="width: 20%" class="border-bottom">
            <div style="padding-left: 10px">
                {{ date('F j, Y',strtotime($print_date)) }}
            </div>
        </td>
        <td></td>
    </tr>
</table>
<table style="margin-top:30px;font-size: 13px">
    <tr>
        <td colspan="2" class="border-bottom"></td>
        <td></td>
        <td colspan="2" class="border-bottom">
            @if($filing_type == 'Separate Filing' || $filing_type == 'Not Applicable' || $spouse_signature == 0)
                <div style="text-align: center">N/A</div>
            @endif
        </td>
    </tr>
    <tr style="font-style: italic">
        <td colspan="2" style="text-align: center">Signature of Declarant</td>
        <td></td>
        <td colspan="2" style="text-align: center">Signature of Declarant</td>
    </tr>
</table>
<table style="margin-top:20px;font-size: 13px">
    <tr>
        <td>Government Issued ID:</td>
        <td class="border-bottom">
            @if($declarant)
                {{strtoupper($declarant->government_id_issued)}}
            @endif
        </td>
        <td></td>
        <td>Government Issued ID:</td>
        <td class="border-bottom">
            @if($filing_type == 'Separate Filing' || $filing_type == 'Not Applicable')
                N/A
            @elseif($filing_type == 'Joint Filing' && $spouse)
                {{strtoupper($spouse->government_id_issued)}}
            @else

            @endif
        </td>
    </tr>
    <tr>
        <td>ID No.:</td>
        <td class="border-bottom">
            @if($declarant)
                {{strtoupper($declarant->government_id_no)}}
            @endif
        </td>
        <td></td>
        <td>ID No.:</td>
        <td class="border-bottom">
            @if($filing_type == 'Separate Filing' || $filing_type == 'Not Applicable')
                N/A
            @elseif($filing_type == 'Joint Filing' && $spouse)
                {{strtoupper($spouse->government_id_no)}}
            @else

            @endif
        </td>
    </tr>
    <tr>
        <td>Date Issued:</td>
        <td class="border-bottom" style="text-transform: uppercase">
            @if(isset($declarant->date_issued))
                {{ date('F j, Y', strtotime($declarant->date_issued)) }}
            @endif
        </td>
        <td></td>
        <td>Date Issued:</td>
        <td class="border-bottom" style="text-transform: uppercase">
            @if($filing_type == 'Separate Filing' || $filing_type == 'Not Applicable')
                N/A
            @elseif($filing_type == 'Joint Filing' && isset($spouse->date_issued))
                {{ date('F j, Y', strtotime($spouse->date_issued)) }}
            @else

            @endif
        </td>
    </tr>
</table>

<div style="margin-top: 30px; font-size: 13px;">
    <strong style="margin-left: 30px;">SUBSCRIBED AND SWORN</strong>
    to before me
    <span style="border-bottom: 1px solid black; display: inline-block; width: 100px; text-align: center;">
        {{ $before_me }}
    </span>
    day of
    <span style="border-bottom: 1px solid black; display: inline-block; width: 150px; text-align: center;" id="day-of">
        {{ $day_of }}
    </span>,
    affiant exhibiting to me the above-stated<br/>
    government-issued identification card.
</div>


<div style="text-align: center; font-size: 13px;margin-top:30px">
    <div>
        <div style="font-size: 11.7px; font-weight: bold; padding-bottom: 5px;">
            <span style="text-decoration: underline;">
                AGUSTIN D. AGOS, JR., MD, FPSGS, FPCS, MA Psy, DODT, PhD-OD, RODC, APRM™
            </span>
        </div>
        <div style="font-style: italic; margin-top: 5px;">
            (Person Administering Oath)
        </div>
    </div>
</div>

<div style="text-align:left;">
    <ol style="
        list-style-type: lower-roman;
        padding-left: 24px;   /* controls text start */
    ">
        <li style="padding-left:6px;">
            Position, Agency, and Address shall only be declared if the spouse is a public official or employee.
        </li>
        <li style="padding-left:6px;">
            Capital or paraphernal assets, and liabilities of the declarant’s spouse, and properties of children below
            18 years of age and living in the declarant’s household shall be disclosed
            using the additional sheets provided.
        </li>
        <li style="padding-left:6px;">
            Balae refers to the parent of one’s son or daughter-in-law; Bilas refers to a brother-in-law’s wife or
            sister-in-law’s husband; Inso refers to the appellation for the wife of an elder
            brother or male cousin.
        </li>
    </ol>
</div>


<div
    style="position: absolute; bottom: 10px; text-align: center; font-size: 13px; font-family: 'Times New Roman'; width: 100%;">
    Page 2 of {{ $total_pages }}
</div>

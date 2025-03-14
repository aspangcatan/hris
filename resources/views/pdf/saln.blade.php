<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Document</title>
    <style>
        @page {
            size: 612pt 1008pt !important;
        }

        @font-face {
            font-family: 'Bookman';
            src: url({{ storage_path("fonts/bookman.ttf") }}) format("truetype");
            font-weight: 800;
            font-style: normal;
        }

        html, body {
            padding: 20px;
            margin: 0px;
            font-size: 10.66px;
            font-family: 'Bookman';
        }

        table {
            width: 100%;
            table-layout: fixed !important;
            border-spacing: 0; /* Removes the cell spacing via CSS */
            border-collapse: collapse; /* Optional - if you don't want to have double border where cells touch */
        }

        table td {
            margin: 0px !important;
            height: 15px !important;
            padding: 1px !important;
            vertical-align: middle;
        }

        .title {
            font-size: 13.33px;
        }

        #declarant td {
            vertical-align: bottom;
        !important;
        }

        .assets td {
            height: 40px !important;
        }

        .border {
            border: solid 1px black;
        }

        .border-bottom {
            border-bottom: solid 1px black;
        }

        .border-right {
            border-right: solid 1px black;
        }

        .border-left {
            border-left: solid 1px black;
        }

        .border-top {
            border-top: solid 1px black;
        }

        .fw-bold {
            font-weight: bold;
        }

        .gray {
            background: rgb(234, 234, 234) !important;
        }
    </style>
</head>
<body>
<div style="font-size: 9.33px;margin-left: 82%">
    <div>Revised as of January 2015</div>
    <div>Per CSC Resolution No. 1500088</div>
    <div>Promulgated on January 23, 2015</div>
</div>

<div style="text-align: center;margin-top: 20px">
    <div style="font-weight: bolder;font-size: 16px;">SWORN STATEMENT OF ASSETS, LIABILITIES AND NET WORTH</div>
    <div style="font-size: 13px">As of <u style="margin-left: 5px">December
            31, {{ $as_of_year }}</u></div>
    <div style="font-size: 10.66px">(Required by R.A 6713)</div>
    <div style="font-size: 12.5px;margin-top: 15px">
        <strong>Note:</strong>
        <i>Husband and wife who are both public officials and employees may file the required statements jointly or
            separately.</i>
    </div>
    <div style="font-size:13.33px;margin-left: 20%;margin-right:20%;text-align: center">
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
    <table id="declarant" style="margin-top: 20px;text-align: center;">
        <tr>
            <td class="fw-bold" style="width: 11%">DECLARANT:</td>
            <td style="width: 39%">
                <table style="text-align: center" class="box">
                    <tr>
                        <td style="font-size: 13px">
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

            [$addressLine1, $addressLine2] = splitAddressByWords($address, 45); // Adjust 40 to your desired max length
        @endphp

        <tr>
            <td class="fw-bold">ADDRESS:</td>
            <td class="border-bottom">
                {{ strtoupper($addressLine1) }}
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
                        <td style="font-size: 13px">
                            @if(!empty($spouse->spouse_lname) && strtoupper($spouse->spouse_lname) !== 'N/A' && strtoupper($spouse->spouse_lname) !== 'NA')
                                {{ strtoupper($spouse->spouse_lname) }}
                            @else
                                N/A
                            @endif
                        </td>
                        <td>
                            @if(!empty($spouse->spouse_fname) && strtoupper($spouse->spouse_fname) !== 'N/A' && strtoupper($spouse->spouse_fname) !== 'NA')
                                {{ strtoupper($spouse->spouse_fname) }}
                            @else
                                N/A
                            @endif
                        </td>
                        <td>
                            @if(!empty($spouse->spouse_mname) && strtoupper($spouse->spouse_mname) !== 'N/A' && strtoupper($spouse->spouse_mname) !== 'NA')
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
                @if(!empty($spouse->position) && strtoupper($spouse->position) !== 'N/A' && strtoupper($spouse->position) !== 'NA')
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
                @if(!empty($spouse->agency) && strtoupper($spouse->agency) !== 'N/A' && strtoupper($spouse->agency) !== 'NA')
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
                @if(!empty($spouse->office) && strtoupper($spouse->office) !== 'N/A' && strtoupper($spouse->office) !== 'NA')
                    {{ strtoupper($spouse->office) }}
                @else
                    N/A
                @endif
            </td>
        </tr>
    </table>

    <div class="border-bottom" style="margin-top: 20px"></div>
    <div class="border-bottom" style="margin-top: 2px"></div>

    <div style="margin-top: 10px;text-decoration: underline" class="title fw-bold">
        UNMARRIED CHILDREN BELOW EIGHTEEN (18) YEARS OF AGE LIVING IN DECLARANT'S HOUESHOLD
    </div>
    <table style="font-size: 14px;text-align: center;margin-top: 20px">
        <tr class="fw-bold" style="font-size: 10px">
            <td></td>
            <td>NAME</td>
            <td></td>
            <td>DATE OF BIRTH</td>
            <td></td>
            <td>AGE</td>
            <td></td>
        </tr>

        @foreach($children as $key)
            <tr style="font-size: 13.33px">
                <td style="width: 8%"></td>
                <td class="border-bottom">{{ strtoupper($key['name']) }}</td>
                <td style="width: 2%"></td>
                <td class="border-bottom">
                    {{ $key['birthdate'] }}
                </td>
                <td style="width: 2%"></td>
                <td class="border-bottom">{{ strtoupper($key['age']) }}</td>
                <td style="width: 8%"></td>
            </tr>
        @endforeach
    </table>
    <div class="border-bottom" style="margin-top: 20px"></div>
    <div class="border-bottom" style="margin-top: 2px"></div>
    <div style="margin-top: 10px;text-decoration: underline" class="fw-bold title">
        ASSETS, LIABILITIES AND NETWORTH
    </div>
    <div style="text-align: center;" class="title">
        (Including those of the spouse and unmarried children below eighteen (18)<br/>years of age living in declarant's
        household)
    </div>
</div>
<div class="fw-bold" style="margin-top: 10px;font-size: 14px">
    1. ASSETS
</div>
<div class="fw-bold" style="margin-top: 10px;margin-left: 30px;font-size: 14px">
    a. Real Properties*
</div>
<table id="assets" style="text-align: center;margin-top: 10px;font-size: 12px">
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
    @foreach($real_properties as $key)
        <tr class="border-bottom border-left border-right assets" style="font-size: 11px">
            <td class="fw-bold border-right" style="width: 10%">{{ strtoupper($key['description']) }}</td>
            <td class="fw-bold border-right" style="width: 10%">{{ strtoupper($key['kind']) }}</td>
            <td class="fw-bold border-right" style="width: 18%">{{ strtoupper($key['exact_location']) }}</td>
            <td class="fw-bold border-right" style="width: 8%;">{{ strtoupper($key['assessed_value']) }}</td>
            <td class="fw-bold border-right" style="width: 14%;">{{ strtoupper($key['current_fair']) }}</td>
            <td class="fw-bold border-right" style="width: 8%">{{ strtoupper($key['acquisition_year']) }}</td>
            <td class="fw-bold border-right" style="width: 8%">{{ strtoupper($key['acquisition_mode']) }}</td>
            <td class="fw-bold" style="width: 14%;">{{ strtoupper($key['acquisition_cost']) }}</td>
        </tr>
    @endforeach
</table>
<div class="fw-bold" style="margin-top: 20px;margin-left: 30px;font-size: 14px">
    b. Personal Properties*
</div>
<table style="text-align: center;margin-top: 10px;font-size: 12px">
    <tr class="fw-bold gray border-top border-left">
        <td class="border-right">DESCRIPTION</td>
        <td class="border-right border-top">YEAR ACQUIRED</td>
        <td class="border-top border-right">AQUISITION<br/>COST/AMOUNT</td>
    </tr>
    @foreach($personal_properties as $key)
        <tr class="border-top border-bottom border-right border-left" style="font-size: 11px;">
            <td class="fw-bold border-right" style="width: 50%;">{{ strtoupper($key['gadget']) }}</td>
            <td class="fw-bold border-right" style="width: 36%;">{{ strtoupper($key['various_years']) }}</td>
            <td class="fw-bold" style="width: 14%;">{{ strtoupper($key['acquisition_cost']) }}</td>
        </tr>
    @endforeach

    <tr class="border-top" style="font-size: 13.33px;">
        <td colspan="2" style="text-align: right;" class="fw-bold">Subtotal:</td>
        <td style="text-align: left">
            <div class="fw-bold border-bottom" style="padding-left:10px;">
                {{ number_format($personal_properties_subtotal,2) }}
            </div>
        </td>
    </tr>
    <tr>
        <td colspan="3" style="color: white">N/A</td>
    </tr>
    @if(count($personal_properties_extended) < 1)
        <tr style="font-size: 14px">
            <td colspan="2" style="text-align: right;" class="fw-bold">TOTAL ASSETS (a+b):</td>
            <td style="text-align: left">
                <div class="fw-bold border-bottom" style="padding-left:10px;border-bottom: solid 3px black">
                    {{ number_format($real_properties_subtotal + $personal_properties_subtotal,2)   }}
                </div>
            </td>
        </tr>
    @endif
</table>
<div style="font-size: 13.33px;font-style: italic">* Additional sheet/s may be used, if necessary.</div>
<div
    style="position: absolute; bottom: 10px; text-align: center; font-size: 16px; color: gray; font-style: italic; font-family: 'Times New Roman'; width: 100%;">
    @if(count($personal_properties_extended) < 1)
        Page 1 of 2
    @else
        Page 1 of 3
    @endif
</div>

@if(count($personal_properties_extended) > 0)
    <div style="page-break-before: always !important;"></div>
    @include('pdf.saln_extree_sheet',
        [
            'personal_properties'=>$personal_properties_extended,
            'sub_total' =>  number_format($personal_properties_extended_subtotal,2),
            'total_assets'=> number_format($real_properties_subtotal + $personal_properties_subtotal + $personal_properties_extended_subtotal,2)
        ])
@endif

<div style="page-break-before: always !important;"></div>
<div class="fw-bold" style="margin-top: 20px;margin-left: 30px;font-size: 14px">
    2. LIABILITIES*
</div>
<table style="text-align: center;margin-top: 10px;font-size: 12px">
    <tr class="fw-bold gray border-top border-left" style="font-size: 10px">
        <td class="border-right">NATURE</td>
        <td class="border-right border-top">NAME OF CREDITORS</td>
        <td class="border-top border-right">OUTSTANDING BALANCE</td>
    </tr>
    @foreach($liabilities as $key)
        <tr class="border-top border-right border-left" style="font-size: 13.33px">
            <td class="fw-bold border-right" style="width: 35%">{{ strtoupper($key['nature']) }}</td>
            <td class="fw-bold border-right" style="width: 30%">{{ strtoupper($key['creditors_name']) }}</td>
            <td class="fw-bold" style="width: 14%">{{ strtoupper($key['outstanding_balance']) }}</td>
        </tr>
    @endforeach
    <tr class="border-top" style="font-size: 13.33px">
        <td colspan="2" style="text-align: right;" class="fw-bold">TOTAL LIABILITIES:</td>
        <td style="text-align: left">
            <div class="fw-bold border-bottom" style="padding-left:10px;">
                {{ number_format($liabilities_total,2) }}
            </div>
        </td>
    </tr>
    <tr style="font-size: 13.33px">
        <td colspan="2" style="text-align: right;" class="fw-bold">
            NET WORTH : Total Assets less Total Liabilities =
        </td>
        <td style="text-align: left">
            <div class="fw-bold border-bottom" style="padding-left:10px;border-bottom: solid 4px black !important;">
                @if(count($personal_properties_extended) > 0)
                    {{ number_format(($real_properties_subtotal + $personal_properties_subtotal + $personal_properties_extended_subtotal) - $liabilities_total,2) }}
                @else
                    {{ number_format(($real_properties_subtotal + $personal_properties_subtotal) - $liabilities_total,2) }}
                @endif
            </div>
        </td>
    </tr>
</table>
<div style="font-size: 13.33px;font-style: italic">* Additional sheet/s may be used, if necessary.</div>
<div style="margin-top: 20px;font-size: 13.33px;text-decoration: underline;text-align: center" class="fw-bold">
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
        <div style="display: inline;font-size: 13.33px">I/We do not have any business interest or financial
            connection.
        </div>
    </div>
</div>
<table style="text-align: center;margin-top: 10px;font-size: 12px">
    <tr class="fw-bold gray border-bottom border-top border-left" style="font-size: 10.66px">
        <td class="border-right">NAME OF ENTITY/BUSINESS<br/>ENTERPRISE</td>
        <td class="border-right border-top">BUSINESS ADDRESS</td>
        <td class="border-right border-top">NATURE OF BUSINESS<br/>INTEREST &/OR FINANCIAL<br/>CONNECTION</td>
        <td class="border-top border-right">DATE OF ACQUISITION OF <br/>INTEREST OR CONNECTION</td>
    </tr>
    @foreach($business as $key)
        <tr class="border-bottom border-right border-left" style="font-size: 13.33px">
            <td class="fw-bold border-right" style="width: 25%">{{ strtoupper($key['enterprise']) }}</td>
            <td class="fw-bold border-right" style="width: 25%">{{ strtoupper($key['address']) }}</td>
            <td class="fw-bold border-right" style="width: 25%">{{ strtoupper($key['nature']) }}</td>
            <td class="fw-bold" style="width: 25%">{{ strtoupper($key['date_acquisition']) }}</td>
        </tr>
    @endforeach
</table>
<div style="margin-top: 20px;font-size: 14px;text-decoration: underline;text-align: center" class="fw-bold">
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
<table style="text-align: center;margin-top: 10px;font-size: 10.66px">
    <tr class="fw-bold gray border-bottom border-top border-left">
        <td class="border-right">NAME OF RELATIVE</td>
        <td class="border-right border-top">RELATIONSHIP</td>
        <td class="border-right border-top">POSITION</td>
        <td class="border-top border-right">NAME OF AGENCY/OFFICE AND ADDRESS</td>
    </tr>
    @foreach($relatives as $key)
        <tr class="border-bottom border-right border-left">
            <td class="fw-bold border-right" style="width: 25%">{{ strtoupper($key['name']) }}</td>
            <td class="fw-bold border-right" style="width: 25%">{{ strtoupper($key['relationship']) }}</td>
            <td class="fw-bold border-right" style="width: 15%">{{ strtoupper($key['position']) }}</td>
            <td class="fw-bold" style="width: 35%">{{ strtoupper($key['agency']) }}</td>
        </tr>
    @endforeach
</table>

<div style="margin-top: 30px;font-size: 13.33px">
    <div><span style="margin-left: 50px">I</span> hereby certify that these are true and correct statements of my
        assets, liabilities, net worth,business interests and financial connections, including those of my spouse and
        unmarried
        children below eighteen (18) years of age living in my household, and that to the best of my knowledge, the
        above-enumerated
        are names of my relatives in the government within the fourth civil degree of consanguinity or affinity.
    </div>
    <div style="margin-top: 20px"><span style="margin-left: 50px">I</span> hereby authorize the Ombudsman or his/her
        duly authorized
        representative to obtain and secure from all appropriate government agencies, including the Bureau of Internal
        Revenue such documents that may show
        my assets, liabilities, net worth, business interests and financial connections, to include those of my spouse
        and unmarried children below 18 years of age living with me in my household covering previous years to include
        the year I first assumed office in government.
    </div>
</div>

<table style="font-size: 13.33px;margin-top: 20px">
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
<table style="margin-top:30px;font-size: 10.66px">
    <tr>
        <td colspan="2" class="border-bottom"></td>
        <td></td>
        <td colspan="2" class="border-bottom">
            @if($spouse_signature == 0)
                <div style="text-align: center">N/A</div>
            @endif
        </td>
    </tr>
    <tr style="font-style: italic">
        <td colspan="2" style="text-align: center">(Signature of Declarant)</td>
        <td></td>
        <td colspan="2" style="text-align: center">Signature of Co-Declarant/Spouse</td>
    </tr>
</table>
<table style="margin-top:20px;font-size: 10.66px">
    <tr>
        <td>Government Issued ID:</td>
        <td class="border-bottom">
            @if($declarant)
                {{strtoupper($declarant->government_id_issued)}}
            @else
                N/A
            @endif
        </td>
        <td></td>
        <td>Government Issued ID:</td>
        <td class="border-bottom">
            @if($spouse)
                {{strtoupper($spouse->government_id_issued)}}
            @else
                N/A
            @endif
        </td>
    </tr>
    <tr>
        <td>ID No.:</td>
        <td class="border-bottom">
            {{strtoupper($declarant->government_id_no)}}
        </td>
        <td></td>
        <td>ID No.:</td>
        <td class="border-bottom">
            @if($spouse)
                {{strtoupper($spouse->government_id_no)}}
            @else
                N/A
            @endif
        </td>
    </tr>
    <tr>
        <td>Date Issued:</td>
        <td class="border-bottom" style="text-transform: uppercase">
            @if($declarant)
                {{ date('F j, Y', strtotime($declarant->date_issued)) }}
            @else
                N/A
            @endif
        </td>
        <td></td>
        <td>Date Issued:</td>
        <td class="border-bottom" style="text-transform: uppercase">
            @if(isset($spouse->date_issued))
                {{ date('F j, Y', strtotime($spouse->date_issued)) }}
            @else
                N/A
            @endif
        </td>
    </tr>
</table>

<div style="margin-top: 30px; font-size: 14px;">
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


<div
    style="display: flex; justify-content: center; align-items: center; height: 100vh; text-align: center; font-size: 14px;margin-top:30px">
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

<div
    style="position: absolute; bottom: 10px; text-align: center; font-size: 16px; color: gray; font-style: italic; font-family: 'Times New Roman'; width: 100%;">
    @if(count($personal_properties_extended) < 1)
        Page 2 of 2
    @else
        Page 3 of 3
    @endif
</div>
<script>
    const getFontSize = (textLength) => {
        const baseSize = 9
        if (textLength >= baseSize) {
            textLength = baseSize - 2
        }
        const fontSize = baseSize - textLength
        return `${fontSize}vw`
    }

    const boxes = document.querySelectorAll('.box td')

    boxes.forEach(box => {
        box.style.fontSize = getFontSize(box.textContent.length)
    })
</script>
</body>
</html>

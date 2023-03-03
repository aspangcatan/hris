<div class="border-lg" style="margin-top:10px">
    <table class="w-100 h-100 no-spacing border" border="0" style="font-size: 11.5px !important;">
        <tr>
            <td class="border-dark-right bg-gray-white" style="width: 450px !important;">
                34. Are you related by consanguinity or affinity to the appointing or recommendation authority, or to
                the
                chief of bureau or office or to the person who has immediate supervision over you in the Office,
                Bureau or Department where you will be appointed,<br/>
            </td>
            <td class="v-center text-center">

            </td>
        </tr>
        <tr>
            <td class="border-dark-right bg-gray-white" style="padding-left: 20px !important;">a. within the third
                degree?
            </td>
            <td style="padding-left: 20px !important;">
                <div class="ml-1" style="display: inline;">
                    @if($survey[0]['answer'] == "YES")
                        <input type="checkbox" style="margin-bottom: -7px" checked/>
                    @else
                        <input type="checkbox" style="margin-bottom: -7px"/>
                    @endif
                    <span>YES</span>
                </div>
                <div class="ml-1" style="display: inline;">
                    @if($survey[0]['answer'] == "NO")
                        <input type="checkbox" style="margin-bottom: -7px" checked/>
                    @else
                        <input type="checkbox" style="margin-bottom: -7px"/>
                    @endif
                    <span>NO</span>
                </div>
            </td>
        </tr>
        <tr class="border-dark-bottom">
            <td class="border-dark-right bg-gray-white" style="padding-left: 20px !important;">b. within the fourth
                degree (for Local
                Government Unit - Career Employees)?
            </td>
            <td style="padding-left: 20px !important;padding-bottom: 10px !important;">
                <div class="ml-1" style="display: inline;">
                    @if($survey[1]['answer'] == "YES")
                        <input type="checkbox" style="margin-bottom: -7px" checked/>
                    @else
                        <input type="checkbox" style="margin-bottom: -7px"/>
                    @endif
                    <span>YES</span>
                </div>
                <div class="ml-1" style="display: inline;">
                    @if($survey[1]['answer'] == "NO")
                        <input type="checkbox" style="margin-bottom: -7px" checked/>
                    @else
                        <input type="checkbox" style="margin-bottom: -7px"/>
                    @endif
                    <span>NO</span>
                </div>
                <div style="margin-top:3px">If YES, give details:</div>
                <input class="underline" style="width: 95%" type="text" value="{{ $survey[1]['details'] }}"/>
            </td>
        </tr>
        <tr>
            <td class="border-dark-right bg-gray-white" style="width: 450px !important;">
                35. a. Have you ever been found guilty of any administrative offense?
            </td>
            <td style="padding-left: 20px !important" class="border-dark-bottom">
                <div class="ml-1" style="display: inline;">
                    @if($survey[2]['answer'] == "YES")
                        <input type="checkbox" style="margin-bottom: -7px" checked/>
                    @else
                        <input type="checkbox" style="margin-bottom: -7px"/>
                    @endif
                    <span>YES</span>
                </div>
                <div class="ml-1" style="display: inline;">
                    @if($survey[2]['answer'] == "NO")
                        <input type="checkbox" style="margin-bottom: -7px" checked/>
                    @else
                        <input type="checkbox" style="margin-bottom: -7px"/>
                    @endif
                    <span>NO</span>
                </div>
                <div style="margin-top:3px">If YES, give details:</div>
                <input style="width: 95%" type="text" class="underline" value="{{ $survey[2]['details'] }}"/>
            </td>
        </tr>
        <tr class="border-dark-bottom">
            <td class="border-dark-right bg-gray-white" style="padding-left: 20px !important;">
                b. Have you been criminally charged before any court?
            </td>
            <td style="padding-left: 20px !important">
                <div class="ml-1" style="display: inline;">
                    @if($survey[3]['answer'] == "YES")
                        <input type="checkbox" style="margin-bottom: -7px" checked/>
                    @else
                        <input type="checkbox" style="margin-bottom: -7px"/>
                    @endif
                    <span>YES</span>
                </div>
                <div class="ml-1" style="display: inline;">
                    @if($survey[3]['answer'] == "NO")
                        <input type="checkbox" style="margin-bottom: -7px" checked/>
                    @else
                        <input type="checkbox" style="margin-bottom: -7px"/>
                    @endif
                    <span>NO</span>
                </div>
                <div style="margin-top:3px">If YES, give details:</div>
                <div style="margin-top: 5px;margin-left: 20px">
                    <div style="display:inline-block">Date Filed:</div>
                    <input type="text" class="underline" style="display: inline-block;width: 77%"
                           value="{{ $survey[3]['details'] }}"/>
                </div>
                <div style="margin-bottom: 10px;">
                    <div style="display:inline-block">Status of Case/s:</div>
                    <input type="text" class="underline" style="display: inline-block;width: 71%"
                           value="{{ $survey[3]['extra'] }}"/>
                </div>
            </td>
        </tr>
        <tr class="border-dark-bottom">
            <td class="border-dark-right bg-gray-white">
                36. Have you ever been convicted of any crime or violation of any law, decree, ordinance or regulation
                by any court or tribunal?
            </td>
            <td style="padding-left: 20px !important" class="border-dark-bottom">
                <div class="ml-1" style="display: inline;">
                    @if($survey[4]['answer'] == "YES")
                        <input type="checkbox" style="margin-bottom: -7px" checked/>
                    @else
                        <input type="checkbox" style="margin-bottom: -7px"/>
                    @endif
                    <span>YES</span>
                </div>
                <div class="ml-1" style="display: inline;">
                    @if($survey[4]['answer'] == "NO")
                        <input type="checkbox" style="margin-bottom: -7px" checked/>
                    @else
                        <input type="checkbox" style="margin-bottom: -7px"/>
                    @endif
                    <span>NO</span>
                </div>
                <div style="margin-top:3px">If YES, give details:</div>
                <input style="width: 95%" type="text" class="underline" value="{{ $survey[4]['details'] }}"/>
            </td>
        </tr>
        <tr class="border-dark-bottom">
            <td class="border-dark-right bg-gray-white">
                37. Have you ever been separated from the service in any of the following modes: resignation,
                retirement, dropped from the rolls, dismissal,
                termination, end of term, finished contract or phased out (abolition) in the public or private sector?
            </td>
            <td style="padding-left: 20px !important" class="border-dark-bottom">
                <div class="ml-1" style="display: inline;">
                    @if($survey[5]['answer'] == "YES")
                        <input type="checkbox" style="margin-bottom: -7px" checked/>
                    @else
                        <input type="checkbox" style="margin-bottom: -7px"/>
                    @endif
                    <span>YES</span>
                </div>
                <div class="ml-1" style="display: inline;">
                    @if($survey[5]['answer'] == "NO")
                        <input type="checkbox" style="margin-bottom: -7px" checked/>
                    @else
                        <input type="checkbox" style="margin-bottom: -7px"/>
                    @endif
                    <span>NO</span>
                </div>
                <div style="margin-top:3px">If YES, give details:</div>
                <input style="width: 95%" type="text" class="underline" value="{{ $survey[5]['details'] }}"/>
            </td>
        </tr>
        <tr>
            <td class="border-dark-right bg-gray-white" style="width: 450px !important;">
                38. a. Have you ever been a candidate in a national or local election held within the last year (except
                Barangay election)?
            </td>
            <td style="padding-left: 20px !important;">
                <div class="ml-1" style="display: inline;">
                    @if($survey[6]['answer'] == "YES")
                        <input type="checkbox" style="margin-bottom: -7px" checked/>
                    @else
                        <input type="checkbox" style="margin-bottom: -7px"/>
                    @endif
                    <span>YES</span>
                </div>
                <div class="ml-1" style="display: inline;">
                    @if($survey[6]['answer'] == "NO")
                        <input type="checkbox" style="margin-bottom: -7px" checked/>
                    @else
                        <input type="checkbox" style="margin-bottom: -7px"/>
                    @endif
                    <span>NO</span>
                </div>
                <div style="margin-top:3px">If YES, give details:</div>
                <input style="width: 95%" type="text" class="underline" value="{{ $survey[6]['details'] }}"/>
            </td>
        </tr>
        <tr class="border-dark-bottom">
            <td class="border-dark-right bg-gray-white" style="padding-left: 20px !important;">
                b. Have you resigned from the government service during the three (3) month period before the last
                election to promote/actively
                campaign for a national or local candidate?
            </td>
            <td style="padding-left: 20px !important;">
                <div class="ml-1" style="display: inline;">
                    @if($survey[7]['answer'] == "YES")
                        <input type="checkbox" style="margin-bottom: -7px" checked/>
                    @else
                        <input type="checkbox" style="margin-bottom: -7px"/>
                    @endif
                    <span>YES</span>
                </div>
                <div class="ml-1" style="display: inline;">
                    @if($survey[7]['answer'] == "NO")
                        <input type="checkbox" style="margin-bottom: -7px" checked/>
                    @else
                        <input type="checkbox" style="margin-bottom: -7px"/>
                    @endif
                    <span>NO</span>
                </div>
                <div style="margin-top:3px">If YES, give details:</div>
                <input style="width: 95%" type="text" class="underline" value="{{ $survey[7]['details'] }}"/>
            </td>
        </tr>
        <tr class="border-dark-bottom">
            <td class="border-dark-right bg-gray-white">
                39. Have you acquired the status of an immigrant or permanent resident of another country?
            </td>
            <td style="padding-left: 20px !important" class="border-dark-bottom">
                <div class="ml-1" style="display: inline;">
                    @if($survey[8]['answer'] == "YES")
                        <input type="checkbox" style="margin-bottom: -7px" checked/>
                    @else
                        <input type="checkbox" style="margin-bottom: -7px"/>
                    @endif
                    <span>YES</span>
                </div>
                <div class="ml-1" style="display: inline;">
                    @if($survey[8]['answer'] == "NO")
                        <input type="checkbox" style="margin-bottom: -7px" checked/>
                    @else
                        <input type="checkbox" style="margin-bottom: -7px"/>
                    @endif
                    <span>NO</span>
                </div>
                <div style="margin-top:3px">If YES, give details (country):</div>
                <input style="width: 95%" type="text" class="underline" value="{{ $survey[8]['details'] }}"/>
            </td>
        </tr>
        <tr>
            <td class="border-dark-right bg-gray-white" style="width: 450px !important;">
                40. Pursuant to: (a) Indegenous People's Act (RA 8371); (b)Magna Carta for Disabled Persons (RA 7277);
                and (c) Solo Parents Welfare Act of 2000 (RA 8972), please answer the following items:
            </td>
            <td class="v-center text-center">

            </td>
        </tr>
        <tr>
            <td class="border-dark-right bg-gray-white" style="padding-left: 20px !important;">a. Are you a member of
                any indigenous
                group?
            </td>
            <td style="padding-left: 20px !important;">
                <div class="ml-1" style="display: inline;">
                    @if($survey[9]['answer'] == "YES")
                        <input type="checkbox" style="margin-bottom: -7px" checked/>
                    @else
                        <input type="checkbox" style="margin-bottom: -7px"/>
                    @endif
                    <span>YES</span>
                </div>
                <div class="ml-1" style="display: inline;">
                    @if($survey[9]['answer'] == "NO")
                        <input type="checkbox" style="margin-bottom: -7px" checked/>
                    @else
                        <input type="checkbox" style="margin-bottom: -7px"/>
                    @endif
                    <span>NO</span>
                </div>
                <div>
                    <div style="margin-top:3px;display: inline-block">If YES, please specify:</div>
                    <input class="underline" style="width: 40%;display: inline-block" type="text"
                           value="{{ $survey[9]['details'] }}"/>
                </div>
            </td>
        </tr>
        <tr>
            <td class="border-dark-right bg-gray-white" style="padding-left: 20px !important;">b. Are you a person with
                disability?
            </td>
            <td style="padding-left: 20px !important">
                <div class="ml-1" style="display: inline;">
                    @if($survey[10]['answer'] == "YES")
                        <input type="checkbox" style="margin-bottom: -7px" checked/>
                    @else
                        <input type="checkbox" style="margin-bottom: -7px"/>
                    @endif
                    <span>YES</span>
                </div>
                <div class="ml-1" style="display: inline;">
                    @if($survey[10]['answer'] == "NO")
                        <input type="checkbox" style="margin-bottom: -7px" checked/>
                    @else
                        <input type="checkbox" style="margin-bottom: -7px"/>
                    @endif
                    <span>NO</span>
                </div>
                <div>
                    <div style="margin-top:3px;display: inline-block">If YES, please specify:</div>
                    <input class="underline" style="width: 40%;display: inline-block" type="text"
                           value="{{ $survey[10]['details'] }}"/>
                </div>

            </td>
        </tr>
        <tr>
            <td class="border-dark-right bg-gray-white" style="padding-left: 20px !important;">c. Are you a solo parent?
            </td>
            <td style="padding-left: 20px !important;padding-bottom: 10px !important;">
                <div class="ml-1" style="display: inline;">
                    @if($survey[11]['answer'] == "YES")
                        <input type="checkbox" style="margin-bottom: -7px" checked/>
                    @else
                        <input type="checkbox" style="margin-bottom: -7px"/>
                    @endif
                    <span>YES</span>
                </div>
                <div class="ml-1" style="display: inline;">
                    @if($survey[11]['answer'] == "NO")
                        <input type="checkbox" style="margin-bottom: -7px" checked/>
                    @else
                        <input type="checkbox" style="margin-bottom: -7px"/>
                    @endif
                    <span>NO</span>
                </div>
                <div>
                    <div style="margin-top:3px;display: inline-block">If YES, please specify:</div>
                    <input class="underline" style="width: 40%;display: inline-block" type="text"
                           value="{{ strtoupper($survey[11]['details']) }}"/>
                </div>

            </td>
        </tr>
    </table>

    <div style="clear:both; position:relative;margin-bottom: 50px">
        <div style="position:absolute; left:0pt; width:80%">
            <table class="w-100 h-100 no-spacing border" border="0" style="font-size: 11.5px !important;">
                <tr class="border-dark-bottom bg-gray-white">
                    <td class="v-center" colspan="3">41. REFERENCES <small style="color:darkred;font-weight: bold">(Person
                            not related by
                            consanguinity or affinity to applicant/appointee)</small></td>
                </tr>
                <tr class="text-center bg-gray-white">
                    <td class="v-center border-dark-right" style="width: 40%">NAME</td>
                    <td class="v-center border-dark-right" style="width: 40%">ADDRESS</td>
                    <td class="v-center" style="width: 20%">TEL NO.</td>
                </tr>
                @foreach($reference as $key)
                    <tr class="text-center border-dark-top">
                        <td class="v-center border-dark-right">{{ strtoupper($key['name']) }}</td>
                        <td class="v-center border-dark-right">{{ strtoupper($key['address']) }}</td>
                        <td class="v-center">{{ strtoupper($key['contact_no']) }}</td>
                    </tr>
                @endforeach
                <tr class="border-dark-bottom border-dark-top bg-gray-white">
                    <td colspan="3" style="font-size: 12.5px !important;">42. I declare under oath that I have
                        personally accomplished this Personal Data
                        Sheet
                        which is true, correct and complete
                        statement pursuant to the provisions of pertinent laws, rules and regulations of the Republic of
                        the Philippines. I authorize the agency head/authorized
                        representative to verify/validate the contents stated herein.<br/>I agree that any
                        misrepresentation
                        made in this document and its attachments shall cause the filing of
                        administrative/criminal case/s against me.
                    </td>
                </tr>
            </table>

            <div style="clear:both; position:relative;">
                <div style="position:absolute; left:0pt; width:50%;height: 250px !important;">
                    <table class="w-100 h-100 no-spacing border" border="0"
                           style="font-size: 11.5px !important;margin-top:10px;margin-left: 10px;margin-bottom: 10px">
                        <tr class="border-dark-bottom bg-gray-white">
                            <td class="v-center" colspan="2">Government Issued
                                <small>
                                    (i.e. Passport, GSIS, SSS, PRC, Driver's Licenses, etc.)
                                </small>
                                <span style="font-size: 9px;float: right;margin-top: 3px;margin-right: 2px">PLEASE INDICATE ID Number and Date of Issuance</span>
                            </td>
                        </tr>
                        <tr class="border-dark-bottom">
                            <td class="v-center" style="width: 45%">Government Issued ID:</td>
                            <td class="v-center fw-bold" style="width: 55%">
                                @if($employee)
                                    {{ strtoupper($employee->issued_id) }}
                                @else
                                    N/A
                                @endif
                            </td>
                        </tr>
                        <tr class="border-dark-bottom">
                            <td class="v-center" style="width: 45%">ID/License/Passport No.:</td>
                            <td class="v-center fw-bold" style="width: 55%">
                                @if($employee)
                                    {{ $employee->issued_id_no }}
                                @else
                                    N/A
                                @endif
                            </td>
                        </tr>
                        <tr class="border-dark-bottom">
                            <td class="v-center" style="width: 45%">Date/Place of Issuance:</td>
                            <td class="v-center fw-bold" style="width: 55%">
                                @if($employee)
                                    {{ strtoupper($employee->issued_date) }}
                                @else
                                    N/A
                                @endif
                            </td>
                        </tr>
                    </table>
                </div>
                <div style="margin-left:55%;height: 250px !important;">
                    <div class="w-100 h-100 border-dark text-center"
                         style="font-size: 11.5px !important;margin-top:10px;margin-bottom: 10px">
                        <div class="border-dark-bottom" style="height: 60px"></div>
                        <div class="border-dark-bottom bg-gray-white">Signature (sign inside the box)</div>
                        <div class="border-dark-bottom" style="height: 15px"></div>
                        <div class="bg-gray-white">Date Accomplished</div>
                    </div>
                </div>
            </div>
        </div>
        <div style="margin-left:80%;">
            <div class="text-center" style="margin-top: 20px">
                <div class="border-dark" style="padding:10px 5px;width: 120px;margin-left: 10% !important;">
                    ID picture taken within<br/>
                    the last 6 months<br/>
                    3.5 cm. X 4.5 cm<br/>
                    (passport size)<br/><br/>

                    With full and handwritten<br/>
                    name tag and signature over<br/>
                    printed name<br/><br/>

                    Computer generated<br/>
                    or photocopied picture<br/>
                    is not acceptable<br/>
                </div>
                <div style="margin-top:5px">PHOTO</div>
                <div class="border-dark text-center" style="margin-top: 14%;margin-left: 4%;margin-right: 4%">
                    <div class="border-dark-bottom" style="height: 105px"></div>
                    <div class="bg-gray-white">Right Thumbmark</div>
                </div>
            </div>
        </div>
    </div>
    <div class="text-center border-dark-top" style="font-size: 12.5px;padding:5px;margin-top:5px;">
        <div style="margin-top: 5px">
            <div style="display: inline;">SUBSCRIBED AND SWORN to before me this</div>
            <div style="display: inline-block;width: 140px" class="underline"></div>
            , affiant exhibiting his/her validly issued government ID as indicated above.
        </div>
        <div class="border-dark text-center"
             style="margin-top: 15px;margin-left: 30%;margin-right: 30%;margin-bottom:3px">
            <div class="border-dark-bottom" style="height: 105px"></div>
            <div class="bg-gray-white">Person Administering Oath</div>
        </div>
    </div>
    <div class="border-dark-top" style="text-align: right">
        <em style="margin-right: 2px">CS FORM 212 (Revized 2017), Page 4 of 4</em>
    </div>
</div>

<div id="page_4" style="border:1px solid black;font-size: 11.5px">
    <table style="border-top:solid 1px black" class="survey">
        <tr>
            <td style="width: 67%;border-right: solid 1px black">34. Are you related by consanguinity or affinity to the
                appointing or recommending authority, or to the chief of bureau or office or to the person who has immediate supervision over you in the Office, <br/>
                Bureau or Department where you will be appointed,
            </td>
            <td style="width: 33%"></td>
        </tr>
        <tr>
            <td style="border-right: solid 1px black;">a. within the third degree?</td>
            <td>
                <div style="display:inline-block;margin-right: 10px;margin-left: 5px">
                    @if($survey[0]['answer'] == "YES")
                        <input type="checkbox" style="margin-top: -10px;margin-right: 3px" checked/>
                    @else
                        <input type="checkbox" style="margin-top: -10px;margin-right: 3px"/>
                    @endif
                    <span>YES</span>
                </div>
                <div style="display:inline-block;margin-left: 20px">
                    @if($survey[0]['answer'] == "NO")
                        <input type="checkbox" style="margin-top: -10px;margin-right: 3px" checked/>
                    @else
                        <input type="checkbox" style="margin-top: -10px;margin-right: 3px"/>
                    @endif
                    <span>NO</span>
                </div>
            </td>
        </tr>
        <tr>
            <td style="border-right: solid 1px black;">b. within the fourth degree (for Local Government Unit - Career
                Employees)?
            </td>
            <td>
                <div style="margin-left: 5px">
                    <div style="display:inline-block;margin-right: 10px">
                        @if($survey[1]['answer'] == "YES")
                            <input type="checkbox" style="margin-top: -10px;margin-right: 3px" checked/>
                        @else
                            <input type="checkbox" style="margin-top: -10px;margin-right: 3px"/>
                        @endif
                        <span>YES</span>
                    </div>
                    <div style="display:inline-block;margin-left: 20px">
                        @if($survey[1]['answer'] == "NO")
                            <input type="checkbox" style="margin-top: -10px;margin-right: 3px" checked/>
                        @else
                            <input type="checkbox" style="margin-top: -10px;margin-right: 3px"/>
                        @endif
                        <span>NO</span>
                    </div>
                    <div style="margin-left: 5px;margin-top:5px">
                        <div>If YES, give details:</div>
                        <div style="border-bottom: solid 1px black;width: 98%">
                            <div class="value">
                                @if($survey[1]['details'])
                                    {{ $survey[1]['details'] }}
                                @else
                                    N/A
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </td>
        </tr>
        <tr style="border-top: solid 1px black">
            <td style="border-right: solid 1px black;">35. a. Have you ever been found guilty of any administrative
                offense?
            </td>
            <td>
                <div style="margin-left: 5px;margin-top:5px">
                    <div style="display:inline-block;margin-right: 10px">
                        @if($survey[2]['answer'] == "YES")
                            <input type="checkbox" style="margin-top: -10px;margin-right: 3px" checked/>
                        @else
                            <input type="checkbox" style="margin-top: -10px;margin-right: 3px"/>
                        @endif
                        <span>YES</span>
                    </div>
                    <div style="display:inline-block;margin-left: 20px">
                        @if($survey[2]['answer'] == "NO")
                            <input type="checkbox" style="margin-top: -10px;margin-right: 3px" checked/>
                        @else
                            <input type="checkbox" style="margin-top: -10px;margin-right: 3px"/>
                        @endif
                        <span>NO</span>
                    </div>
                    <div style="margin-left: 5px;margin-top:5px">
                        <div>If YES, give details:</div>
                        <div style="border-bottom: solid 1px black;width: 98%">
                            @if($survey[2]['details'])
                                {{ $survey[2]['details'] }}
                            @else
                                N/A
                            @endif
                        </div>
                    </div>
                </div>
            </td>
        </tr>
        <tr>
            <td style="border-right: solid 1px black;">b. Have you been criminally charged before any court?</td>
            <td style="border-top: solid 1px black">
                <div style="margin-left: 5px;margin-top: 5px">
                    <div style="display:inline-block;margin-right: 10px">
                        @if($survey[3]['answer'] == "YES")
                            <input type="checkbox" style="margin-top: -10px;margin-right: 3px" checked/>
                        @else
                            <input type="checkbox" style="margin-top: -10px;margin-right: 3px"/>
                        @endif
                        <span>YES</span>
                    </div>
                    <div style="display:inline-block;margin-left: 20px">
                        @if($survey[3]['answer'] == "NO")
                            <input type="checkbox" style="margin-top: -10px;margin-right: 3px" checked/>
                        @else
                            <input type="checkbox" style="margin-top: -10px;margin-right: 3px"/>
                        @endif
                        <span>NO</span>
                    </div>
                    <div style="margin-left: 5px;margin-top:5px">
                        <div>If YES, give details:</div>
                        <table style="vertical-align: middle;">
                            <tr>
                                <td style="width: 40%;text-align: right">Date Filed:</td>
                                <td style="border-bottom: solid 1px black">
                                    <div class="value">
                                        @if($survey[3]['details'])
                                            {{ $survey[3]['details'] }}
                                        @else
                                            N/A
                                        @endif
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td style=";text-align: right">Status of Case/s:</td>
                                <td style="border-bottom: solid 1px black">
                                    <div class="value">
                                        @if($survey[3]['extra'])
                                            {{ $survey[3]['extra'] }}
                                        @else
                                            N/A
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
            </td>
        </tr>
        <tr style="border-top: solid 1px black">
            <td style="border-right: solid 1px black;">36. Have you ever been convicted of any crime or violation of any
                law, decree, ordinance or<br/>regulation by any court or tribunal?
            </td>
            <td>
                <div style="margin-left: 5px;margin-top: 5px">
                    <div style="display:inline-block;margin-right: 10px">
                        @if($survey[4]['answer'] == "YES")
                            <input type="checkbox" style="margin-top: -10px;margin-right: 3px" checked/>
                        @else
                            <input type="checkbox" style="margin-top: -10px;margin-right: 3px"/>
                        @endif
                        <span>YES</span>
                    </div>
                    <div style="display:inline-block;margin-left: 20px">
                        @if($survey[4]['answer'] == "NO")
                            <input type="checkbox" style="margin-top: -10px;margin-right: 3px" checked/>
                        @else
                            <input type="checkbox" style="margin-top: -10px;margin-right: 3px"/>
                        @endif
                        <span>NO</span>
                    </div>
                    <div style="margin-left: 5px;margin-top:5px">
                        <div>If YES, give details:</div>
                        <div style="border-bottom: solid 1px black;width: 98%">
                            <div class="value">
                                @if($survey[4]['details'])
                                    {{ $survey[4]['details'] }}
                                @else
                                    N/A
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </td>
        </tr>
        <tr style="border-top: solid 1px black">
            <td style="border-right: solid 1px black;">37. Have you ever been separated from the service in any of the
                following modes: resignation,<br/>retirement, dropped from the rolls, dismissal, termination, end of
                term,
                finished contract or phased out<br/>(abolition) in the public or private sector?
            </td>
            <td>
                <div style="margin-left: 5px;margin-top: 5px">
                    <div style="display:inline-block;margin-right: 10px">
                        @if($survey[5]['answer'] == "YES")
                            <input type="checkbox" style="margin-top: -10px;margin-right: 3px" checked/>
                        @else
                            <input type="checkbox" style="margin-top: -10px;margin-right: 3px"/>
                        @endif
                        <span>YES</span>
                    </div>
                    <div style="display:inline-block;margin-left: 20px">
                        @if($survey[5]['answer'] == "NO")
                            <input type="checkbox" style="margin-top: -10px;margin-right: 3px" checked/>
                        @else
                            <input type="checkbox" style="margin-top: -10px;margin-right: 3px"/>
                        @endif
                        <span>NO</span>
                    </div>
                    <div style="margin-left: 5px;margin-top:5px">
                        <div>If YES, give details:</div>
                        <div style="border-bottom: solid 1px black;width: 98%">
                            <div class="value">
                                @if($survey[5]['details'])
                                    {{ $survey[5]['details'] }}
                                @else
                                    N/A
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </td>
        </tr>
        <tr style="border-top: solid 1px black">
            <td style="border-right: solid 1px black;">38. a. Have you ever been a candidate in a national or local
                election held within the last year (except<br/>Barangay election)?
            </td>
            <td>
                <div style="margin-left: 5px;margin-top: 5px">
                    <div style="display:inline-block;margin-right: 10px">
                        @if($survey[6]['answer'] == "YES")
                            <input type="checkbox" style="margin-top: -10px;margin-right: 3px" checked/>
                        @else
                            <input type="checkbox" style="margin-top: -10px;margin-right: 3px"/>
                        @endif
                        <span>YES</span>
                    </div>
                    <div style="display:inline-block;margin-left: 20px">
                        @if($survey[6]['answer'] == "NO")
                            <input type="checkbox" style="margin-top: -10px;margin-right: 3px" checked/>
                        @else
                            <input type="checkbox" style="margin-top: -10px;margin-right: 3px"/>
                        @endif
                        <span>NO</span>
                    </div>
                    <div style="margin-left: 5px;margin-top:5px">
                        <div>If YES, give details:</div>
                        <div style="border-bottom: solid 1px black;width: 98%">
                            <div class="value">
                                @if($survey[6]['details'])
                                    {{ $survey[6]['details'] }}
                                @else
                                    N/A
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </td>
        </tr>
        <tr>
            <td style="border-right: solid 1px black;">b. Have you resigned from the government service during the three
                (3)-month period before the<br/>last election to promote/actively campaign for a national or local
                candidate?
            </td>
            <td>
                <div style="margin-left: 5px;margin-top: 5px">
                    <div style="display:inline-block;margin-right: 10px">
                        @if($survey[7]['answer'] == "YES")
                            <input type="checkbox" style="margin-top: -10px;margin-right: 3px" checked/>
                        @else
                            <input type="checkbox" style="margin-top: -10px;margin-right: 3px"/>
                        @endif
                        <span>YES</span>
                    </div>
                    <div style="display:inline-block;margin-left: 20px">
                        @if($survey[7]['answer'] == "NO")
                            <input type="checkbox" style="margin-top: -10px;margin-right: 3px" checked/>
                        @else
                            <input type="checkbox" style="margin-top: -10px;margin-right: 3px"/>
                        @endif
                        <span>NO</span>
                    </div>
                    <div style="margin-left: 5px;margin-top:5px">
                        <div>If YES, give details:</div>
                        <div style="border-bottom: solid 1px black;width: 98%">
                            <div class="value">
                                @if($survey[7]['details'])
                                    {{ $survey[7]['details'] }}
                                @else
                                    N/A
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </td>
        </tr>
        <tr style="border-top: solid 1px black">
            <td style="border-right: solid 1px black;">39. Have you acquired the status of an immigrant or permanent
                resident of another country?
            </td>
            <td>
                <div style="margin-left: 5px;margin-top: 5px">
                    <div style="display:inline-block;margin-right: 10px">
                        @if($survey[8]['answer'] == "YES")
                            <input type="checkbox" style="margin-top: -10px;margin-right: 3px" checked/>
                        @else
                            <input type="checkbox" style="margin-top: -10px;margin-right: 3px"/>
                        @endif
                        <span>YES</span>
                    </div>
                    <div style="display:inline-block;margin-left: 20px">
                        @if($survey[8]['answer'] == "NO")
                            <input type="checkbox" style="margin-top: -10px;margin-right: 3px" checked/>
                        @else
                            <input type="checkbox" style="margin-top: -10px;margin-right: 3px"/>
                        @endif
                        <span>NO</span>
                    </div>
                    <div style="margin-left: 5px;margin-top:5px">
                        <div>If YES, give details (country):</div>
                        <div style="border-bottom: solid 1px black;width: 98%">
                            <div class="value">
                                @if($survey[8]['details'])
                                    {{ $survey[8]['details'] }}
                                @else
                                    N/A
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </td>
        </tr>
        <tr style="border-top: solid 1px black">
            <td style="border-right: solid 1px black;">40. Pursuant to: (a) Indigenous People's Act (RA 8371); (b) Magna
                Carta for Disabled Persons (RA<br/>7277); and (c) Solo Parents Welfare Act of 2000 (RA 8972), please
                answer
                the following items:
            </td>
            <td></td>
        </tr>
        <tr>
            <td style="border-right: solid 1px black;">a. Are you a member of any indigenous group?</td>
            <td>
                <div style="margin-left: 5px;margin-top: 5px">
                    <div style="display:inline-block;margin-right: 10px">
                        @if($survey[9]['answer'] == "YES")
                            <input type="checkbox" style="margin-top: -10px;margin-right: 3px" checked/>
                        @else
                            <input type="checkbox" style="margin-top: -10px;margin-right: 3px"/>
                        @endif
                        <span>YES</span>
                    </div>
                    <div style="display:inline-block;margin-left: 20px">
                        @if($survey[9]['answer'] == "NO")
                            <input type="checkbox" style="margin-top: -10px;margin-right: 3px" checked/>
                        @else
                            <input type="checkbox" style="margin-top: -10px;margin-right: 3px"/>
                        @endif
                        <span>NO</span>
                    </div>
                </div>
                <table style="vertical-align: middle">
                    <tr>
                        <td style="width: 63%">If YES, please specify:</td>
                        <td style="border-bottom: solid 1px black">
                            <div class="value">
                                @if($survey[9]['details'])
                                    {{ $survey[9]['details'] }}
                                @else
                                    N/A
                                @endif
                            </div>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
        <tr>
            <td style="border-right: solid 1px black;">b. Are you a person with disability?</td>
            <td>
                <div style="margin-left: 5px;margin-top: 5px">
                    <div style="display:inline-block;margin-right: 10px">
                        @if($survey[10]['answer'] == "YES")
                            <input type="checkbox" style="margin-top: -10px;margin-right: 3px" checked/>
                        @else
                            <input type="checkbox" style="margin-top: -10px;margin-right: 3px"/>
                        @endif
                        <span>YES</span>
                    </div>
                    <div style="display:inline-block;margin-left: 20px">
                        @if($survey[10]['answer'] == "NO")
                            <input type="checkbox" style="margin-top: -10px;margin-right: 3px" checked/>
                        @else
                            <input type="checkbox" style="margin-top: -10px;margin-right: 3px"/>
                        @endif
                        <span>NO</span>
                    </div>
                </div>
                <table style="vertical-align: middle">
                    <tr>
                        <td style="width: 63%">If YES, please specify ID No:</td>
                        <td style="border-bottom: solid 1px black">
                            <div class="value">
                                @if($survey[10]['details'])
                                    {{ $survey[10]['details'] }}
                                @else
                                    N/A
                                @endif
                            </div>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
        <tr style="border-bottom: solid 1px black">
            <td style="border-right: solid 1px black;">c. Are you a solo parent?</td>
            <td>
                <div style="margin-left: 5px;margin-top: 5px">
                    <div style="display:inline-block;margin-right: 10px">
                        @if($survey[11]['answer'] == "YES")
                            <input type="checkbox" style="margin-top: -10px;margin-right: 3px" checked/>
                        @else
                            <input type="checkbox" style="margin-top: -10px;margin-right: 3px"/>
                        @endif
                        <span>YES</span>
                    </div>
                    <div style="display:inline-block;margin-left: 20px">
                        @if($survey[11]['answer'] == "NO")
                            <input type="checkbox" style="margin-top: -10px;margin-right: 3px" checked/>
                        @else
                            <input type="checkbox" style="margin-top: -10px;margin-right: 3px"/>
                        @endif
                        <span>NO</span>
                    </div>
                </div>
                <table style="vertical-align: middle">
                    <tr>
                        <td style="width: 63%">If YES, please specify ID No:</td>
                        <td style="border-bottom: solid 1px black">
                            <div class="value">
                                @if($survey[11]['details'])
                                    {{ $survey[11]['details'] }}
                                @else
                                    N/A
                                @endif
                            </div>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
    <div style="display: inline-block;width: 75%">
        <table>
            <tr class="gray">
                <td colspan="3" style="border-right: solid 1px black">
                    41. REFERENCES <span style="font-weight: bold">(Person not related by consanguinity or affinity to applicant /appointee)</span>
                </td>
            </tr>
            <tr style="text-align: center;border-bottom: solid 1px black;font-size: 9px !important;">
                <td style="border-top: solid 1px black;width: 45%;border-right: solid 1px black" class="gray">
                    NAME
                </td>
                <td style="border-top: solid 1px black;width: 35%;border-right: solid 1px black" class="gray">
                    OFFICE / RESIDENTIAL ADDRESS
                </td>
                <td style="border-top: solid 1px black;width: 20%;border-right: solid 1px black" class="gray">
                    CONTACT NO. AND/OR<br/>EMAIL
                </td>
            </tr>
            @foreach($reference as $key)
                <tr style="font-size: 9px !important;">
                    <td style="border-top: solid 1px black;border-right: solid 1px black;font-size: 10.33px"
                        class="center-value">{{ strtoupper($key['name']) }}</td>
                    <td style="border-top: solid 1px black;border-right: solid 1px black;font-size: 10.33px"
                        class="center-value">{{ strtoupper($key['address']) }}</td>
                    <td style="border-top: solid 1px black;border-right: solid 1px black;font-size: 10.33px"
                        class="center-value">{{ strtoupper($key['contact_no']) }}</td>
                </tr>
            @endforeach
            <tr style="border-top: solid 1px black">
                <td colspan="3" style="border-right: solid 1px black; font-size: 12.3px; font-family: 'Arial Narrow'">
                    <p style="margin: 0; text-align: justify; text-indent: -20px; padding-left: 25px;">
                        42. I declare under oath that I have personally accomplished this Personal Data Sheet which is a true,
                        correct and complete statement pursuant to the provisions of pertinent laws, rules and regulations of the
                        Republic of the Philippines. I authorize the agency head/authorized representative to verify/validate the
                        contents stated herein. I agree that any misrepresentation made in this document and its attachments shall
                        cause the filing of administrative/criminal case/s against me.
                    </p>
                </td>
            </tr>

        </table>
        <table id="government_id">
            <tr>
                <td style="width:55%;border-top: solid 1px black;vertical-align: top !important;">
                    <div style="text-align: left !important;margin-top:10px;margin-left: 5%;margin-right: 5%"
                         class="rectangle">
                        <table class="gray" style="font-size: 10px">
                            <tr>
                                <td colspan="2" style="height: unset !important;">Government Issued ID
                                    <span style="font-size: 7.5px">(i.e.Passport,GSIS,SSS,PRC,Driver's License, etc.)</span>
                                </td>
                            </tr>
                            <tr>
                                <td colspan="2" style="height: unset !important;">
                                    PLEASE INDICATE ID Number and Date of Issuance
                                </td>
                            </tr>
                        </table>
                        <table>
                            <tr style="border-top: solid 1px black">
                                <td style="width: 55%">Government Issued ID:</td>
                                <td style="width: 45%">
                                    <div class="value" style="font-size: 10px">
                                        @if($employee)
                                            {{ strtoupper($employee->issued_id) }}
                                        @else
                                            N/A
                                        @endif
                                    </div>
                                </td>
                            </tr>
                            <tr style="border-top: solid 1px black">
                                <td>ID/License/Passport No.</td>
                                <td>
                                    <div class="value"  style="font-size: 10px">
                                        @if($employee)
                                            {{ $employee->issued_id_no }}
                                        @else
                                            N/A
                                        @endif
                                    </div>
                                </td>
                            </tr>
                            <tr style="border-top: solid 1px black">
                                <td>Date/Place of Issuance:</td>
                                <td>
                                    <div class="value"  style="font-size: 10px">
                                        @if(isset($employee->issued_date))
                                            {{ strtoupper($employee->issued_date) }}
                                        @else
                                            N/A
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        </table>
                    </div>
                </td>
                <td style="width:45%;border-top: solid 1px black;vertical-align: top !important;">
                    <div style="text-align: left !important;margin-top:10px" class="rectangle">
                        <div style="padding:2px;background: white;color: white">
                            <div>N/A</div>
                            <div>N/A</div>
                            <div>N/A</div>
                            <div>N/A</div>
                        </div>
                        <div class="gray"
                             style="text-align: center;border-top:solid 1px black;border-bottom: solid 1px black">
                            Signature
                            (Sign inside the box)
                        </div>
                        <div style="text-align: center;font-weight: bold">{{ $pds_date }}</div>
                        <div class="gray"
                             style="text-align: center;border-top:solid 1px black;border-bottom: solid 1px black">Date
                            Accomplished
                        </div>
                    </div>
                </td>
            </tr>
        </table>
    </div>
    <div style="display: inline-block;float:right;width: 24%">
        <div class="rectangle photo" style="margin-top: 20px">
            <br/><br/><br/>
            Passport-sized unfiltered digital<br />
            picture taken within<br />
            the last 6 months<br />4.5 cm. X 3.5 cm
            <br/><br/><br/>
        </div>
        <div style="text-align: center;margin-top: 5px;color:gray">PHOTO</div>

        <div style="margin-top:5px;margin-right: 5%;" class="rectangle">
            <div style="padding:2px;background: white;color: white">
                <div>N/A</div>
                <div>N/A</div>
                <div>N/A</div>
                <div>N/A</div>
                <div>N/A</div>
                <div>N/A</div>
            </div>
            <div style="color: white">N/A</div>
            <div style="color: white">N/A</div>
            <div class="gray"
                 style="text-align: center;border-top:solid 1px black;border-bottom: solid 1px black">
                Right Thumbmark
            </div>
        </div>
    </div>
    <div
        style="margin-top: 10px;padding-top:20px;padding-bottom:10px;border-top: solid 1px black;text-align: center;font-size: 12px">

        <span style="margin-right: 10px;">SUBSCRIBED AND SWORN to before me this</span>
        <span style="border-bottom: solid 1px black; display: inline-block; text-align: center; padding-right: 30px; padding-left: 30px; width: fit-content;">{{ $sworn_date }}</span>
        <span style="margin-left: 5px;">, affiant exhibiting his/her validly issued government ID as indicated above</span>


        <div style="text-align: left !important;margin-top:10px;margin-left: 26%;margin-right: 20%" class="rectangle">
            <div style="padding:2px;background: white;color: white">
                <div>N/A</div>
            </div>
            <div style="text-align:center;font-weight: bold;font-size:10.4px">AGUSTIN D. AGOS, JR.,MD, FPSGS, FPCS, MA Psy, DODT, PhD-OD, RODC, APRM™</div>
            <div style="color: white">N/A</div>
            <div style="color:white"></div>
            <div class="gray"
                 style="text-align: center;border-top:solid 1px black;border-bottom: solid 1px black">
                Person Administering Oath
            </div>
        </div>
    </div>
</div>
<div style="text-align: right;font-size: 9.33px;font-style: italic">CS FORM 212 (Revised 2025), Page 4 of 4</div>

@php
    if (!function_exists('adjustFontSize')) {
        function adjustFontSize($text, $baseSize = 13) {
            $length = strlen($text);
            if ($length > 60) return 'font-size: 7.5px;';
            if ($length > 45) return 'font-size: 8.5px;';
            if ($length > 35) return 'font-size: 9.5px;';
            if ($length > 20) return 'font-size: 10.5px;';
            if ($length > 15) return 'font-size: 11.5px;';
            return "font-size: {$baseSize}px;";
        }
    }

  if (!function_exists('schoolAdjustFontSize')) {
        function schoolAdjustFontSize($text, $baseSize = 10) {
            $length = strlen($text);
            if ($length > 45) return 'font-size: 8px;';
            if ($length > 35) return 'font-size: 8.5px;';
            if ($length > 20) return 'font-size: 9px;';
            if ($length > 13) return 'font-size: 9.5px;';
            return "font-size: {$baseSize}px;";
        }
    }

if (!function_exists('honorsAdjustFontSize')) {
        function honorsAdjustFontSize($text, $baseSize = 13) {
            $length = strlen($text);
            if ($length > 15) return 'font-size: 7px;';
            if ($length > 10) return 'font-size: 8px;';
            return "font-size: {$baseSize}px;";
        }
    }
@endphp
<div id="page_1" style="border:1px solid black">
    <div>
        <div style="font-weight: bold;font-size:14.66px;font-family: 'Calibri';padding-left:2.5px">CS Form No. 212</div>
        <div style="font-size: 12px;padding-left:2.5px">Revised 2026</div>
        <div
            style="text-align: center;font-weight: bold;font-size: 29.33px;font-family:'Arial Black',sans-serif !important;">
            PERSONAL DATA SHEET
        </div>
        <div style="margin-top:10px;font-weight: bold;font-size: 10px;font-family: 'Arial'">WARNING: Any
            misrepresentation made in the
            Personal
            Data Sheet and the Work Experience Sheet shall cause the filling of administrative/criminal case/s
            against the person concerned.
        </div>
        <div style="font-size:10px;font-weight: bold;font-family: 'Arial';margin-top:10px">READ THE ATTACHED GUIDE TO
            FILLING OUT THE
            PERSONAL DATA SHEET
            (PDS)
            BEFORE ACCOMPLISHING THE PDS FORM.
        </div>
        <table>
            <tr>
                <td style="padding: 0px !important;height: unset !important;border-bottom: solid 1px black;border-right: solid 1px black;font-size: 9px;">
                    <span>Print legibly if accomplished through own handwriting. Tick appropriate boxes</span>
                    <input style="display: inline;margin-top: -20px;font-size: 10px" type="checkbox"/>
                    <span>) and use separateI sheet if necessary. Indicate N/A if not applicable.</span>
                    <span style="font-weight: bold;">DO NOT ABBREVIATE.</span>
                </td>
            </tr>
        </table>
    </div>
    <table>
        <tr style="background: gray">
            <td colspan="3"
                class="title">I.
                PERSONAL
                INFORMATION
            </td>
        </tr>
        <tr>
            <td style="border-top:solid 1px black" class="gray">2. SURNAME</td>
            <td style="border-bottom: solid 1px black;border-left: solid 1px black;" colspan="2">
                <div class="value">{{ strtoupper($user->lname) }}</div>
            </td>
        </tr>
        <tr>
            <td style="width: 20%;margin-left: 10px" class="gray">
                <div style="margin-left: 10px;">FIRSTNAME</div>
            </td>
            <td style="border-bottom: solid 1px black;border-left: solid 1px black;">
                <div class="value">{{ strtoupper($user->fname) }}</div>
            </td>
            <td style="width: 25%;border-bottom: solid 1px black;border-left: solid 1px black;font-size: 9.33px"
                class="gray">
                <div style="font-size:8px">NAME EXTENSION (JR.,SR)</div>
                <div class="value">
                    @if($user->suffix)
                        {{ strtoupper($user->suffix) }}
                    @else
                        N/A
                    @endif
                </div>
            </td>
        </tr>
        <tr>
            <td class="gray">
                <div style="margin-left: 10px;">MIDDLENAME</div>
            </td>
            <td style="border-left: solid 1px black;" colspan="2">
                @if(trim($user->mname))
                    <div class="value">{{ strtoupper($user->mname) }}</div>
                @else
                    <div class="value">N/A</div>
                @endif
            </td>
        </tr>
    </table>
    <table style="border-top:solid 1px black">
        <tr>
            <td style="width: 20%;height: unset !important;" class="gray">3. DATE OF BIRTH<br/><span
                    style="margin-left: 10px;">(dd/mm/yyyy)</span></td>
            <td style="height: unset !important;width: 25%;border-left: solid 1px black;border-right:solid 1px black;border-bottom: solid 1px black"
                rowspan="2">
                <div class="value">
                    @if($employee->birthdate)
                        {{ date('d/m/Y', strtotime($employee->birthdate)) }}
                    @else
                        N/A
                    @endif
                </div>
            </td>
            <td style="height: unset !important;width: 23%;border-right: solid 1px black" class="gray">16. CITIZENSHIP
            </td>
            <td style="width: 32%;height: unset !important;">
                <div style="display:inline;margin-left: 15px">
                    @if($employee)
                        @if($employee->citizenship == "Filipino")
                            <input type="checkbox" style="margin-top: -10px;margin-right: 3px" checked/>
                        @else
                            <input type="checkbox" style="margin-top: -10px;margin-right: 3px"/>
                        @endif
                    @else
                        <input type="checkbox" style="margin-top: -10px;margin-right: 3px"/>
                    @endif
                    <span>Filipino</span>
                </div>
                <div style="display:inline;margin-left: 5px">
                    @if($employee)
                        @if($employee->citizenship == "Dual Citizenship")
                            <input type="checkbox" style="margin-top: -10px;margin-right: 3px" checked/>
                        @else
                            <input type="checkbox" style="margin-top: -10px;margin-right: 3px"/>
                        @endif
                    @else
                        <input type="checkbox" style="margin-top: -10px;margin-right: 3px"/>
                    @endif
                    <span>Dual Citizenship</span>
                </div>
            </td>
        </tr>
        <tr>
            <td style="border-bottom:solid 1px black;height: unset !important;" class="gray"></td>
            <td style="border-right: solid 1px black;height: unset !important;" class="gray"></td>
            <td style="height: unset !important;">
                <div style="margin-left:50px">
                    <div style="display:inline">
                        @if($employee)
                            @if($employee->citizenship_type == "by birth")
                                <input type="checkbox" style="margin-top: -10px;margin-right: 3px" checked/>
                            @else
                                <input type="checkbox" style="margin-top: -10px;margin-right: 3px"/>
                            @endif
                        @else
                            <input type="checkbox" style="margin-top: -10px;margin-right: 3px"/>
                        @endif
                        <span>by birth</span>
                    </div>
                    <div style="display:inline;margin-left: 10px">
                        @if($employee)
                            @if($employee->citizenship_type == "by naturalization")
                                <input type="checkbox" style="margin-top: -10px;margin-right: 3px" checked/>
                            @else
                                <input type="checkbox" style="margin-top: -10px;margin-right: 3px"/>
                            @endif
                        @else
                            <input type="checkbox" style="margin-top: -10px;margin-right: 3px"/>
                        @endif
                        <span>by naturalization</span>
                    </div>
                </div>
            </td>
        </tr>
        <tr>
            <td style="border-bottom: solid 1px black;border-right: solid 1px black;" class="gray">4. PLACE OF BIRTH
            </td>
            <td style="border-bottom: solid 1px black;border-right: solid 1px black;">
                <div class="value">
                    @if($employee->birthplace)
                        {{ strtoupper($employee->birthplace) }}
                    @else
                        N/A
                    @endif
                </div>
            </td>
            <td style="text-align: center;border-right: solid 1px black" class="gray">
                If holder of dual citizenship
            </td>
            <td style="text-align: center;border-bottom: solid 1px black">
                Pls. indicate country:
            </td>
        </tr>
        <tr>
            <td style="border-right: solid 1px black" class="gray">5. SEX AT BIRTH</td>
            <td style="border-right: solid 1px black;">
                <div style="margin-left:10px;margin-top: 3px">
                    <div style="display:inline">
                        @if($employee)
                            @if($employee->sex == "MALE")
                                <input type="checkbox" style="margin-top: -10px;margin-right: 3px" checked/>
                            @else
                                <input type="checkbox" style="margin-top: -10px;margin-right: 3px"/>
                            @endif
                        @else
                            <input type="checkbox" style="margin-top: -10px;margin-right: 3px"/>
                        @endif
                        <span>Male</span>
                    </div>
                    <div style="display:inline;margin-left: 65px">
                        @if($employee)
                            @if($employee->sex == "FEMALE")
                                <input type="checkbox" style="margin-top: -10px;margin-right: 3px" checked/>
                            @else
                                <input type="checkbox" style="margin-top: -10px;margin-right: 3px"/>
                            @endif
                        @else
                            <input type="checkbox" style="margin-top: -10px;margin-right: 3px"/>
                        @endif
                        <span>Female</span>
                    </div>
                </div>
            </td>
            <td style="border-right: solid 1px black;vertical-align: top !important;text-align: center" class="gray">
                please indicate the details.
            </td>
            <td style="text-align: center;font-weight: bold">
                <div class="value">
                    @if($employee->citizenship_country)
                        {{ strtoupper($employee->citizenship_country) }}
                    @else
                        N/A
                    @endif
                </div>
            </td>
        </tr>
    </table>
    <table style="border-top:solid 1px black">
        <tr>
            <td style="border-right: solid 1px black;width: 20%;vertical-align: top !important;" rowspan="2"
                class="gray">
                <div style="margin-top: 5px">6. CIVIL STATUS</div>
            </td>
            <td style="border-right: solid 1px black;width:25%" rowspan="2">
                <div style="margin-left:10px">
                    <div style="display:inline">
                        @if($employee)
                            @if($employee->civil_status == "SINGLE")
                                <input type="checkbox" style="margin-top: -10px;margin-right: 3px" checked/>
                            @else
                                <input type="checkbox" style="margin-top: -10px;margin-right: 3px"/>
                            @endif
                        @else
                            <input type="checkbox" style="margin-top: -10px;margin-right: 3px"/>
                        @endif
                        <span>Single</span>
                    </div>
                    <div style="display:inline;margin-left: 60px">
                        @if($employee)
                            @if($employee->civil_status == "MARRIED")
                                <input type="checkbox" style="margin-top: -10px;margin-right: 3px" checked/>
                            @else
                                <input type="checkbox" style="margin-top: -10px;margin-right: 3px"/>
                            @endif
                        @else
                            <input type="checkbox" style="margin-top: -10px;margin-right: 3px"/>
                        @endif
                        <span>Married</span>
                    </div>
                </div>
                <div style="margin-left:10px;margin-top: 3px">
                    <div style="display:inline">
                        @if($employee)
                            @if($employee->civil_status == "WIDOWED")
                                <input type="checkbox" style="margin-top: -10px;margin-right: 3px" checked/>
                            @else
                                <input type="checkbox" style="margin-top: -10px;margin-right: 3px"/>
                            @endif
                        @else
                            <input type="checkbox" style="margin-top: -10px;margin-right: 3px"/>
                        @endif
                        <span style="position: relative;margin-top: -10px">Widowed</span>
                    </div>
                    <div style="display:inline;margin-left: 47px">
                        @if($employee)
                            @if($employee->civil_status == "SEPARATED")
                                <input type="checkbox" style="margin-top: -10px;margin-right: 3px" checked/>
                            @else
                                <input type="checkbox" style="margin-top: -10px;margin-right: 3px"/>
                            @endif
                        @else
                            <input type="checkbox" style="margin-top: -10px;margin-right: 3px"/>
                        @endif
                        <span>Separated</span>
                    </div>
                </div>
                <div style="margin-left:10px;margin-top: 3px">
                    <div style="display:inline">
                        @if($employee)
                            @if($employee->civil_status == "OTHERS")
                                <input type="checkbox" style="margin-top: -10px;margin-right: 3px" checked/>
                            @else
                                <input type="checkbox" style="margin-top: -10px;margin-right: 3px"/>
                            @endif
                        @else
                            <input type="checkbox" style="margin-top: -10px;margin-right: 3px"/>
                        @endif
                        <span>Other/s:</span>
                    </div>
                </div>
            </td>
            <td style="border-right: solid 1px black;width: 17%;vertical-align: top !important;" rowspan="2"
                class="gray">
                <div style="margin-top: 5px;font-size: 9.33px">17. RESIDENTIAL ADDRESS</div>
            </td>
            <td style="padding-left: 0px !important;padding-right: 0px !important;width: 19%;border-bottom: solid 1px black;text-align: center">
                <div class="center-value">
                    @if($residential)
                        @if($residential->house_no)
                            {{ strtoupper($residential->house_no) }}
                        @else
                            N/A
                        @endif
                    @else
                        N/A
                    @endif
                </div>
                <div style="border-top:solid 1px  gray">House/Block/Lot No.</div>
            </td>
            <td style="padding-left: 0px !important;padding-right: 0px !important;width: 19%;border-bottom: solid 1px black;text-align: center">
                <div class="center-value">
                    @if($residential)
                        @if($residential->street)
                            {{ strtoupper($residential->street) }}
                        @else
                            N/A
                        @endif
                    @else
                        N/A
                    @endif
                </div>
                <div style="border-top:solid 1px  gray">Street</div>
            </td>
        </tr>
        <tr style="text-align: center">
            <td style="padding-left: 0px !important;padding-right: 0px !important;width: 19%;border-bottom:solid 1px black;">
                <div class="center-value">
                    @if($residential)
                        @if($residential->subdivision_village)
                            {{ strtoupper($residential->subdivision_village) }}
                        @else
                            N/A
                        @endif
                    @else
                        N/A
                    @endif
                </div>
                <div style="border-top:solid 1px gray">Subdivision/Village</div>
            </td>
            <td style="padding-left: 0px !important;padding-right: 0px !important;width: 19%;border-bottom:solid 1px black;">
                <div class="center-value">
                    @if($residential)
                        @if($residential->barangay)
                            {{ strtoupper($residential->barangay) }}
                        @else
                            N/A
                        @endif
                    @else
                        N/A
                    @endif
                </div>
                <div style="border-top:solid 1px gray">Barangay</div>
            </td>
        </tr>
        <tr>
            <td style="border-top:solid 1px black;border-right: solid 1px black;" class="gray">7. HEIGHT (m)</td>
            <td style="border-top:solid 1px black;border-right:solid 1px black">
                <div class="value">
                    @if($employee->height)
                        {{ $employee->height }}
                    @else
                        N/A
                    @endif
                </div>
            </td>
            <td style="border-right: solid 1px black" class="gray"></td>
            <td style="padding-left: 0px !important;padding-right: 0px !important;width: 19%;border-bottom:solid 1px black;text-align: center">
                <div class="center-value">
                    @if($residential)
                        @if($residential->city)
                            {{ strtoupper($residential->city) }}
                        @else
                            N/A
                        @endif
                    @else
                        N/A
                    @endif
                </div>
                <div style="border-top:solid 1px gray">City/Municipality</div>
            </td>
            <td style="padding-left: 0px !important;padding-right: 0px !important;width: 19%;border-bottom:solid 1px black;text-align: center">
                <div class="center-value">
                    @if($residential)
                        @if($residential->province)
                            {{ strtoupper($residential->province) }}
                        @else
                            N/A
                        @endif
                    @else
                        N/A
                    @endif
                </div>
                <div style="border-top:solid 1px gray">Province</div>
            </td>
        </tr>
        <tr>
            <td style="border-top:solid 1px black;border-right: solid 1px black;" class="gray">8. WEIGHT (kg)</td>
            <td style="border-top:solid 1px black;border-right:solid 1px black">
                <div class="value">
                    @if($employee->weight)
                        {{ $employee->weight }}
                    @else
                        N/A
                    @endif
                </div>
            </td>
            <td style="border-right: solid 1px black;text-align: center" class="gray">ZIP CODE</td>
            <td colspan="2">
                <div class="value">
                    @if($residential)
                        @if($residential->zip_code)
                            {{ strtoupper($residential->zip_code) }}
                        @else
                            N/A
                        @endif
                    @else
                        N/A
                    @endif
                </div>
            </td>
        </tr>

        <tr style="border-top: solid 1px black;">
            <td style="border-right: solid 1px black;width: 20%;vertical-align: top !important;" class="gray">
                <div style="margin-top: 5px">9. BLOOD TYPE</div>
            </td>
            <td style="border-right: solid 1px black;width:25%">
                <div class="value">
                    @if($employee->blood_type)
                        {{ $employee->blood_type }}
                    @else
                        N/A
                    @endif
                </div>
            </td>
            <td style="border-right: solid 1px black;width: 17%;vertical-align: top !important;" class="gray">
                <div style="margin-top: 5px;font-size: 9.33px">18. PERMANENT ADDRESS</div>
            </td>
            <td style="padding-left: 0px !important;padding-right: 0px !important;width: 19%;border-bottom: solid 1px black;text-align: center">
                <div class="center-value">
                    @if($permanent)
                        @if($permanent->house_no)
                            {{ strtoupper($permanent->house_no) }}
                        @else
                            N/A
                        @endif
                    @else
                        N/A
                    @endif
                </div>
                <div style="border-top:solid 1px  gray">House/Block/Lot No.</div>
            </td>
            <td style="padding-left: 0px !important;padding-right: 0px !important;width: 19%;border-bottom: solid 1px black;text-align: center">
                <div class="center-value">
                    @if($permanent)
                        @if($permanent->street)
                            {{ strtoupper($permanent->street) }}
                        @else
                            N/A
                        @endif
                    @else
                        N/A
                    @endif
                </div>
                <div style="border-top:solid 1px  gray">Street</div>
            </td>
        </tr>
        <tr>

            <td style="border-top: solid 1px black;border-right: solid 1px black" class="gray">10. UMID ID NO.</td>
            <td style="border-top: solid 1px black;border-right: solid 1px black;">
                <div class="value">
                    @if($employee->umid_id_no)
                        {{ $employee->umid_id_no }}
                    @else
                        N/A
                    @endif
                </div>
            </td>
            <td style="border-right: solid 1px black" class="gray"></td>
            <td style="padding-left: 0px !important;padding-right: 0px !important;width: 19%;border-bottom: solid 1px black;text-align: center">
                <div class="center-value">
                    @if($permanent)
                        @if($permanent->subdivision_village)
                            {{ strtoupper($permanent->subdivision_village) }}
                        @else
                            N/A
                        @endif
                    @else
                        N/A
                    @endif
                </div>
                <div style="border-top:solid 1px  gray">Subdivision/Village</div>
            </td>
            <td style="padding-left: 0px !important;padding-right: 0px !important;width: 19%;border-bottom: solid 1px black;text-align: center">
                <div class="center-value">
                    @if($permanent)
                        @if($permanent->barangay)
                            {{ strtoupper($permanent->barangay) }}
                        @else
                            N/A
                        @endif
                    @else
                        N/A
                    @endif
                </div>
                <div style="border-top:solid 1px  gray">Barangay</div>
            </td>
        </tr>
        <tr>
            <td style="border-top:solid 1px black;border-right: solid 1px black;" class="gray">11. PAG-IBIG ID NO.</td>
            <td style="border-top:solid 1px black;border-right:solid 1px black">
                <div class="value">
                    @if($employee->pagibig_id_no)
                        {{ $employee->pagibig_id_no }}
                    @else
                        N/A
                    @endif
                </div>
            </td>
            <td style="border-right: solid 1px black" class="gray"></td>
            <td style="padding-left: 0px !important;padding-right: 0px !important;width: 19%;border-bottom: solid 1px black;text-align: center">
                <div class="center-value">
                    @if($permanent)
                        @if($permanent->city)
                            {{ strtoupper($permanent->city) }}
                        @else
                            N/A
                        @endif
                    @else
                        N/A
                    @endif
                </div>
                <div style="border-top:solid 1px  gray">City/Municipality</div>
            </td>
            <td style="padding-left: 0px !important;padding-right: 0px !important;width: 19%;border-bottom: solid 1px black;text-align: center">
                <div class="center-value">
                    @if($permanent)
                        @if($permanent->province)
                            {{ strtoupper($permanent->province) }}
                        @else
                            N/A
                        @endif
                    @else
                        N/A
                    @endif
                </div>
                <div style="border-top:solid 1px  gray">Province</div>
            </td>
        </tr>
        <tr>
            <td style="border-top:solid 1px black;border-right: solid 1px black;" class="gray">12. PHILHEALTH NO.</td>
            <td style="border-top:solid 1px black;border-right:solid 1px black">
                <div class="value">
                    @if($employee->philhealth_no)
                        {{ $employee->philhealth_no }}
                    @else
                        N/A
                    @endif
                </div>
            </td>
            <td style="border-right: solid 1px black;text-align: center" class="gray">ZIP CODE</td>
            <td colspan="2" style="border-top:solid 1px black;">
                <div class="value">
                    @if($permanent)
                        @if($permanent->zip_code)
                            {{ strtoupper($permanent->zip_code) }}
                        @else
                            N/A
                        @endif
                    @else
                        N/A
                    @endif
                </div>
            </td>
        </tr>
        <tr style="border-top:solid 1px black;">
            <td style="border-right: solid 1px black;" class="gray">13. PhilSys Number (PSN):</td>
            <td style="border-right:solid 1px black">
                <div class="value">
                    @if($employee->philsys_no)
                        {{ $employee->philsys_no }}
                    @else
                        N/A
                    @endif
                </div>
            </td>
            <td style="border-right: solid 1px black" class="gray">19. TELEPHONE NO.</td>
            <td colspan="2">
                <div class="value">
                    @if($employee->telephone_no)
                        {{ $employee->telephone_no }}
                    @else
                        N/A
                    @endif
                </div>
            </td>
        </tr>
        <tr style="border-top:solid 1px black;">
            <td style="border-right: solid 1px black;" class="gray">14. TIN NO.</td>
            <td style="border-right:solid 1px black">
                <div class="value">
                    @if($employee->tin_no)
                        {{ $employee->tin_no }}
                    @else
                        N/A
                    @endif
                </div>
            </td>
            <td style="border-right: solid 1px black" class="gray">20. MOBILE NO.</td>
            <td colspan="2">
                <div class="value">
                    @if($employee->mobile_no)
                        {{ $employee->mobile_no }}
                    @else
                        N/A
                    @endif
                </div>
            </td>
        </tr>
        <tr style="border-top:solid 1px black;">
            <td style="border-right: solid 1px black;" class="gray">15. AGENCY EMPLOYEE NO.</td>
            <td style="border-right:solid 1px black">
                <div class="value">
                    @if($employee->employee_no)
                        {{ $employee->employee_no }}
                    @else
                        N/A
                    @endif
                </div>
            </td>
            <td style="font-size: 10px;border-right: solid 1px black;font-size:9px" class="gray">21. E-MAIL ADDRESS (if
                any)
            </td>
            <td colspan="2">
                <div class="value">
                    @if($employee->email_address)
                        {{ strtoupper($employee->email_address) }}
                    @else
                        N/A
                    @endif
                </div>
            </td>
        </tr>
    </table>
    <table>
        <tr style="background: gray">
            <td colspan="5"
                class="title">II.
                FAMILY BACKGROUND
            </td>
        </tr>
        <tr>
            <td style="border-right: solid 1px black" class="gray">22. SPOUSE'S SURNAME</td>
            <td style="border-right: solid 1px black" colspan="2">
                <div class="value">
                    @if($family)
                        @if($family->spouse_lname)
                            {{ strtoupper($family->spouse_lname) }}
                        @else
                            N/A
                        @endif
                    @else
                        N/A
                    @endif
                </div>
            </td>
            <td style="font-size: 10.5px;border-right: solid 1px black" class="gray">
                <div style="margin-top: 2px;font-size: 8px;text-align: center">23. NAME of CHILDREN (Write full name and
                    list all)
                </div>
            </td>
            <td style="text-align: center" class="gray">DATE OF BIRTH<br/>(dd/mm/yyy)</td>
        </tr>
        <tr style="border-top: solid 1px black">
            <td style="width: 20%;border-right: solid 1px black" class="gray">
                <div style="margin-left: 15px">FIRST NAME</div>
            </td>
            <td style="width: 25%;border-right: solid 1px black;">
                <div class="value">
                    @if($family)
                        @if($family->spouse_fname)
                            {{ strtoupper($family->spouse_fname) }}
                        @else
                            N/A
                        @endif
                    @else
                        N/A
                    @endif
                </div>
            </td>
            <td style="width: 17%;border-right: solid 1px black;font-size: 9.33px" class="gray">
                <div style="font-size:8px">NAME EXTENSION (JR.,SR)</div>
                <div class="value">
                    @if($family)
                        @if($family->spouse_ext_name)
                            {{ strtoupper($family->spouse_ext_name) }}
                        @else
                            N/A
                        @endif
                    @else
                        N/A
                    @endif
                </div>
            </td>
            <td style="width: 25%;border-right: solid 1px black; {{ adjustFontSize($childrens[0]['name'] ?? '', 12) }}"
                class="center-value">
                {{ strtoupper($childrens[0]['name']) }}
            </td>
            <td style="width: 13%">
                <div class="center-value">
                    {{ $childrens[0]['birthdate'] }}
                </div>
            </td>
        </tr>
        <tr style="border-top: solid 1px black">
            <td style="width: 20%;border-right: solid 1px black" class="gray">
                <div style="margin-left: 15px">MIDDLE NAME</div>
            </td>
            <td colspan="2" style="border-right: solid 1px black;">
                <div class="value">
                    @if($family)
                        @if($family->spouse_mname)
                            {{ strtoupper($family->spouse_mname) }}
                        @else
                            N/A
                        @endif
                    @else
                        N/A
                    @endif
                </div>
            </td>
            <td style="width: 25%;border-right: solid 1px black; {{ adjustFontSize($childrens[1]['name'] ?? '', 12) }}"
                class="center-value">
                {{ strtoupper($childrens[1]['name']) }}
            </td>
            <td style="width: 13%" class="center-value">
                {{ $childrens[1]['birthdate'] }}
            </td>
        </tr>
        <tr style="border-top: solid 1px black">
            <td style="width: 20%;border-right: solid 1px black" class="gray">
                <div style="margin-left: 15px">OCCUPATION</div>
            </td>
            <td colspan="2" style="border-right: solid 1px black;">
                <div class="value">
                    @if($family)
                        @if($family->spouse_occupation)
                            {{ strtoupper($family->spouse_occupation) }}
                        @else
                            N/A
                        @endif
                    @else
                        N/A
                    @endif
                </div>
            </td>
            <td style="width: 25%;border-right: solid 1px black; {{ adjustFontSize($childrens[2]['name'] ?? '', 12) }}"
                class="center-value">
                {{ strtoupper($childrens[2]['name']) }}
            </td>
            <td style="width: 13%" class="center-value">
                {{ $childrens[2]['birthdate'] }}
            </td>
        </tr>
        <tr style="border-top: solid 1px black">
            <td style="width: 20%;border-right: solid 1px black" class="gray">
                <div style="margin-left: 15px">EMPLOYER/BUSINESS NAME</div>
            </td>
            <td colspan="2" style="border-right: solid 1px black;">
                <div class="value">
                    @if($family)
                        @if($family->spouse_employer)
                            {{ strtoupper($family->spouse_employer) }}
                        @else
                            N/A
                        @endif
                    @else
                        N/A
                    @endif
                </div>
            </td>
            <td style="width: 25%;border-right: solid 1px black; {{ adjustFontSize($childrens[3]['name'] ?? '', 12) }}"
                class="center-value">
                {{ strtoupper($childrens[3]['name']) }}
            </td>
            <td style="width: 13%" class="center-value">
                {{ $childrens[3]['birthdate'] }}
            </td>
        </tr>
        <tr style="border-top: solid 1px black">
            <td style="width: 20%;border-right: solid 1px black" class="gray">
                <div style="margin-left: 15px">BUSINESS ADDRESS</div>
            </td>
            <td colspan="2" class="value" style="border-right: solid 1px black;">
                <div class="value" style="{{ adjustFontSize(strtoupper($family->spouse_business_address) ?? '', 12) }}">
                    @if($family)
                        @if($family->spouse_business_address)
                            {{ strtoupper($family->spouse_business_address) }}
                        @else
                            N/A
                        @endif
                    @else
                        N/A
                    @endif
                </div>
            </td>
            <td style="width: 25%;border-right: solid 1px black; {{ adjustFontSize($childrens[4]['name'] ?? '', 12) }}"
                class="center-value">
                {{ strtoupper($childrens[4]['name']) }}
            </td>
            <td style="width: 13%" class="center-value">
                {{ $childrens[4]['birthdate'] }}
            </td>
        </tr>
        <tr style="border-top:solid 1px black">
            <td style="border-right: solid 1px black" class="gray">24. FATHER'S SURNAME</td>
            <td style="border-right: solid 1px black" colspan="2">
                <div class="value">
                    @if($family)
                        @if($family->father_lname)
                            {{ strtoupper($family->father_lname) }}
                        @else
                            N/A
                        @endif
                    @else
                        N/A
                    @endif
                </div>
            </td>
            <td style="width: 25%;border-right: solid 1px black; {{ adjustFontSize($childrens[6]['name'] ?? '', 12) }}"
                class="center-value">
                {{ strtoupper($childrens[5]['name']) }}
            </td>
            <td style="width: 13%" class="center-value">
                {{ $childrens[5]['birthdate'] }}
            </td>
        </tr>
        <tr style="border-top: solid 1px black">
            <td style="width: 20%;border-right: solid 1px black" class="gray">
                <div style="margin-left: 15px">FIRST NAME</div>
            </td>
            <td style="width: 25%;border-right: solid 1px black;">
                <div class="value">
                    @if($family)
                        @if($family->father_fname)
                            {{ strtoupper($family->father_fname) }}
                        @else
                            N/A
                        @endif
                    @else
                        N/A
                    @endif
                </div>
            </td>
            <td style="width: 17%;border-right: solid 1px black;font-size: 9.33px" class="gray">
                <div style="font-size:8px">NAME EXTENSION (JR.,SR)</div>
                <div class="value">
                    @if($family)
                        @if($family->father_ext_name)
                            {{ strtoupper($family->father_ext_name) }}
                        @else
                            N/A
                        @endif
                    @else
                        N/A
                    @endif
                </div>
            </td>
            <td style="width: 25%;border-right: solid 1px black; {{ adjustFontSize($childrens[7]['name'] ?? '', 12) }}"
                class="center-value">
                {{ strtoupper($childrens[6]['name']) }}
            </td>
            <td style="width: 13%" class="center-value">
                {{ $childrens[6]['birthdate'] }}
            </td>
        </tr>
        <tr style="border-top: solid 1px black">
            <td style="width: 20%;border-right: solid 1px black" class="gray">
                <div style="margin-left: 15px">MIDDLE NAME</div>
            </td>
            <td colspan="2" style="border-right: solid 1px black;">
                <div class="value">
                    @if($family)
                        @if($family->father_mname)
                            {{ strtoupper($family->father_mname) }}
                        @else
                            N/A
                        @endif
                    @else
                        N/A
                    @endif
                </div>
            </td>
            <td style="width: 25%;border-right: solid 1px black; {{ adjustFontSize($childrens[8]['name'] ?? '', 12) }}"
                class="center-value">
                {{ strtoupper($childrens[7]['name']) }}
            </td>
            <td style="width: 13%" class="center-value">
                {{ $childrens[7]['birthdate'] }}
            </td>
        </tr>
        <tr style="border-top:solid 1px black">
            <td style="border-right: solid 1px black" class="gray">25. MOTHER'S MAIDEN NAME</td>
            <td style="border-right: solid 1px black" colspan="2"></td>
            <td style="width: 25%;border-right: solid 1px black; {{ adjustFontSize($childrens[9]['name'] ?? '', 12) }}"
                class="center-value">
                {{ strtoupper($childrens[8]['name']) }}
            </td>
            <td style="width: 13%" class="center-value">
                {{ $childrens[8]['birthdate'] }}
            </td>
        </tr>
        <tr style="border-top: solid 1px black">
            <td style="width: 20%;border-right: solid 1px black" class="gray">
                <div style="margin-left: 15px">SURNAME</div>
            </td>
            <td colspan="2" style="width: 25%;border-right: solid 1px black;">
                <div class="value">
                    @if($family)
                        @if($family->mother_lname)
                            {{ strtoupper($family->mother_lname) }}
                        @else
                            N/A
                        @endif
                    @else
                        N/A
                    @endif
                </div>
            </td>
            <td style="width: 25%;border-right: solid 1px black; {{ adjustFontSize($childrens[10]['name'] ?? '', 12) }}"
                class="center-value">
                {{ strtoupper($childrens[9]['name']) }}
            </td>
            <td style="width: 13%" class="center-value">
                {{ $childrens[9]['birthdate'] }}
            </td>
        </tr>
        <tr style="border-top: solid 1px black">
            <td style="width: 20%;border-right: solid 1px black" class="gray">
                <div style="margin-left: 15px">FIRSTNAME</div>
            </td>
            <td colspan="2" style="width: 25%;border-right: solid 1px black;">
                <div class="value">
                    @if($family)
                        @if($family->mother_fname)
                            {{ strtoupper($family->mother_fname) }}
                        @else
                            N/A
                        @endif
                    @else
                        N/A
                    @endif
                </div>
            </td>
            <td style="width: 25%;border-right: solid 1px black; {{ adjustFontSize($childrens[11]['name'] ?? '', 12) }}"
                class="center-value">
                {{ strtoupper($childrens[10]['name']) }}
            </td>
            <td style="width: 13%" class="center-value">
                {{ $childrens[10]['birthdate'] }}
            </td>
        </tr>
        <tr style="border-top: solid 1px black">
            <td style="width: 20%;border-right: solid 1px black" class="gray">
                <div style="margin-left: 15px">MIDDLE NAME</div>
            </td>
            <td colspan="2" style="border-right: solid 1px black;">
                <div class="value">
                    @if($family)
                        @if($family->mother_mname)
                            {{ strtoupper($family->mother_mname) }}
                        @else
                            N/A
                        @endif
                    @else
                        N/A
                    @endif
                </div>
            </td>
            <td colspan="2" style="color:red;font-weight:bold;text-align: center" class="gray">(Continue on separate
                sheet if
                necessary)
            </td>
        </tr>
    </table>
    @php
        // Count total education rows and determine split point
        $totalEducationRows = 0;
        foreach($educations as $education) {
            $totalEducationRows += count($education);
        }
        
        // Maximum rows that can fit on page 1 (adjust this based on your page layout)
        $maxRowsPage1 = 8;
        $needsContinuation = $totalEducationRows > $maxRowsPage1;
        
        // Split educations into page 1 and continuation page
        $educationsPage1 = [];
        $educationsContinuation = [];
        $currentRowCount = 0;
        
        foreach($educations as $levelKey => $education) {
            $levelRowCount = count($education);
            
            if ($currentRowCount + $levelRowCount <= $maxRowsPage1) {
                // All entries for this level fit on page 1
                $educationsPage1[$levelKey] = $education;
                $currentRowCount += $levelRowCount;
            } else if ($currentRowCount < $maxRowsPage1) {
                // Partial entries fit on page 1
                $remainingSpace = $maxRowsPage1 - $currentRowCount;
                $educationsPage1[$levelKey] = array_slice($education, 0, $remainingSpace);
                $educationsContinuation[$levelKey] = array_slice($education, $remainingSpace);
                $currentRowCount = $maxRowsPage1;
            } else {
                // All entries go to continuation page
                $educationsContinuation[$levelKey] = $education;
            }
        }
    @endphp

    <table style="border-top: solid 1px black">
        <tr style="background: gray">
            <td colspan="8" class="title">III. EDUCATIONAL BACKGROUND</td>
        </tr>
        <tr style="text-align: center" class="gray">
            <td rowspan="2" style="vertical-align: top !important;border-right: solid 1px black">
                <p style="text-align: left !important;">26.</p>
                <div>LEVEL</div>
            </td>
            <td rowspan="2" style="border-right: solid 1px black">
                NAME OF SCHOOL<br/>(Write in full)
            </td>
            <td rowspan="2" style="border-right: solid 1px black">
                BASIC EDUCATION/DEGREE/COURSE<br/>
                (Write in full)
            </td>
            <td colspan="2" style="border-right: solid 1px black">PERIOD OF ATTENDANCE</td>
            <td rowspan="2" style="font-size: 8px !important;border-right: solid 1px black">
                HIGHEST LEVEL/<br/>UNITS EARNED<br/>(if not
                graduated)
            </td>
            <td rowspan="2" style=";border-right: solid 1px black;font-size: 8px !important;">YEAR GRADUATED</td>
            <td rowspan="2" style="font-size: 8px">SCHOLARSHI<br/>P/ACADEMIC<br/>HONORS<br/>RECEIVED
            </td>
        </tr>
        <tr style="text-align: center;border-top: solid 1px  black" class="gray">
            <td style="border-right: solid 1px black">FROM</td>
            <td style="border-right: solid 1px black">TO</td>
        </tr>
        @foreach($educationsPage1 as $levelKey => $education)
            <tr style="border-top: solid 1px  black">
                <td rowspan="{{ count($education) }}" style="width: 20%;border-right: solid 1px black"
                    class="gray">{{ $education[0]['level'] }}</td>
            @for($i=0; $i<count($education); $i++)
                @if($i > 0)
                    <tr style="border-top: solid 1px black">
                        @endif
                        <td style="width: 25%;border-right: solid 1px black;text-align: unset !important"
                            class="center-value education">
                            <div
                                style="padding-left: 3px;{{ schoolAdjustFontSize(strtoupper($education[$i]['school_name'])  ?? '', 12) }}">
                                {{ strtoupper($education[$i]['school_name']) }}
                            </div>
                        </td>
                        <td style="width: 21%;border-right: solid 1px black" class="center-value education">
                            <div
                                style="padding-left: 3px;{{ schoolAdjustFontSize(strtoupper($education[$i]['education_degree'])  ?? '', 12) }}">
                                {{ strtoupper($education[$i]['education_degree']) }}
                            </div>
                        </td>

                        <td style="width: 6%;border-right: solid 1px black"
                            class="center-value education">{{ $education[$i]['attendance_from'] }}</td>
                        <td style="width: 6%;border-right: solid 1px black"
                            class="center-value education">{{ $education[$i]['attendance_to'] }}</td>
                        <td style="width: 9%;border-right: solid 1px black"
                            class="center-value education">{{ strtoupper($education[$i]['highest_level']) }}</td>
                        <td style="width: 6%;border-right: solid 1px black"
                            class="center-value education">{{ $education[$i]['year_graduated'] }}</td>
                        <td style="width: 7%;" class="center-value education">
                            <div
                                style="{{ honorsAdjustFontSize(strtoupper($education[$i]['scholarship'])  ?? '', 12) }}">
                                {{ $education[$i]['scholarship'] }}
                            </div>
                        </td>

                        @if(count($education) > 1) </tr> @endif
                    @endfor
                    @if(count($education) < 2) </tr> @endif
                @endforeach
                <tr>
                    <td colspan="8"
                        style="border-top: solid 1px black;text-align: center;color:red;font-weight: bold;height: unset !important;"
                        class="gray">
                        (Continue on separate sheet if necessary)
                    </td>
                </tr>
                <tr style="border-top: solid 1px black;">
                    <td style="text-align: center;font-weight: bold;border-right: solid 1px black" class="gray">
                        SIGNATURE
                    </td>
                    <td colspan="2"
                        style="border-top: solid 1px black;text-align: center;color:red;font-weight: bold;height: unset !important;">

                    </td>
                    <td colspan="2" style="text-align: center;font-weight: bold;border-right: solid 1px black"
                        class="gray">
                        DATE
                    </td>
                    <td colspan="3" style="text-align: center;font-weight: bold">{{ $pds_date }}</td>
                </tr>
    </table>
</div>
<div style="text-align: right;font-size: 9.33px;font-style: italic">CS FORM 212 (Revised 2026), Page 1 of 4</div>

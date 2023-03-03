<div class="border-lg">
    <div style="padding: 4px">
        <div class="fw-bold">CS Form No. 212</div>
        <small class="fw-bold">Revised 2017</small>
        <h1 class="text-center fw-bold" style="padding:0px !important;line-height: 10px">PERSONAL DATA SHEET</h1>
        <div class="fw-light-bold label">WARNING: Any misrepresentation made in the Personal Data Sheet and the
            Work Experience Sheet shall cause the filing of administrative/criminal case/s against the person concerned.
        </div>
        <div class="label fw-light-bold fw-bold">READ THE ATTACHED GUIDE TO FILLING OUT THE PERSONAL DATA SHEET (PDS)
            BEFORE
            ACCOMPLISHING
            THE PDS FORM.
        </div>
    </div>
    <span style="font-size: 9.8px;padding-left: 3px">Print legibly. Tick appropriate boxes
            <input type="checkbox" style="margin-bottom:-8px"/>)
            <span>and use the separate sheet if necessary. Indicate N/A if not applicable. &nbsp;<strong>DO NOT ABBREVIATE.</strong></span>
        </span>
    <div class="border-dark" style="float:right;display: inline-block;padding-bottom: 2px;margin-top:-1.5px">
        <span class="fw-bold"
              style="color:white;background:rgb(150, 150, 150);padding:2px;margin:0px">1. CS ID No</span>
        <span style="margin-left: 30px;padding:2px">(Do not fill up. For CSC use only)</span>
    </div>
    <div class="item border-dark pl-1">I. PERSONAL INFORMATION</div>
    <table class="w-100 no-spacing bg-gray-white border" border="0">
        <tr>
            <td>
                <div style="padding: 5px !important;">2. SURNAME</div>
            </td>
            <td class="bg-white border-dark fw-bold v-center" colspan="2">{{ strtoupper($user->lname) }}</td>
        </tr>
        <tr>
            <td style="width: 145px !important;">
                <div style="margin-left: 10px">FIRST NAME</div>
            </td>
            <td class="bg-white border-dark fw-bold v-center" style="width: 50%">{{ strtoupper($user->fname) }}</td>
            <td style="width: 30%">
                <small>NAME OF EXTENSION (JR.SR)</small>
                <div class="fw-bold">
                    @if($user->suffix)
                        {{ strtoupper($user->suffix) }}
                    @else
                        N/A
                    @endif

                </div>
            </td>
        </tr>
        <tr>
            <td>
                <div style="padding: 5px !important;">MIDDLE NAME</div>
            </td>
            <td class="bg-white border-dark fw-bold v-center" colspan="2">{{ strtoupper($user->mname) }}</td>
        </tr>
    </table>
    <table class="w-100 no-spacing bg-gray-white border" border="0">
        <tr>
            <td style="width: 145px !important;" rowspan="2">
                <div style="padding: 5px !important;">
                    <label>3. DATE OF BIRTH</label>
                    <label class="ml-1">(mm/dd/yyyy)</label>
                </div>
            </td>
            <td style="width: 180px" class="bg-white border-dark fw-bold v-center" rowspan="2">
                @if($employee->birthdate)
                    {{ date('m/d/Y', strtotime($employee->birthdate)) }}
                @else
                    N/A
                @endif
            </td>
            <td style="width: 180px" rowspan="2" class="border-dark-right">
                <div>16. CITIZENSHIP</div>
            </td>
            <td class="bg-white">
                <div class="ml-1" style="display: inline;">
                    @if($employee)
                        @if($employee->citizenship == "Filipino")
                            <input type="checkbox" style="margin-bottom: -7px" checked/>
                        @else
                            <input type="checkbox" style="margin-bottom: -7px"/>
                        @endif
                    @else
                        <input type="checkbox" style="margin-bottom: -7px"/>
                    @endif
                    <span>Filipino</span>
                </div>
                <div class="ml-1" style="display: inline;">
                    @if($employee)
                        @if($employee->citizenship == "Dual Citizenship")
                            <input type="checkbox" style="margin-bottom: -7px" checked/>
                        @else
                            <input type="checkbox" style="margin-bottom: -7px"/>
                        @endif
                    @else
                        <input type="checkbox" style="margin-bottom: -7px"/>
                    @endif
                    <span>Dual Citizenship</span>
                </div>
            </td>
        </tr>
        <tr>
            <td class="bg-white">
                <div class="ml-1" style="display: inline;">
                    @if($employee)
                        @if($employee->citizenship_type == "by birth")
                            <input type="checkbox" style="margin-bottom: -7px;margin-left: 50px" checked/>
                        @else
                            <input type="checkbox" style="margin-bottom: -7px;margin-left: 50px"/>
                        @endif
                    @else
                        <input type="checkbox" style="margin-bottom: -7px;margin-left: 50px"/>
                    @endif
                    <span>by birth</span>
                </div>
                <div class="ml-1" style="display: inline;">
                    @if($employee)
                        @if($employee->citizenship_type == "by naturalization")
                            <input type="checkbox" style="margin-bottom: -7px" checked/>
                        @else
                            <input type="checkbox" style="margin-bottom: -7px"/>
                        @endif
                    @else
                        <input type="checkbox" style="margin-bottom: -7px"/>
                    @endif
                    <span>by naturalization</span>
                </div>
            </td>
        </tr>
        <tr>
            <td class="border-dark">
                <div style="padding: 5px !important;">
                    <label>4. PLACE OF BIRTH</label>
                </div>
            </td>
            <td class="bg-white border-dark fw-bold v-center">
                @if($employee->birthplace)
                    {{ strtoupper($employee->birthplace) }}
                @else
                    N/A
                @endif
            </td>
            <td class="border-dark-right">
                <div style="margin-left:20px">if holder of dual citizenship</div>
            </td>
            <td class="bg-white">
                <label style="margin-left: 60px">Pls. indicate country:</label>
            </td>
        </tr>
        <tr>
            <td class="border-dark">
                <div style="padding: 5px !important;">
                    <label>5. SEX</label>
                </div>
            </td>
            <td class="border-dark bg-white">
                <div class="ml-1" style="display: inline;">
                    @if($employee)
                        @if($employee->sex == "MALE")
                            <input type="checkbox" style="margin-bottom: -7px" checked/>
                        @else
                            <input type="checkbox" style="margin-bottom: -7px"/>
                        @endif
                    @else
                        <input type="checkbox" style="margin-bottom: -7px"/>
                    @endif
                    <span>Male</span>
                </div>
                <div class="ml-1" style="display: inline;">
                    @if($employee)
                        @if($employee->sex == "FEMALE")
                            <input type="checkbox" style="margin-bottom: -7px;margin-left: 50px;" checked/>
                        @else
                            <input type="checkbox" style="margin-bottom: -7px;margin-left: 50px;"/>
                        @endif
                    @else
                        <input type="checkbox" style="margin-bottom: -7px;margin-left: 50px;"/>
                    @endif
                    <span>Female</span>
                </div>
            </td>
            <td class="border-dark-right">
                <div style="margin-left:25px">please indicate the details.</div>
            </td>
            <td class="border-dark bg-white fw-bold v-center text-center">
                @if($employee->citizenship_country)
                    {{ strtoupper($employee->citizenship_country) }}
                @else
                    N/A
                @endif
            </td>
        </tr>
    </table>
    <table class="w-100 no-spacing bg-gray-white border" border="0">
        <tr>
            <td style="width: 145px !important;" class="border-dark">
                <div style="padding: 5px !important;">
                    <label>6. CIVIL STATUS</label>
                </div>
            </td>
            <td style="width: 180px" class="border-dark bg-white">
                <div>
                    <div class="ml-1" style="display: inline;">
                        @if($employee)
                            @if($employee->civil_status == "SINGLE")
                                <input type="checkbox" style="margin-bottom: -7px" checked/>
                            @else
                                <input type="checkbox" style="margin-bottom: -7px"/>
                            @endif
                        @else
                            <input type="checkbox" style="margin-bottom: -7px"/>
                        @endif
                        <span>Single</span>
                    </div>
                    <div style="display: inline;">
                        @if($employee)
                            @if($employee->civil_status == "MARRIED")
                                <input type="checkbox" style="margin-bottom: -7px;margin-left: 56px;" checked/>
                            @else
                                <input type="checkbox" style="margin-bottom: -7px;margin-left: 56px;"/>
                            @endif
                        @else
                            <input type="checkbox" style="margin-bottom: -7px;margin-left: 56px;"/>
                        @endif
                        <span>Married</span>
                    </div>
                </div>
                <div>
                    <div class="ml-1" style="display: inline;">
                        @if($employee)
                            @if($employee->civil_status == "WIDOWED")
                                <input type="checkbox" style="margin-bottom: -7px" checked/>
                            @else
                                <input type="checkbox" style="margin-bottom: -7px"/>
                            @endif
                        @else
                            <input type="checkbox" style="margin-bottom: -7px"/>
                        @endif
                        <span>Widowed</span>
                    </div>
                    <div class="ml-1" style="display: inline;">
                        @if($employee)
                            @if($employee->civil_status == "SEPARATED")
                                <input type="checkbox" style="margin-bottom: -7px;margin-left: 34px;" checked/>
                            @else
                                <input type="checkbox" style="margin-bottom: -7px;margin-left: 34px;"/>
                            @endif
                        @else
                            <input type="checkbox" style="margin-bottom: -7px;margin-left: 34px;"/>
                        @endif
                        <span>Separated</span>
                    </div>
                </div>
                <div>
                    <div class="ml-1" style="display: inline;">
                        @if($employee)
                            @if($employee->civil_status == "OTHERS")
                                <input type="checkbox" style="margin-bottom: -7px" checked/>
                            @else
                                <input type="checkbox" style="margin-bottom: -7px"/>
                            @endif
                        @else
                            <input type="checkbox" style="margin-bottom: -7px"/>
                        @endif
                        <span>Other/s:</span>
                    </div>
                </div>
            </td>
            <td style="width: 135px" class="border-dark-right" rowspan="2">
                <div style="font-size: 9px">17. RESIDENTIAL ADDRESS</div>
            </td>
            <td class="border-dark-right border-dark-left border-dark-bottom bg-white"
                style="margin: 0px !important;padding: 0px !important;">
                <div style="margin-top: 9px;">
                    <div
                        style="display: inline-block;width: 50%;margin-right: -5px">
                        @if($residential)
                            <div class="text-center fw-bold">{{ strtoupper($residential->house_no) }}</div>
                        @else
                            <div class="text-center fw-bold">N/A</div>
                        @endif
                        <div class="text-center border-gray-top border-dark-bottom">House/Block/Lot No.</div>
                    </div>
                    <div
                        style="display: inline-block;width: 50%;margin-right: -5px">
                        @if($residential)
                            <div class="text-center fw-bold">{{ strtoupper($residential->street) }}</div>
                        @else
                            <div class="text-center fw-bold">N/A</div>
                        @endif
                        <div class="text-center border-gray-top border-dark-bottom">Street</div>
                    </div>
                </div>
                <div style="margin-top: 3px;">
                    <div
                        style="display: inline-block;width: 50%;margin-right: -5px">
                        @if($residential)
                            <div class="text-center fw-bold">{{ strtoupper($residential->subdivision_village) }}</div>
                        @else
                            <div class="text-center fw-bold">N/A</div>
                        @endif
                        <div class="text-center border-gray-top">Subdivision/Village</div>
                    </div>
                    <div style="display: inline-block;width: 50%;margin-right: -5px">
                        @if($residential)
                            <div class="text-center fw-bold">{{ strtoupper($residential->barangay) }}</div>
                        @else
                            <div class="text-center fw-bold">N/A</div>
                        @endif
                        <div class="text-center border-gray-top">Barangay</div>
                    </div>
                </div>
            </td>
        </tr>
        <tr>
            <td style="width: 145px !important;" class="border-dark">
                <div style="padding: 5px !important;">
                    <label>7. HEIGHT (m)</label>
                </div>
            </td>
            <td class="border-dark-right border-dark-bottom bg-white v-center fw-bold">
                @if($employee->height)
                    {{ $employee->height }}
                @else
                    N/A
                @endif
            </td>
            <td class="border-dark-right border-dark-left border-dark-bottom bg-white"
                style="margin: 0px !important;padding: 0px !important;">
                <div style="margin-top: 9px;">
                    <div
                        style="display: inline-block;width: 50%;margin-right: -5px">
                        @if($residential)
                            <div class="text-center fw-bold">{{ strtoupper($residential->city) }}</div>
                        @else
                            <div class="text-center fw-bold">N/A</div>
                        @endif
                        <div class="text-center border-gray-top">City/Municipality</div>
                    </div>
                    <div
                        style="display: inline-block;width: 50%;margin-right: -5px">
                        @if($residential)
                            <div class="text-center fw-bold">{{ strtoupper($residential->province) }}</div>
                        @else
                            <div class="text-center fw-bold">N/A</div>
                        @endif
                        <div class="text-center border-gray-top">Province</div>
                    </div>
                </div>
            </td>
        </tr>
        <tr>
            <td style="width: 145px !important;" class="border-dark">
                <div style="padding: 5px !important;">
                    <label>8. WEIGHT (kg)</label>
                </div>
            </td>
            <td class="border-dark-right bg-white v-center fw-bold">
                @if($employee->weight)
                    {{ $employee->weight }}
                @else
                    N/A
                @endif
            </td>
            <td class="border-dark-right v-center text-center">
                ZIP CODE
            </td>
            <td class="border-dark-right border-dark-left border-dark-bottom bg-white text-center v-center fw-bold">
                @if($residential)
                    {{ $residential->zip_code }}
                @else
                    N/A
                @endif
            </td>
        </tr>
        <tr>
            <td style="width: 145px !important;" class="border-dark">
                <div style="padding: 5px !important;">
                    <label>9. BLOOD TYPE</label>
                </div>
            </td>
            <td style="width: 180px" class="border-dark bg-white v-center fw-bold">
                @if($employee->blood_type)
                    {{ $employee->blood_type }}
                @else
                    N/A
                @endif
            </td>
            <td style="width: 135px" class="border-dark-right border-dark-top" rowspan="3">
                <div style="font-size: 9px">18. PERMANENT ADDRESS</div>
            </td>
            <td class="border-dark-right border-dark-left border-dark-bottom bg-white"
                style="margin: 0px !important;padding: 0px !important;">
                <div style="margin-top: 9px;">
                    <div
                        style="display: inline-block;width: 50%;margin-right: -5px">
                        @if($permanent)
                            <div class="text-center fw-bold">{{ strtoupper($permanent->house_no) }}</div>
                        @else
                            <div class="text-center fw-bold">N/A</div>
                        @endif

                        <div class="text-center border-gray-top">House/Block/Lot No.</div>
                    </div>
                    <div
                        style="display: inline-block;width: 50%;margin-right: -5px">
                        @if($permanent)
                            <div class="text-center fw-bold">{{ strtoupper($permanent->street) }}</div>
                        @else
                            <div class="text-center fw-bold">N/A</div>
                        @endif
                        <div class="text-center border-gray-top">Street</div>
                    </div>
                </div>

            </td>
        </tr>
        <tr>
            <td style="width: 145px !important;" class="border-dark">
                <div style="padding: 5px !important;">
                    <label>10. GSIS ID NO.</label>
                </div>
            </td>
            <td class="border-dark-right border-dark-bottom bg-white v-center fw-bold">
                @if($employee->gsis_id_no)
                    {{ $employee->gsis_id_no }}
                @else
                    N/A
                @endif
            </td>
            <td class="border-dark-right border-dark-left border-dark-bottom bg-white"
                style="margin: 0px !important;padding: 0px !important;">
                <div style="margin-top: 9px;">
                    <div
                        style="display: inline-block;width: 50%;margin-right: -5px">
                        @if($permanent)
                            <div class="text-center fw-bold">{{ strtoupper($permanent->subdivision_village) }}</div>
                        @else
                            <div class="text-center fw-bold">N/A</div>
                        @endif
                        <div class="text-center border-gray-top">Subdivision/Village</div>
                    </div>
                    <div
                        style="display: inline-block;width: 50%;margin-right: -5px">
                        @if($permanent)
                            <div class="text-center fw-bold">{{ strtoupper($permanent->barangay) }}</div>
                        @else
                            <div class="text-center fw-bold">N/A</div>
                        @endif
                        <div class="text-center border-gray-top">Barangay</div>
                    </div>
                </div>
            </td>
        </tr>
        <tr>
            <td style="width: 145px !important;" class="border-dark">
                <div style="padding: 5px !important;">
                    <label>11. PAGIBIG-IBIG ID NO.</label>
                </div>
            </td>
            <td class="border-dark-right border-dark-bottom bg-white">
                <div class="fw-bold" style="vertical-align: center !important;margin-top: 5px">
                    @if($employee->pagibig_id_no)
                        {{ $employee->pagibig_id_no }}
                    @else
                        N/A
                    @endif
                </div>
            </td>

            <td class="border-dark-right border-dark-left border-dark-bottom bg-white"
                style="margin: 0px !important;padding: 0px !important;">
                <div style="margin-top: 9px;">
                    <div
                        style="display: inline-block;width: 50%;margin-right: -5px">
                        @if($permanent)
                            <div class="text-center fw-bold">{{ strtoupper($permanent->city) }}</div>
                        @else
                            <div class="text-center fw-bold">N/A</div>
                        @endif
                        <div class="text-center border-gray-top">City/Municipality</div>
                    </div>
                    <div
                        style="display: inline-block;width: 50%;margin-right: -5px">
                        @if($permanent)
                            <div class="text-center fw-bold">{{ strtoupper($permanent->province) }}</div>
                        @else
                            <div class="text-center fw-bold">N/A</div>
                        @endif
                        <div class="text-center border-gray-top">Province</div>
                    </div>
                </div>
            </td>
        </tr>
        <tr>
            <td style="width: 145px !important;" class="border-dark">
                <div style="padding: 5px !important;">
                    <label>12. PHILHEALTH NO.</label>
                </div>
            </td>
            <td class="border-dark-right border-dark-bottom bg-white">
                <div class="fw-bold" style="vertical-align: center !important;margin-top: 5px">
                    @if($employee->philhealth_no)
                        {{ $employee->philhealth_no }}
                    @else
                        N/A
                    @endif
                </div>
            </td>
            <td class="border-dark-right border-dark-bottom">
                <div style="vertical-align: center !important;text-align: center;margin-top: 5px">ZIP CODE</div>
            </td>
            <td class="text-center v-center border-dark-right border-dark-left border-dark-bottom bg-white fw-bold">
                @if($permanent)
                    {{ $permanent->zip_code }}
                @else
                    N/A
                @endif
            </td>
        </tr>
        <tr>
            <td style="width: 145px !important;" class="border-dark">
                <div style="padding: 5px !important;">
                    <label>13. SSS NO.</label>
                </div>
            </td>
            <td class="border-dark-right border-dark-bottom bg-white">
                <div class="fw-bold" style="vertical-align: center !important;margin-top: 5px">
                    @if($employee->sss_no)
                        {{ $employee->sss_no }}
                    @else
                        N/A
                    @endif
                </div>
            </td>
            <td class="border-dark-right border-dark-bottom">
                <div style="vertical-align: center !important;margin-top: 5px">19. TELEPHONE NO</div>
            </td>
            <td class="border-dark-right border-dark-bottom bg-white">
                <div class="fw-bold" style="vertical-align: center !important;margin-top: 5px">
                    @if($employee->telephone_no)
                        {{ $employee->telephone_no }}
                    @else
                        N/A
                    @endif
                </div>
            </td>
        </tr>
        <tr>
            <td style="width: 145px !important;" class="border-dark">
                <div style="padding: 5px !important;">
                    <label>14. TIN NO.</label>
                </div>
            </td>
            <td class="border-dark-right border-dark-bottom bg-white">
                <div class="fw-bold" style="vertical-align: center !important;margin-top: 5px">
                    @if($employee->tin_no)
                        {{ $employee->tin_no }}
                    @else
                        N/A
                    @endif
                </div>
            </td>
            <td class="border-dark-right border-dark-bottom">
                <div style="vertical-align: center !important;margin-top: 5px">20. MOBILE NO</div>
            </td>
            <td class="border-dark-right border-dark-bottom bg-white">
                <div class="fw-bold" style="vertical-align: center !important;margin-top: 5px">
                    @if($employee->mobile_no)
                        {{ $employee->mobile_no }}
                    @else
                        N/A
                    @endif
                </div>
            </td>
        </tr>
        <tr>
            <td style="width: 145px !important;" class="border-dark">
                <div style="padding: 5px !important;">
                    <label style="font-size: 10px !important;">15. AGENCY EMPLOYEE NO.</label>
                </div>
            </td>
            <td class="border-dark-right border-dark-bottom bg-white">
                <div class="fw-bold" style="vertical-align: center !important;margin-top: 5px">
                    @if($employee->employee_no)
                        {{ $employee->employee_no }}
                    @else
                        N/A
                    @endif
                </div>
            </td>
            <td class="border-dark-right border-dark-bottom">
                <div style="vertical-align: center !important;margin-top: 5px;font-size: 9.5px !important;">21. E-MAIL
                    ADDRESS (if any)
                </div>
            </td>
            <td class="border-dark-right border-dark-bottom bg-white">
                <div class="fw-bold" style="vertical-align: center !important;margin-top: 5px">
                    @if($employee->email_address)
                        {{ strtoupper($employee->email_address) }}
                    @else
                        N/A
                    @endif
                </div>
            </td>
        </tr>
    </table>
    <div class="item pl-1 border-dark">II. FAMILY BACKGROUND</div>
    <table class="w-100 h-100 no-spacing border-dark" border="0">
        <tr>
            <td class="v-center border-dark-top border-dark-left border-dark-right bg-gray-white">22. SPOUSE'S NAME</td>
            <td class="v-center border-dark fw-bold" colspan="2">
                @if($family)
                    @if($family->spouse_lname)
                        {{ strtoupper($family->spouse_lname) }}
                    @else
                        N/A
                    @endif
                @else
                    N/A
                @endif
            </td>
            <td class="v-center text-center border-dark bg-gray-white">23. NAME of Children (Write full name and list
                all)
            </td>
            <td class="v-center text-center border-dark bg-gray-white" style="font-size: 7px;width: auto !important;">
                DATE OF
                BIRTH<br/>(mm/dd/yyyy)
            </td>
        </tr>
        <tr>
            <td class="v-center border-dark-left border-dark-right bg-gray-white"
                style="width: 130px !important;padding-left: 14px !important;">FIRST NAME
            </td>
            <td class="v-center bg-white border-dark fw-bold" style="width: 210px">
                @if($family)
                    @if($family->spouse_fname)
                        {{ strtoupper($family->spouse_fname) }}
                    @else
                        N/A
                    @endif
                @else
                    N/A
                @endif
            </td>
            <td class="v-center bg-gray-white" style="padding-left: 3px !important;">
                <small style="font-size: 8px !important;">NAME OF EXTENSION (JR.SR)</small>
                <div class="fw-bold">
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
            <td class="v-center text-center border-dark fw-bold">
                {{ $childrens[0]['name'] }}
            </td>
            <td class="v-center text-center border-dark fw-bold" style="font-size: 8px !important;">
                {{ $childrens[0]['birthdate'] }}
            </td>
        </tr>
        <tr>
            <td class="v-center border-dark-left border-dark-right bg-gray-white"
                style="padding-left: 14px !important;">MIDDLE NAME
            </td>
            <td class="v-center border-dark fw-bold" colspan="2">
                @if($family)
                    @if($family->spouse_mname)
                        {{ strtoupper($family->spouse_mname) }}
                    @else
                        N/A
                    @endif
                @else
                    N/A
                @endif
            </td>
            <td class="v-center text-center border-dark fw-bold">
                {{ $childrens[1]['name'] }}
            </td>
            <td class="v-center text-center border-dark fw-bold" style="font-size: 8px !important;">
                {{ $childrens[1]['birthdate'] }}
            </td>
        </tr>
        <tr>
            <td class="v-center border-dark bg-gray-white" style="padding-left: 14px !important;">OCCUPATION</td>
            <td class="v-center border-dark fw-bold" colspan="2">
                @if($family)
                    @if($family->spouse_occupation)
                        {{ strtoupper($family->spouse_occupation) }}
                    @else
                        N/A
                    @endif
                @else
                    N/A
                @endif
            </td>
            <td class="v-center text-center border-dark fw-bold">
                {{ $childrens[2]['name'] }}
            </td>
            <td class="v-center text-center border-dark fw-bold" style="font-size: 8px !important;">
                {{ $childrens[2]['birthdate'] }}
            </td>
        </tr>
        <tr>
            <td class="v-center border-dark bg-gray-white"
                style="padding-left: 14px !important;font-size:9px !important;">
                EMPLOYER/BUSINESS
                NAME
            </td>
            <td class="v-center border-dark fw-bold" colspan="2">
                @if($family)
                    @if($family->spouse_employer)
                        {{ strtoupper($family->spouse_employer) }}
                    @else
                        N/A
                    @endif
                @else
                    N/A
                @endif
            </td>
            <td class="v-center text-center border-dark fw-bold">
                {{ $childrens[3]['name'] }}
            </td>
            <td class="v-center text-center border-dark fw-bold" style="font-size: 8px !important;">
                {{ $childrens[3]['birthdate'] }}
            </td>
        </tr>
        <tr>
            <td class="v-center border-dark bg-gray-white" style="padding-left: 14px !important;">BUSINESS ADDRESS</td>
            <td class="v-center border-dark fw-bold" colspan="2">
                @if($family)
                    @if($family->spouse_business_address)
                        {{ strtoupper($family->spouse_business_address) }}
                    @else
                        N/A
                    @endif
                @else
                    N/A
                @endif
            </td>
            <td class="v-center text-center border-dark fw-bold">
                {{ $childrens[4]['name'] }}
            </td>
            <td class="v-center text-center border-dark fw-bold" style="font-size: 8px !important;">
                {{ $childrens[4]['birthdate'] }}
            </td>
        </tr>
        <tr>
            <td class="v-center border-dark bg-gray-white" style="padding-left: 14px !important;">TELEPHONE NO.</td>
            <td class="v-center border-dark fw-bold" colspan="2">
                @if($family)
                    @if($family->spouse_contact_no)
                        {{ strtoupper($family->spouse_contact_no) }}
                    @else
                        N/A
                    @endif
                @else
                    N/A
                @endif
            </td>
            <td class="v-center text-center border-dark fw-bold">
                {{ $childrens[5]['name'] }}
            </td>
            <td class="v-center text-center border-dark fw-bold" style="font-size: 8px !important;">
                {{ $childrens[5]['birthdate'] }}
            </td>
        </tr>
        <tr>
            <td class="v-center border-dark-top border-dark-left border-dark-right bg-gray-white">24. FATHER'S SURNAME
            </td>
            <td class="v-center border-dark fw-bold" colspan="2">
                @if($family)
                    @if($family->father_lname)
                        {{ strtoupper($family->father_lname) }}
                    @else
                        N/A
                    @endif
                @else
                    N/A
                @endif
            </td>
            <td class="v-center text-center border-dark fw-bold">
                {{ $childrens[6]['name'] }}
            </td>
            <td class="v-center text-center border-dark fw-bold" style="font-size: 8px !important;">
                {{ $childrens[6]['birthdate'] }}
            </td>
        </tr>
        <tr>
            <td class="v-center border-dark-left border-dark-right bg-gray-white"
                style="padding-left: 14px !important;">FIRST NAME
            </td>
            <td class="v-center bg-white border-dark fw-bold bg-gray-white">
                @if($family)
                    @if($family->father_fname)
                        {{ strtoupper($family->father_fname) }}
                    @else
                        N/A
                    @endif
                @else
                    N/A
                @endif
            </td>
            <td class="v-center bg-gray-white" style="padding-left: 3px !important;">
                <small style="font-size: 8px !important;">NAME OF EXTENSION (JR.SR)</small>
                <div class="fw-bold">
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
            <td class="v-center text-center border-dark fw-bold">
                {{ $childrens[7]['name'] }}
            </td>
            <td class="v-center text-center border-dark fw-bold" style="font-size: 8px !important;">
                {{ $childrens[7]['birthdate'] }}
            </td>
        </tr>
        <tr>
            <td class="v-center border-dark-left border-dark-right bg-gray-white"
                style="padding-left: 14px !important;">MIDDLE NAME
            </td>
            <td class="v-center border-dark fw-bold" colspan="2">
                @if($family)
                    @if($family->father_mname)
                        {{ strtoupper($family->father_mname) }}
                    @else
                        N/A
                    @endif
                @else
                    N/A
                @endif
            </td>
            <td class="v-center text-center border-dark fw-bold">
                {{ $childrens[8]['name'] }}
            </td>
            <td class="v-center text-center border-dark fw-bold" style="font-size: 8px !important;">
                {{ $childrens[8]['birthdate'] }}
            </td>
        </tr>
        <tr>
            <td class="v-center border-dark-top border-dark-left border-dark-right bg-gray-white"
                style="font-size: 9px !important;">
                25.
                MOTHER'S MAIDEN NAME
            </td>
            <td class="border-dark" colspan="2"></td>
            <td class="v-center text-center border-dark fw-bold">
                {{ $childrens[9]['name'] }}
            </td>
            <td class="v-center text-center border-dark fw-bold" style="font-size: 8px !important;">
                {{ $childrens[9]['birthdate'] }}
            </td>
        </tr>
        <tr>
            <td class="v-center border-dark-left border-dark-right bg-gray-white"
                style="padding-left: 14px !important;">SURNAME
            </td>
            <td class="v-center bg-white border-dark fw-bold" style="width: 180px" colspan="2">
                @if($family)
                    @if($family->mother_lname)
                        {{ strtoupper($family->mother_lname) }}
                    @else
                        N/A
                    @endif
                @else
                    N/A
                @endif
            </td>
            <td class="v-center text-center border-dark fw-bold">
                {{ $childrens[10]['name'] }}
            </td>
            <td class="v-center text-center border-dark fw-bold" style="font-size: 8px !important;">
                {{ $childrens[10]['birthdate'] }}
            </td>
        </tr>
        <tr>
            <td class="v-center border-dark-left border-dark-right bg-gray-white"
                style="padding-left: 14px !important;">FIRSTNAME
            </td>
            <td class="v-center bg-white border-dark fw-bold">
                @if($family)
                    @if($family->mother_fname)
                        {{ strtoupper($family->mother_fname) }}
                    @else
                        N/A
                    @endif
                @else
                    N/A
                @endif
            </td>
            <td class="v-center border-dark bg-gray-white">
                <small style="font-size: 8px !important;">NAME OF EXTENSION
                    (JR.SR)</small>
                <div class="fw-bold">
                    @if($family)
                        @if($family->mother_ext_name)
                            {{ strtoupper($family->mother_ext_name) }}
                        @else
                            N/A
                        @endif
                    @else
                        N/A
                    @endif
                </div>
            </td>
            <td class="v-center text-center border-dark fw-bold">
                {{ $childrens[11]['name'] }}
            </td>
            <td class="v-center text-center border-dark fw-bold" style="font-size: 8px !important;">
                {{ $childrens[11]['birthdate'] }}
            </td>
        </tr>
        <tr>
            <td class="v-center border-dark-left border-dark-right bg-gray-white"
                style="padding-left: 14px !important;">MIDDLE NAME
            </td>
            <td class="v-center border-dark fw-bold" colspan="2">
                @if($family)
                    @if($family->mother_mname)
                        {{ strtoupper($family->mother_mname) }}
                    @else
                        N/A
                    @endif
                @else
                    N/A
                @endif
            </td>
            <td class="v-center text-center border-dark fw-bold">
                {{ $childrens[12]['name'] }}
            </td>
            <td class="v-center text-center border-dark fw-bold" style="font-size: 8px !important;">
                {{ $childrens[12]['birthdate'] }}
            </td>
        </tr>
    </table>

    <div class="item pl-1 border-dark">III. EDUCATIONAL BACKGROUND</div>
    <table class="w-100 h-100 no-spacing border" border="0" id="education">
        <tr class="bg-gray-white" style="font-size: 9px !important;">
            <td class="border-dark v-center text-center" rowspan="2">
                26. LEVEL
            </td>
            <td class="border-dark v-center text-center" rowspan="2">
                NAME OF SCHOOL <br \>(Write in full)
            </td>
            <td class="border-dark v-center text-center" rowspan="2">
                BASIC EDUCATION/DEGREE/COURSE <br \>(Write in full)
            </td>
            <td class="v-center text-center" colspan="2">
                PERIOD OF ATTENDANCE
            </td>
            <td class="border-dark v-center text-center" rowspan="2">
                HIGHEST LEVEL/<br \>UNITS EARNED<br/>(if not graduated)
            </td>
            <td class="border-dark v-center text-center" rowspan="2">
                YEAR<br \>GRADUATED
            </td>
            <td class="border-dark v-center text-center" rowspan="2">
                SCHOLARSHIP<br \>ACADEMIC<br/>HONORS<br/>RECEIVED
            </td>

        </tr>
        <tr class="border-dark bg-gray-white">
            <td class="border-dark v-center text-center">From</td>
            <td class="border-dark v-center text-center">To</td>
        </tr>

        @foreach($educations as $education)
            <tr>
                <td class="border-dark v-center text-center bg-gray-white" style="width: 142px !important;">
                    {{ $education['level'] }}
                </td>
                <td class="border-dark fw-bold v-center text-center" style="width: 190px">
                    {{ strtoupper($education['school_name']) }}
                </td>
                <td class="border-dark fw-bold v-center text-center" style="width: 185px">
                    {{ strtoupper($education['education_degree']) }}
                </td>
                <td class="border-dark fw-bold v-center text-center" style="font-size: 7.5px">
                    {{ $education['attendance_from'] }}
                </td>
                <td class="border-dark fw-bold v-center text-center" style="font-size: 7.5px">
                    {{ $education['attendance_to'] }}
                </td>
                <td class="border-dark fw-bold v-center text-center">
                    {{ strtoupper($education['highest_level']) }}
                </td>
                <td class="border-dark fw-bold v-center text-center">
                    {{ $education['year_graduated'] }}
                </td>
                <td class="border-dark fw-bold v-center text-center">
                    {{ $education['scholarship'] }}
                </td>
            </tr>
        @endforeach
        <tr>
            <td colspan="8" style="color: darkred;height: unset !important;"
                class="v-center text-center fw-bold bg-gray-white">
                (Continue on separate sheet if necessary)
            </td>
        </tr>
        <tr>
            <td class="border-dark v-center text-center fw-bold bg-gray-white" style="width: 142px !important;">
                SIGNATURE
            </td>
            <td class="border-dark" colspan="2"></td>
            <td class="border-dark v-center text-center fw-bold bg-gray-white" colspan="2">
                DATE
            </td>
            <td class="border-dark" colspan="3"></td>
        </tr>
    </table>
</div>
<div style="text-align: right">CS FORM 212 (Revised 2017), Page 1 of 4</div>

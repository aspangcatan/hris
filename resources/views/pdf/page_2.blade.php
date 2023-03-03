<div class="border-lg" style="margin-top:10px">
    <div class="item pl-1 border-dark">IV. CIVIL SERVICE ELIGIBILITY</div>
    <table class="w-100 h-100 no-spacing border" border="0" id="eligibility">
        <tr class="h-100 bg-gray-white" style="font-size: 9px !important;">
            <td class="border-dark v-center text-center" rowspan="2" style="font-size: 8px">
                27. CAREER SERVICE/RA 1080 (BOARD/BAR) UNDER<br/>SPECIAL LAWS/CES/CSEE<br/>BARANGAY ELIGIBILITY/DRIVER'S
                LICENSE

            </td>
            <td class="border-dark v-center text-center" rowspan="2">
                RATING <br \>(If Applicable)
            </td>
            <td class="border-dark v-center text-center" rowspan="2">
                DATE OF<br/>EXAMINATION/<br/>CONFERMENT
            </td>
            <td class="border-dark v-center text-center" rowspan="2">
                PLACE OF EXAMINATION/CONFERMENT
            </td>
            <td class="border-dark v-center text-center" colspan="2">
                LICENSE (If applicable)
            </td>
        </tr>
        <tr class="bg-gray-white">
            <td class="border-dark v-center text-center">
                NUMBER
            </td>
            <td class="border-dark v-center text-center">
                Date of<br/>Validity
            </td>
        </tr>
        @foreach($eligibility as $key)
            <tr>
                <td style="height: 33px !important;" class="border-dark v-center text-center fw-bold">{{ strtoupper($key['career_service']) }}</td>
                <td class="border-dark v-center text-center fw-bold">{{ strtoupper($key['rating']) }}</td>
                <td class="border-dark v-center text-center fw-bold">{{ strtoupper($key['examination_date']) }}</td>
                <td class="border-dark v-center text-center fw-bold">{{ strtoupper($key['examination_place']) }}</td>
                <td class="border-dark v-center text-center fw-bold">{{ strtoupper($key['license_no'] )}}</td>
                <td class="border-dark v-center text-center fw-bold">{{ strtoupper($key['license_expiry']) }}</td>
            </tr>
        @endforeach
        <tr class="bg-gray-white">
            <td colspan="6" style="color: darkred;height: unset !important;" class="v-center text-center fw-bold">
                (Continue on separate sheet if necessary)
            </td>
        </tr>
    </table>
    <div class="item pl-1 border-dark">V. WORK EXPERIENCE</div>
    <table class="w-100 h-100 no-spacing border" border="0" id="eligibility">
        <tr class="h-100 bg-gray-white" style="font-size: 9px !important;">
            <td class="border-dark v-center text-center" colspan="2">
                28. INCLUSIVE DATES<br/>(mm/dd/yyyy)
            </td>
            <td class="border-dark v-center text-center" rowspan="2">
                POSITION TITLE <br \>(Write in full/Do not abbreviate)
            </td>
            <td class="border-dark v-center text-center" rowspan="2">
                DEPARTMENT/AGENCY/OFFIEC/COMPANY<br/>(Write in full/Do not abbreviate)
            </td>
            <td class="border-dark v-center text-center" rowspan="2">
                MONTHLY<br/>SALARY
            </td>
            <td class="border-dark v-center text-center" style="font-size: 7px" rowspan="2">
                SALARY/JOB/PAY<br/>GRADE (if<br/>applicable)& STEP<br/>
                (Format "00-0")/<br/>
                INCREMENT
            </td>
            <td class="border-dark v-center text-center" rowspan="2">STATUS OF<br/>APPOINTMENT</td>
            <td class="border-dark v-center text-center" rowspan="2">GOV'T<br/>SERVICE<br/>(Y/N)</td>
        </tr>
        <tr class="bg-gray-white">
            <td class="border-dark v-center text-center">
                From
            </td>
            <td class="border-dark v-center text-center">
                To
            </td>
        </tr>
        @foreach($work_experience as $key)
            <tr>
                <td style="height: 33px !important;" class="border-dark v-center text-center fw-bold">{{ strtoupper($key['working_from']) }}</td>
                <td class="border-dark v-center text-center fw-bold">{{ strtoupper($key['working_to']) }}</td>
                <td class="border-dark v-center text-center fw-bold">{{ strtoupper($key['position']) }}</td>
                <td class="border-dark v-center text-center fw-bold">{{ strtoupper($key['agency']) }}</td>
                <td class="border-dark v-center text-center fw-bold">{{ strtoupper($key['monthly_salary']) }}</td>
                <td class="border-dark v-center text-center fw-bold">{{ strtoupper($key['salary_grade']) }}</td>
                <td class="border-dark v-center text-center fw-bold">{{ strtoupper($key['appointment_status']) }}</td>
                <td class="border-dark v-center text-center fw-bold">{{ strtoupper($key['is_government']) }}</td>
            </tr>
        @endforeach
        <tr class="bg-gray-white">
            <td colspan="8" style="color: darkred;height: unset !important;" class="v-center text-center fw-bold">
                (Continue on separate sheet if necessary)
            </td>
        </tr>
        <tr>
            <td class="border-dark v-center text-center fw-bold bg-gray-white" style="width: 142px !important;" colspan="2">
                SIGNATURE
            </td>
            <td class="border-dark" colspan="2"></td>
            <td class="border-dark v-center text-center fw-bold bg-gray-white" colspan="2">
                DATE
            </td>
            <td class="border-dark" colspan="2"></td>
        </tr>
    </table>
</div>
<div style="text-align: right">CS FORM 212 (Revised 2017), Page 2 of 4</div>

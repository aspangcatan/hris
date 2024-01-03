<div id="page_6" style="border:1px solid black">
    <table style="border-top: solid 1px black">
        <tr style="background: gray">
            <td colspan="8"
                class="title">V. WORK EXPERIENCE<br/><span style="font-size: 11.33px">(Include private employment. Start from your recent work)
                    Description of duties should be indicated in the attached Work Experience sheet.</span>
            </td>
        </tr>
        <tr style="text-align: center" class="gray">
            <td colspan="2" style="border-right: solid 1px black">
                <div>28. INCLUSIVE DATES<br/>
                    (mm/dd/yyyy)
                </div>
            </td>
            <td rowspan="2" style="border-right: solid 1px black">
                POSITION TITLE<br/>
                (Write in full/Do not abbreviate)
            </td>
            <td rowspan="2" style="border-right: solid 1px black;font-size: 10px">
                DEPARTMENT/AGENCY/OFFICE/COMPANY<br/>
                (Write in full/Do not abbreviate)
            </td>
            <td rowspan="2" style=";border-right: solid 1px black">MONTHLY<br/>SALARY
            </td>
            <td rowspan="2" style="border-right: solid 1px black;font-size: 9px !important;">SALARY/JOB/<br/>PAY
                GRADE(if<br/>applicable)&<br/>STEP
                (Format<br/>"00-0")/<br/>INCREMENT
            </td>
            <td rowspan="2" style="border-right: solid 1px black">STATUS OF<br/>APPOINTMENT</td>
            <td rowspan="2">GOV'T<br/>SERVICE<br/>(Y/N)</td>
        </tr>
        <tr style="text-align: center;border-top: solid 1px  black" class="gray">
            <td style=";border-right: solid 1px black">From</td>
            <td style="border-right: solid 1px black">To</td>
        </tr>
        @foreach($work_experience as $key)
            <tr style="border-top: solid 1px  black">
                <td style="width: 9%;border-right: solid 1px black;font-size: 11px"
                    class="center-value">{{ strtoupper($key['working_from']) }}</td>
                <td style="width: 9%;border-right: solid 1px black;font-size: 11px"
                    class="center-value">{{ strtoupper($key['working_to']) }}</td>
                <td style="width: 20%;border-right: solid 1px black;font-size: 10px"
                    class="center-value">{{ strtoupper($key['position']) }}</td>
                <td style="width: 27%;border-right: solid 1px black;font-size: 10px"
                    class="center-value">{{ strtoupper($key['agency']) }}</td>
                <td style="width: 7%;border-right: solid 1px black;font-size: 11px"
                    class="center-value">{{ strtoupper($key['monthly_salary']) }}</td>
                <td style="width: 8%;border-right: solid 1px black;font-size: 11px"
                    class="center-value">{{ strtoupper($key['salary_grade']) }}</td>
                <td style="width: 12%;border-right: solid 1px black;font-size: 11px"
                    class="center-value">{{ strtoupper($key['appointment_status']) }}</td>
                <td style="width: 8%;" class="center-value">{{ strtoupper($key['is_government']) }}</td>
            </tr>
        @endforeach
        <tr style="border-top: solid 1px  black;text-align: center">
            <td colspan="8" style="color:red;font-weight: bold;height:  unset !important;" class="gray">(Continue on
                separate sheet if necessary)
            </td>
        </tr>
    </table>
    <table>
        <tr style="border-top: solid 1px black;">
            <td style="width:16%;text-align: center;font-weight: bold;border-right: solid 1px black" class="gray">
                SIGNATURE
            </td>
            <td style="border-right: solid 1px black"></td>
            <td style="width:14%;text-align: center;font-weight: bold;border-right: solid 1px black" class="gray">
                DATE
            </td>
            <td style="width:28%;text-align: center;font-weight: bold">{{ $pds_date }}</td>
        </tr>
    </table>
</div>

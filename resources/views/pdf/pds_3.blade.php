<div id="page_3" style="border:1px solid black">
    <table style="border-top: solid 1px black">
        <tr style="background: gray">
            <td colspan="6"
                class="title">VI. VOLUNTARY WORK OR INVOLVEMENT IN CIVIC / NON-GOVENMENT / PEOPLE / VOLUNTARY
                ORGANIZATION/S
            </td>
        </tr>
        <tr style="text-align: center" class="gray">
            <td rowspan="2" style="vertical-align: top !important;border-right: solid 1px black">
                <div>29. NAME & ADDRESS OF ORGANIZATION<br/>
                    (Write in full)
                </div>
            </td>
            <td colspan="2" style="border-right: solid 1px black">
                INCLUSIVE DATES<br/>
                (mm/dd/yyyy)
            </td>
            <td rowspan="2" style="border-right: solid 1px black">
                NUMBER OF<br/>
                HOURS
            </td>
            <td rowspan="2">
                POSITION / NATURE OF WORK
            </td>
        </tr>
        <tr style="text-align: center;border-top: solid 1px  black" class="gray">
            <td style=";border-right: solid 1px black">From</td>
            <td style=";border-right: solid 1px black">To</td>
        </tr>
        @foreach($voluntary_work as $key)
            <tr style="border-top: solid 1px  black">
                <td style="width: 45%;border-right: solid 1px black" class="center-value">{{ strtoupper($key['organization_address']) }}</td>
                <td style="width: 7%;border-right: solid 1px black" class="center-value">{{ $key['working_from'] }}</td>
                <td style="width: 7%;border-right: solid 1px black" class="center-value">{{ $key['working_to'] }}</td>
                <td style="width: 7%;border-right: solid 1px black" class="center-value">{{ $key['number_hours'] }}</td>
                <td style="width: 34%;" class="center-value">{{ strtoupper($key['position']) }}</td>
            </tr>
        @endforeach
        <tr style="border-top: solid 1px  black;text-align: center">
            <td colspan="5" style="color:red;font-weight: bold;height:  unset !important;" class="gray">(Continue on
                separate sheet if necessary)
            </td>
        </tr>
    </table>
    <table style="border-top: solid 1px black">
        <tr style="background: gray">
            <td colspan="8"
                class="title">VII. LEARNING AND DEVELOPMENT (L&D) INTERVENTIONS/TRAINING PROGRAMS ATTENDED
            </td>
        </tr>
        <tr style="text-align: center" class="gray">
            <td rowspan="2" style="vertical-align: top !important;border-right: solid 1px black">
                <div>30. TITLE OF LEARNING AND DEVELOPMENT INTERVENTIONS/TRAINING PROGRAMS<br/>
                    (Write in full)
                </div>
            </td>
            <td colspan="2" style="border-right: solid 1px black">
                INCLUSIVE DATES OF<br/>
                ATTENDANCE<br/>
                (mm/dd/yyyy)
            </td>
            <td rowspan="2" style="border-right: solid 1px black">
                NUMBER OF HOURS
            </td>
            <td rowspan="2" style=";border-right: solid 1px black">Type of LD<br/>
                (Managerial/<br/>Supervisory/<br/>Technical/etc)
            </td>
            <td rowspan="2">CONDUCTED/SPONSORED BY<br/>(Write in full)
            </td>
        </tr>
        <tr style="text-align: center;border-top: solid 1px  black" class="gray">
            <td style=";border-right: solid 1px black">From</td>
            <td style="border-right: solid 1px black">To</td>
        </tr>
        @foreach($learning as $key)
            <tr style="border-top: solid 1px  black">
                <td style="width: 45%;border-right: solid 1px black" class="center-value">{{ strtoupper($key['learning_title']) }}</td>
                <td style="width: 7%;border-right: solid 1px black" class="center-value">{{ strtoupper($key['attendance_from']) }}</td>
                <td style="width: 7%;border-right: solid 1px black" class="center-value">{{ strtoupper($key['attendance_to']) }}</td>
                <td style="width: 7%;border-right: solid 1px black" class="center-value">{{ strtoupper($key['number_hours']) }}</td>
                <td style="width: 10%;border-right: solid 1px black" class="center-value">{{ strtoupper($key['learning_type']) }}</td>
                <td style="width: 24%;" class="center-value">{{ strtoupper($key['conducted_by']) }}</td>
            </tr>
        @endforeach
        <tr style="border-top: solid 1px  black;text-align: center">
            <td colspan="6" style="color:red;font-weight: bold;height:  unset !important;" class="gray">(Continue on
                separate sheet if necessary)
            </td>
        </tr>
    </table>
    <table style="border-top: solid 1px black">
        <tr style="background: gray">
            <td colspan="8"
                class="title">VIII. OTHER INFORMATION
            </td>
        </tr>
        <tr style="text-align: center" class="gray">
            <td style="border-right: solid 1px black">
                <div>31. SPECIAL SKILLS and HOBBIES</div>
            </td>
            <td colspan="2" style="border-right: solid 1px black">
                32. NON-ACADEMIC DISTINCTIONS/RECOGNITION<br/>
                (Write in full)
            </td>
            <td>
                33. MEMBERSHIP IN ASSOCIATION/ORGANIZATION<br/>
                (Write in full)
            </td>
        </tr>
        @foreach($skill as $key)
            <tr style="border-top: solid 1px  black">
                <td style="width: 24%;border-right: solid 1px black" class="center-value">{{ strtoupper($key['skill']) }}</td>
                <td colspan="2"
                    style="width: 52%;border-right: solid 1px black" class="center-value">{{ strtoupper($key['recognition']) }}</td>
                <td style="width: 24%" class="center-value">{{ strtoupper($key['association']) }}</td>
            </tr>
        @endforeach
        <tr style="border-top: solid 1px  black;text-align: center">
            <td colspan="4" style="color:red;font-weight: bold;height:  unset !important;" class="gray">(Continue on
                separate sheet if necessary)
            </td>
        </tr>
        <tr style="border-top: solid 1px black;">
            <td style="width: 24%;text-align: center;font-weight: bold;border-right: solid 1px black" class="gray">
                SIGNATURE
            </td>
            <td style="width:32%;border-right: solid 1px black"></td>
            <td style="width:20%;border-right: solid 1px black;text-align: center;font-weight: bold" class="gray">DATE</td>
            <td style="width: 24%;"></td>
        </tr>
    </table>
</div>
<div style="text-align: right">
    CS FORM 212 (Revised 2017), Page 3 of 4
</div>
@if(count($next_page_learning) > 0)
    @for($i=0; $i<count($next_page_learning); $i++)
        <div style="page-break-before: always !important;"></div>
        @include('pdf.pds_5',['learning'=>$next_page_learning[$i]])
    @endfor
@endif

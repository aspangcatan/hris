<div class="border-lg" style="margin-top:10px">
    <div class="item pl-1 border-dark">VI. VOLUNTARY WORK OR INVOLVEMENT IN CIVIC/NON-GOVERNMENT/PEOPLE/VOLUNTARY
        ORGANIZATION/S
    </div>
    <table class="w-100 h-100 no-spacing border" border="0">
        <tr class="h-100 bg-gray-white" style="font-size: 9px !important;">
            <td class="border-dark v-center text-center" rowspan="2" style="width: 40%">
                29. NAME & ADDRESS OF ORGANIZATION<br/>(Write in full)

            </td>
            <td class="border-dark v-center text-center" colspan="2">
                INCLUSIVE DATES<br/>(mm/dd/yyyy)
            </td>
            <td class="border-dark v-center text-center" rowspan="2">
                NUMBER OF HOURS
            </td>
            <td class="border-dark v-center text-center" rowspan="2">
                POSITION/NATURE OF WORK
            </td>
        </tr>
        <tr class="bg-gray-white">
            <td class="border-dark v-center text-center">
                FROM
            </td>
            <td class="border-dark v-center text-center">
                TO
            </td>
        </tr>
        @foreach($voluntary_work as $key)
            <tr>
                <td style="height: 33px !important;"
                    class="border-dark v-center text-center fw-bold">{{ strtoupper($key['organization_address']) }}</td>
                <td class="border-dark v-center text-center fw-bold"> {{ $key['working_from'] }}</td>
                <td class="border-dark v-center text-center fw-bold"> {{ $key['working_to'] }}</td>
                <td class="border-dark v-center text-center fw-bold"> {{ $key['number_hours'] }}</td>
                <td class="border-dark v-center text-center fw-bold"> {{ strtoupper($key['position']) }}</td>
            </tr>
        @endforeach
        <tr class="bg-gray-white">
            <td colspan="5" style="color: darkred;height: unset !important;" class="v-center text-center fw-bold">
                (Continue on separate sheet if necessary)
            </td>
        </tr>
    </table>
    <div class="item pl-1 border-dark">
        <div>VII. LEARNING AND DEVELOPMENT (L&D) INTERVENTIONS/TRAINING PROGRAMS ATTENDED</div>
        <small>(Start from the most recent L&D/training program and include only the relevant L&D/training for the last
            five (5) years of Division Chief/Executive/Managerial position)</small></div>
    <table class="w-100 h-100 no-spacing border" border="0">
        <tr class="h-100 bg-gray-white" style="font-size: 9px !important;">
            <td class="border-dark v-center text-center" rowspan="2" style="width: 40%">
                30. TITLE OF LEARNING AND DEVELOPMENT INTERVENTIONS/TRAINING/PROGRAMS<br/>(Write in full)

            </td>
            <td class="border-dark v-center text-center" colspan="2">
                INCLUSIVE DATES<br/>(mm/dd/yyyy)
            </td>
            <td class="border-dark v-center text-center" rowspan="2">
                NUMBER OF HOURS
            </td>
            <td class="border-dark v-center text-center" rowspan="2">
                Type of LD<br/>(Managerial/<br/>Supervisory/<br/>Technical/etc)
            </td>
            <td class="border-dark v-center text-center" rowspan="2">
                CONDUCTED/SPONSORED BY<br/>(Write in full)
            </td>
        </tr>
        <tr class="bg-gray-white">
            <td class="border-dark v-center text-center">
                FROM
            </td>
            <td class="border-dark v-center text-center">
                TO
            </td>
        </tr>
        @foreach($learning as $key)
            <tr>
                <td style="height: 33px !important;"
                    class="border-dark v-center text-center fw-bold"> {{ strtoupper($key['learning_title']) }}</td>
                <td class="border-dark v-center text-center fw-bold"> {{ strtoupper($key['attendance_from']) }}</td>
                <td class="border-dark v-center text-center fw-bold"> {{ strtoupper($key['attendance_to']) }}</td>
                <td class="border-dark v-center text-center fw-bold"> {{ strtoupper($key['number_hours']) }}</td>
                <td class="border-dark v-center text-center fw-bold"> {{ strtoupper($key['learning_type']) }}</td>
                <td class="border-dark v-center text-center fw-bold"> {{ strtoupper($key['conducted_by']) }}</td>
            </tr>
        @endforeach
        <tr class="bg-gray-white">
            <td colspan="6" style="color: darkred;height: unset !important;" class="v-center text-center fw-bold">
                (Continue on separate sheet if necessary)
            </td>
        </tr>
    </table>
    <table class="w-100 h-100 no-spacing border" border="0">
        <tr class="h-100 bg-gray-white" style="font-size: 9px !important;">
            <td class="border-dark v-center text-center">
                31. SPECIAL SKILLS and HOBBIES
            </td>
            <td class="border-dark v-center text-center" colspan="2">
                32. NON-ACADEMIC DISTINCTIONS/RECOGNITION<br/>(Write in full)
            </td>
            <td class="border-dark v-center text-center">
                32. MEMBERSHIP IN ASSOCIATION/ORGANIZATION<br/>(Write in full)
            </td>

        </tr>
        @foreach($skill as $key)
            <tr>
                <td style="height: 33px !important;"
                    class="border-dark v-center text-center fw-bold">{{ strtoupper($key['skill']) }}</td>
                <td class="border-dark v-center text-center fw-bold"
                    colspan="2">{{ strtoupper($key['recognition']) }}</td>
                <td class="border-dark v-center text-center fw-bold">{{ strtoupper($key['association']) }}</td>
            </tr>
        @endforeach
        <tr class="bg-gray-white">
            <td colspan="4" style="color: darkred;height: unset !important;" class="v-center text-center fw-bold">
                (Continue on separate sheet if necessary)
            </td>
        </tr>
        <tr>
            <td class="border-dark v-center text-center fw-bold bg-gray-white" style="width: 33.33%">
                SIGNATURE
            </td>
            <td class="border-dark" style="width: 25% !important;"></td>
            <td class="border-dark v-center text-center fw-bold bg-gray-white" style="width: 8.33% !important;">
                DATE
            </td>
            <td class="border-dark" style="width: 33.33%"></td>
        </tr>
    </table>
</div>
<div style="text-align: right">CS FORM 212 (Revised 2017), Page 3 of 4</div>

@if(count($next_page_learning) > 0)
    @for($i=0; $i<count($next_page_learning); $i++)
        <div style="page-break-before: always !important;"></div>
        @include('pdf.page_5',['learning'=>$next_page_learning[$i]])
    @endfor
@endif
